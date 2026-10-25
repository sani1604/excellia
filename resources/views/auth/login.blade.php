<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In — Excellia School Academic Portal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full flex items-center justify-center py-10 px-4 text-slate-800">
<div class="max-w-5xl w-full grid grid-cols-1 lg:grid-cols-12 bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
    <!-- Left Sign-In Form -->
    <div class="lg:col-span-5 p-8 lg:p-10 flex flex-col justify-between">
        <div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-600 text-white font-bold text-base">ES</span>
                <div>
                    <h1 class="text-lg font-bold text-slate-900">Excellia School</h1>
                    <p class="text-xs text-slate-500">Academic Document Submission & Monitoring · 2026–27</p>
                </div>
            </div>

            <h2 class="mt-8 text-base font-semibold text-slate-900">Portal Sign In</h2>
            <p class="text-xs text-slate-500 mt-1">Sign in with Google Workspace SSO or school-managed credentials.</p>

            @if($errors->any())
                <div class="mt-4 rounded-lg bg-rose-50 border border-rose-200 px-3.5 py-2.5 text-xs text-rose-700 font-medium">
                    {{ $errors->first() }}
                </div>
            @endif

            <!-- Google Workspace SSO Button -->
            <div class="mt-5">
                <a href="{{ route('login.google') }}"
                   class="w-full inline-flex items-center justify-center gap-2.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-800 font-semibold py-2.5 px-4 text-xs shadow-xs transition">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v2.98h3.86c2.26-2.09 3.56-5.17 3.56-8.8z"/>
                        <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-2.98c-1.08.72-2.45 1.16-4.07 1.16-3.12 0-5.77-2.11-6.72-4.96H1.29v3.09C3.26 21.3 7.31 24 12 24z"/>
                        <path fill="#FBBC05" d="M5.28 14.31c-.24-.72-.38-1.49-.38-2.31s.14-1.59.38-2.31V6.6H1.29C.47 8.24 0 10.06 0 12s.47 3.76 1.29 5.4l3.99-3.09z"/>
                        <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.29 6.6l3.99 3.09c.95-2.85 3.6-4.94 6.72-4.94z"/>
                    </svg>
                    <span>Sign in with Google Workspace ({{ '@' . ($googleHostedDomain ?? 'excelliaschool.edu.in') }})</span>
                </a>
            </div>

            <div class="relative my-5">
                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-slate-200"></div></div>
                <div class="relative flex justify-center text-[11px] uppercase tracking-wider">
                    <span class="bg-white px-2.5 text-slate-400">or email & password</span>
                </div>
            </div>

            <form method="POST" action="{{ route('login.submit') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-medium text-slate-700 mb-1">School Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', 'ananya.rao@excelliaschool.edu.in') }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label for="password" class="block text-xs font-medium text-slate-700 mb-1">Password</label>
                    <input id="password" name="password" type="password" value="Excellia@2026" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                </div>

                <button type="submit"
                        class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 text-sm shadow-sm transition cursor-pointer">
                    Sign In to Portal
                </button>
            </form>
        </div>

        <p class="mt-8 text-[11px] text-slate-400">
            Role-based access enforced server-side · Google Drive least-privilege storage · Audit logging active.
        </p>
    </div>

    <!-- Right Quick Role Switcher for Demo / UAT Verification -->
    <div class="lg:col-span-7 bg-slate-900 text-slate-100 p-8 lg:p-10">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-indigo-400">UAT & Role Evaluation</span>
                <h3 class="text-base font-semibold text-white mt-0.5">One-Click Role Sign-In</h3>
            </div>
            <span class="text-[11px] bg-slate-800 text-slate-300 px-2.5 py-1 rounded border border-slate-700">
                Default Password: <code class="text-indigo-300">Excellia@2026</code>
            </span>
        </div>
        <p class="text-xs text-slate-400 mt-1.5">
            Click any role account below to inspect scoped dashboards, teacher uploads, coordinator reviews, escalations, and admin masters.
        </p>

        <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($demoAccounts as $acc)
                <form method="POST" action="{{ route('login.demo') }}">
                    @csrf
                    <input type="hidden" name="email" value="{{ $acc->email }}">
                    <button type="submit"
                            class="w-full text-left p-3.5 rounded-xl bg-slate-800/80 hover:bg-slate-800 border border-slate-700 hover:border-indigo-500 transition cursor-pointer">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-semibold text-white truncate">{{ $acc->full_name }}</span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                {{ $acc->primaryRole?->key }}
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-400 mt-1 truncate">{{ $acc->email }}</div>
                    </button>
                </form>
            @endforeach
        </div>
    </div>
</div>
</body>
</html>
