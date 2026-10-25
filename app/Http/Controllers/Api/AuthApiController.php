<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\MasterDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthApiController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly MasterDataService $masterService,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = mb_strtolower(trim($credentials['email']));
        $user = User::query()->with('primaryRole')->where('email', $email)->first();

        if (! $user || ! $user->password || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (! $user->is_active || $user->trashed()) {
            return response()->json(['message' => 'Account is deactivated and cannot log in.'], 403);
        }

        $plainToken = 'exc_' . Str::random(48);
        $user->update([
            'api_token_hash' => hash('sha256', $plainToken),
            'last_login_at' => now(),
        ]);

        $this->audit->log('auth.api_login', 'user', $user->id, null, ['login_provider' => $user->login_provider], $user, $request);

        return response()->json([
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'user' => $this->formatUser($user),
        ]);
    }

    public function googleLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'access_token' => ['required', 'string'],
        ]);

        $profileResp = \Illuminate\Support\Facades\Http::withToken($validated['access_token'])
            ->get('https://openidconnect.googleapis.com/v1/userinfo');

        if (! $profileResp->successful() || ! $profileResp->json('email') || ! (bool) $profileResp->json('email_verified', false)) {
            return response()->json(['message' => 'Invalid or unverified Google Workspace access token.'], 401);
        }

        $email = mb_strtolower(trim((string) $profileResp->json('email')));
        $allowedDomain = mb_strtolower(trim((string) config('excellia.sso.allowed_hosted_domain', '')));
        if ($allowedDomain !== '' && ! str_ends_with($email, '@' . $allowedDomain)) {
            return response()->json(['message' => "Only @{$allowedDomain} Google Workspace accounts are permitted."], 403);
        }

        $user = User::query()->with('primaryRole')->where('email', $email)->first();
        if (! $user) {
            return response()->json(['message' => 'No registered portal account found for this Google email.'], 403);
        }
        if (! $user->is_active || $user->trashed()) {
            return response()->json(['message' => 'Account is deactivated and cannot log in.'], 403);
        }

        $plainToken = 'exc_' . Str::random(48);
        $user->update([
            'login_provider' => 'google_workspace',
            'provider_subject_id' => (string) ($profileResp->json('sub') ?: $user->provider_subject_id),
            'oauth_access_token' => $validated['access_token'],
            'api_token_hash' => hash('sha256', $plainToken),
            'last_login_at' => now(),
        ]);

        $this->audit->log('auth.api_google_login', 'user', $user->id, null, ['provider' => 'google_workspace'], $user, $request);

        return response()->json([
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'user' => $this->formatUser($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->loadMissing(['primaryRole', 'secondaryRoles', 'activeScopeAssignments']);

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    public function indexUsers(Request $request): JsonResponse
    {
        $users = User::query()
            ->with(['primaryRole', 'activeScopeAssignments'])
            ->when($request->filled('role_key'), fn ($q) => $q->whereHas('primaryRole', fn ($rq) => $rq->where('key', $request->string('role_key'))))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('full_name')
            ->paginate((int) $request->integer('per_page', 25));

        return response()->json($users);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_code' => ['nullable', 'string', 'max:50'],
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['nullable', 'string', 'min:8'],
            'login_provider' => ['nullable', 'string', 'in:local,google_workspace'],
            'primary_role_id' => ['required', 'integer', 'exists:roles,id'],
            'secondary_role_ids' => ['nullable', 'array'],
            'secondary_role_ids.*' => ['integer', 'exists:roles,id'],
            'can_access_restricted_files' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = $this->masterService->createUser($validated, $request->user());

        return response()->json(['data' => $this->formatUser($user)], 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'employee_code' => ['nullable', 'string', 'max:50'],
            'full_name' => ['sometimes', 'string', 'max:160'],
            'email' => ['sometimes', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['nullable', 'string', 'min:8'],
            'primary_role_id' => ['sometimes', 'integer', 'exists:roles,id'],
            'can_access_restricted_files' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->masterService->updateUser($user, $validated, $request->user());

        return response()->json(['data' => $this->formatUser($updated)]);
    }

    public function deactivateUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->masterService->deactivateUser($user, $validated['reason'] ?? null, $request->user());

        return response()->json([
            'message' => 'User deactivated successfully.',
            'data' => $this->formatUser($result['user']),
            'flagged_allocations' => $result['flagged_allocations'],
            'open_tasks_count' => $result['open_tasks_count'],
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'employee_code' => $user->employee_code,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'primary_role' => $user->primaryRole?->key,
            'primary_role_name' => $user->primaryRole?->name,
            'roles' => $user->roleKeys(),
            'permissions' => $user->permissionKeys(),
            'can_access_restricted_files' => $user->can_access_restricted_files,
            'is_active' => $user->is_active,
            'needs_reassignment_review' => $user->needs_reassignment_review,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }
}
