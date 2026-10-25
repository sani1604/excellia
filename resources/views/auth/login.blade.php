<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In — Excellia School Portal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full flex items-center justify-center py-6 sm:py-10 px-4 text-slate-800">
<div class="max-w-4xl w-full grid grid-cols-1 lg:grid-cols-12 bg-white rounded-2xl shadow-md border border-slate-200 overflow-hidden">
    <!-- Left Sign-In Form -->
    <div class="lg:col-span-6 p-6 sm:p-8 lg:p-10 flex flex-col justify-between">
        <div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-indigo-600 text-white font-bold text-lg shrink-0">ES</span>
                <div>
                    <h1 class="text-xl font-bold text-slate-900">Excellia School</h1>
                    <p class="text-xs sm:text-sm text-slate-500">Academic Submission Portal · 2026–27</p>
                </div>
            </div>

            <h2 class="mt-7 text-lg font-bold text-slate-900">Portal Sign In</h2>
            <p class="text-sm text-slate-500 mt-1">Sign in with your school account to continue.</p>

            @if($errors->any())
                <div class="mt-4 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800 font-medium">
                    {{ $errors->first() }}
                </div>
            @endif

            @if($googleSsoEnabled ?? true)
                <div class="mt-6">
                    <a href="{{ route('login.google') }}"
                       class="w-full inline-flex items-center justify-center gap-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-800 font-semibold py-3 px-4 text-sm shadow-2xs transition">
                        <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v2.98h3.86c2.26-2.09 3.56-5.17 3.56-8.8z"/>
                            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-2.98c-1.08.72-2.45 1.16-4.07 1.16-3.12 0-5.77-2.11-6.72-4.96H1.29v3.09C3.26 21.3 7.31 24 12 24z"/>
                            <path fill="#FBBC05" d="M5.28 14.31c-.24-.72-.38-1.49-.38-2.31s.14-1.59.38-2.31V6.6H1.29C.47 8.24 0 10.06 0 12s.47 3.76 1.29 5.4l3.99-3.09z"/>
                            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.29 6.6l3.99 3.09c.95-2.85 3.6-4.94 6.72-4.94z"/>
                        </svg>
                        <span>Sign in with Google Workspace</span>
                    </a>
                </div>

                <div class="relative my-5">
                    <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-slate-200"></div></div>
                    <div class="relative flex justify-center text-xs">
                        <span class="bg-white px-3 text-slate-400">or use school email & password</span>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('login.submit') }}" class="space-y-4 mt-4">
                @csrf
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1">School Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', 'admin@excelliaschool.edu.in') }}" required
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                    <input id="password" name="password" type="password" value="Excellia@2026" required
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:outline-none">
                </div>

                <button type="submit"
                        class="w-full rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 text-sm shadow-xs transition cursor-pointer">
                    Sign In
                </button>
            </form>
        </div>
    </div>

    <!-- Right Quick Role Selector (Super Admin First!) -->
    <div class="lg:col-span-6 bg-indigo-50/60 border-t lg:border-t-0 lg:border-l border-slate-200 p-6 sm:p-8 lg:p-10">
        <div>
            <h3 class="text-base font-bold text-slate-900">Quick Role Sign-In (Demo)</h3>
            <p class="text-sm text-slate-600 mt-1">
                Tap <strong>Super Admin</strong> for all Masters & Integrations, or tap any Teacher / Coordinator / Principal below.
            </p>
        </div>

        <div class="mt-4 space-y-2">
            @foreach($demoAccounts as $acc)
                @php $isSa = $acc->primaryRole?->key === 'SUPER_ADMIN'; @endphp
                <form method="POST" action="{{ route('login.demo') }}">
                    @csrf
                    <input type="hidden" name="email" value="{{ $acc->email }}">
                    <button type="submit"
                            class="w-full text-left px-4 py-3 rounded-xl {{ $isSa ? 'bg-slate-900 hover:bg-slate-800 text-white border-slate-900' : 'bg-white hover:bg-indigo-50 text-slate-900 border-slate-200 hover:border-indigo-400' }} border flex items-center justify-between gap-3 transition cursor-pointer shadow-2xs">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold truncate">{{ $acc->full_name }}</div>
                            <div class="text-xs {{ $isSa ? 'text-slate-300' : 'text-slate-500' }} truncate">{{ $acc->email }}</div>
                        </div>
                        <span class="shrink-0 text-xs font-semibold px-2.5 py-1 rounded-full {{ $isSa ? 'bg-amber-400 text-slate-950' : 'bg-indigo-100 text-indigo-800' }}">
                            {{ $acc->primaryRole?->name ?? 'Staff' }}
                        </span>
                    </button>
                </form>
            @endforeach
        </div>
    </div>
</div>
</body>
</html>
