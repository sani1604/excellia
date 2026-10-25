<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()->is_active) {
            return redirect()->route('dashboard');
        }

        // Order clearly: Super Admin FIRST, then Principal, Director, Headmistress, Coordinator, Teachers, Viewer
        $rolePriority = [
            Role::SUPER_ADMIN => 1,
            Role::PRINCIPAL => 2,
            Role::DIRECTOR => 3,
            Role::HEADMISTRESS => 4,
            Role::COORDINATOR => 5,
            Role::TEACHER => 6,
            Role::VIEWER => 7,
        ];

        $demoAccounts = User::query()
            ->with('primaryRole')
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->unique('primary_role_id')
            ->sortBy(fn (User $u) => $rolePriority[$u->primaryRole?->key ?? ''] ?? 99)
            ->values();

        $secondTeacher = User::query()
            ->with('primaryRole')
            ->where('is_active', true)
            ->whereHas('primaryRole', fn ($q) => $q->where('key', Role::TEACHER))
            ->skip(1)
            ->first();

        if ($secondTeacher) {
            $demoAccounts->push($secondTeacher);
        }

        $ssoEnabled = (bool) SystemSetting::getValue('google_sso_enabled', config('excellia.sso.enabled', true));
        $clientId = $this->resolveGoogleClientId();
        $clientSecret = $this->resolveGoogleClientSecret();

        return view('auth.login', [
            'demoAccounts' => $demoAccounts,
            'googleSsoEnabled' => $ssoEnabled,
            'googleSsoConfigured' => $ssoEnabled && $clientId !== '' && $clientSecret !== '',
            'googleHostedDomain' => $this->resolveHostedDomain(),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = mb_strtolower(trim($credentials['email']));
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! $user->password || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withInput(['email' => $credentials['email']])->withErrors([
                'email' => 'Invalid email address or password.',
            ]);
        }

        if (! $user->is_active || $user->trashed()) {
            return back()->withInput(['email' => $credentials['email']])->withErrors([
                'email' => 'Your account is inactive. Please contact the System Administrator.',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->update(['last_login_at' => now()]);
        $this->audit->log('auth.login', 'user', $user->id, null, ['provider' => $user->login_provider], $user, $request);

        return redirect()->intended(route('dashboard'));
    }

    public function redirectToGoogle(Request $request): RedirectResponse
    {
        $ssoEnabled = (bool) SystemSetting::getValue('google_sso_enabled', config('excellia.sso.enabled', true));
        if (! $ssoEnabled) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google Workspace sign-in is currently disabled by Super Admin.',
            ]);
        }

        $clientId = $this->resolveGoogleClientId();
        $clientSecret = $this->resolveGoogleClientSecret();

        if ($clientId === '' || $clientSecret === '') {
            return redirect()->route('login')->withErrors([
                'email' => 'Google Workspace SSO credentials are not configured yet. Super Admin can add Client ID & Secret under Integrations & Settings -> Google Drive & SSO.',
            ]);
        }

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        $redirectUri = (string) (config('excellia.sso.google_redirect_uri') ?: route('login.google.callback'));
        $hostedDomain = $this->resolveHostedDomain();

        $query = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ];
        if ($hostedDomain !== '') {
            $query['hd'] = $hostedDomain;
        }

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($query));
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $expectedState = (string) $request->session()->pull('google_oauth_state', '');
        $incomingState = (string) $request->query('state', '');

        if ($expectedState === '' || ! hash_equals($expectedState, $incomingState)) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google Workspace sign-in session expired or state mismatch. Please try again.',
            ]);
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google Workspace sign-in was cancelled or denied.',
            ]);
        }

        $clientId = $this->resolveGoogleClientId();
        $clientSecret = $this->resolveGoogleClientSecret();
        $redirectUri = (string) (config('excellia.sso.google_redirect_uri') ?: route('login.google.callback'));

        $tokenResp = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => (string) $request->query('code'),
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (! $tokenResp->successful() || ! $tokenResp->json('access_token')) {
            return redirect()->route('login')->withErrors([
                'email' => 'Failed to exchange Google Workspace authorization code.',
            ]);
        }

        $accessToken = (string) $tokenResp->json('access_token');
        $refreshToken = $tokenResp->json('refresh_token');

        $profileResp = Http::withToken($accessToken)->get('https://openidconnect.googleapis.com/v1/userinfo');
        if (! $profileResp->successful() || ! $profileResp->json('email')) {
            return redirect()->route('login')->withErrors([
                'email' => 'Unable to retrieve verified email from Google Workspace.',
            ]);
        }

        if (! (bool) $profileResp->json('email_verified', false)) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google account email address is not verified.',
            ]);
        }

        $email = mb_strtolower(trim((string) $profileResp->json('email')));
        $allowedDomain = mb_strtolower(trim($this->resolveHostedDomain()));
        if ($allowedDomain !== '' && ! str_ends_with($email, '@' . $allowedDomain)) {
            $this->audit->log('auth.google_sso_denied', 'user', null, null, ['email' => $email, 'reason' => 'domain_mismatch'], null, $request);

            return redirect()->route('login')->withErrors([
                'email' => "Only @{$allowedDomain} Google Workspace accounts are permitted.",
            ]);
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            $this->audit->log('auth.google_sso_denied', 'user', null, null, ['email' => $email, 'reason' => 'unregistered_account'], null, $request);

            return redirect()->route('login')->withErrors([
                'email' => 'No portal user account is registered for ' . $email . '. Contact the System Administrator.',
            ]);
        }

        if (! $user->is_active || $user->trashed()) {
            $this->audit->log('auth.google_sso_denied', 'user', $user->id, null, ['email' => $email, 'reason' => 'deactivated'], $user, $request);

            return redirect()->route('login')->withErrors([
                'email' => 'Your account is inactive. Please contact the System Administrator.',
            ]);
        }

        $updates = [
            'login_provider' => 'google_workspace',
            'provider_subject_id' => (string) ($profileResp->json('sub') ?: $user->provider_subject_id),
            'oauth_access_token' => $accessToken,
            'last_login_at' => now(),
        ];
        if (! empty($refreshToken)) {
            $updates['oauth_refresh_token'] = (string) $refreshToken;
        }
        $user->update($updates);

        Auth::login($user);
        $request->session()->regenerate();

        $this->audit->log(
            'auth.google_sso_login',
            'user',
            $user->id,
            null,
            ['provider' => 'google_workspace', 'provider_subject_id' => $updates['provider_subject_id']],
            $user,
            $request
        );

        return redirect()->intended(route('dashboard'))->with('success', "Signed in via Google Workspace as {$user->full_name}.");
    }

    public function demoSwitch(Request $request): RedirectResponse
    {
        if (! app()->environment(['local', 'testing'])) {
            abort(403);
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()
            ->where('email', mb_strtolower(trim($validated['email'])))
            ->where('is_active', true)
            ->firstOrFail();

        Auth::login($user);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);
        $this->audit->log('auth.demo_switch', 'user', $user->id, null, ['role' => $user->primaryRole?->key], $user, $request);

        return redirect()->route('dashboard')->with('success', "Signed in as {$user->full_name} ({$user->primaryRole?->name}).");
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            $this->audit->log('auth.logout', 'user', $user->id, null, null, $user, $request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function resolveGoogleClientId(): string
    {
        return trim((string) (config('excellia.sso.google_client_id') ?: SystemSetting::getValue('google_sso_client_id', '')));
    }

    private function resolveGoogleClientSecret(): string
    {
        return trim((string) (config('excellia.sso.google_client_secret') ?: SystemSetting::getSecret('google_sso_client_secret', '')));
    }

    private function resolveHostedDomain(): string
    {
        return trim((string) SystemSetting::getValue(
            'google_sso_hosted_domain',
            config('excellia.sso.allowed_hosted_domain', 'excelliaschool.edu.in')
        ));
    }
}
