<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'School Portal') — Excellia School</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full text-slate-800 antialiased">
@php
    /** @var \App\Models\User|null $authUser */
    $authUser = auth()->user();
    $unreadCount = $authUser
        ? \App\Models\NotificationLog::query()->where('recipient_user_id', $authUser->id)->whereNull('read_at')->count()
        : 0;
    $currentYearBadge = \App\Models\AcademicYear::current()?->name ?? '2026-27';
    $isSuperAdmin = $authUser?->primaryRole?->key === \App\Models\Role::SUPER_ADMIN;
@endphp

<!-- Mobile Sticky Top Bar (Shown on Phones & Tablets < lg) -->
<div class="lg:hidden sticky top-0 z-30 bg-white border-b border-slate-200 px-4 py-3 flex items-center justify-between gap-3">
    <div class="flex items-center gap-2.5 min-w-0">
        <button type="button" onclick="window.toggleMobileNav(true)" aria-label="Open menu"
                class="inline-flex items-center justify-center w-10 h-10 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-100 shrink-0 cursor-pointer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 min-w-0">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-600 text-white font-bold text-xs shrink-0">ES</span>
            <span class="font-bold text-slate-900 text-sm truncate">Excellia School</span>
        </a>
    </div>

    <div class="flex items-center gap-2 shrink-0">
        <a href="{{ route('notifications.index') }}" class="relative inline-flex items-center px-2.5 py-1.5 rounded-lg bg-slate-100 text-xs font-semibold text-slate-700">
            Alerts
            @if($unreadCount > 0)
                <span class="ml-1 inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-500 text-white">{{ $unreadCount }}</span>
            @endif
        </a>
    </div>
</div>

<!-- Mobile Backdrop Overlay -->
<div id="mobile-nav-backdrop" onclick="window.toggleMobileNav(false)"
     class="fixed inset-0 z-40 bg-slate-900/50 hidden lg:hidden"></div>

<div class="min-h-screen flex flex-col lg:flex-row">
    <!-- Responsive Sidebar (Slide-over Drawer on Mobile, Sticky Left Column on Desktop) -->
    <aside id="app-sidebar"
           class="fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] -translate-x-full lg:translate-x-0 lg:static lg:w-64 lg:h-screen lg:sticky lg:top-0 bg-white border-r border-slate-200 flex-shrink-0 flex flex-col transition-transform duration-200 ease-out">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 min-w-0">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-600 text-white font-bold text-base shadow-xs shrink-0">ES</span>
                <div class="min-w-0">
                    <div class="font-bold text-slate-900 text-base leading-tight truncate">Excellia School</div>
                    <div class="text-xs text-slate-500 mt-0.5">Session {{ $currentYearBadge }}</div>
                </div>
            </a>
            <button type="button" onclick="window.toggleMobileNav(false)" aria-label="Close menu"
                    class="lg:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100 cursor-pointer">
                ✕
            </button>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-1 text-sm overflow-y-auto">
            <a href="{{ route('dashboard') }}"
               class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl font-medium transition {{ request()->routeIs('dashboard') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-700 hover:bg-slate-100' }}">
                <span>Home</span>
            </a>

            @if($authUser?->hasPermission('tasks.view_own'))
                <a href="{{ route('teacher.tasks') }}"
                   class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl font-medium transition {{ request()->routeIs('teacher.tasks') || request()->routeIs('tasks.show') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-700 hover:bg-slate-100' }}">
                    <span>My Tasks</span>
                </a>
                <a href="{{ route('teacher.submissions') }}"
                   class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl font-medium transition {{ request()->routeIs('teacher.submissions') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-700 hover:bg-slate-100' }}">
                    <span>My Uploaded Files</span>
                </a>
            @endif

            @if($authUser && ($authUser->hasPermission('submissions.review') || $authUser->hasPermission('tasks.view_scope') || $authUser->hasPermission('tasks.create') || $authUser->hasPermission('reports.view_school')))
                <div class="pt-4 pb-1 px-3.5 text-xs font-semibold uppercase tracking-wider text-slate-400">Supervision</div>

                @if($authUser->hasPermission('submissions.review') || $authUser->hasPermission('reports.view_scope') || $authUser->hasPermission('reports.view_school'))
                    <a href="{{ route('reviews.queue') }}"
                       class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium transition {{ request()->routeIs('reviews.*') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-700 hover:bg-slate-100' }}">
                        <span>Files to Check</span>
                    </a>
                @endif

                @if($authUser->hasPermission('tasks.view_scope') || $authUser->hasPermission('reports.view_scope') || $authUser->hasPermission('reports.view_school'))
                    <a href="{{ route('overdue.monitor') }}"
                       class="flex items-center px-3.5 py-2.5 rounded-xl font-medium transition {{ request()->routeIs('overdue.*') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-700 hover:bg-slate-100' }}">
                        <span>Late Submissions</span>
                    </a>
                @endif

                @if($authUser->hasPermission('tasks.create') || $authUser->hasPermission('tasks.assign'))
                    <a href="{{ route('tasks.manage') }}"
                       class="flex items-center px-3.5 py-2.5 rounded-xl font-medium transition {{ request()->routeIs('tasks.manage') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-700 hover:bg-slate-100' }}">
                        <span>Assign Tasks</span>
                    </a>
                @endif
            @endif

            @if($authUser && ($authUser->hasPermission('reports.view_scope') || $authUser->hasPermission('reports.view_school')))
                <div class="pt-4 pb-1 px-3.5 text-xs font-semibold uppercase tracking-wider text-slate-400">Management & Analytics</div>
                <a href="{{ route('management.mis') }}"
                   class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium transition {{ request()->routeIs('management.mis*') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-700 hover:bg-slate-100' }}">
                    <span>Management MIS & Charts</span>
                    <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800">Charts</span>
                </a>
            @endif

            @if($authUser && ($authUser->hasPermission('reports.view_own') || $authUser->hasPermission('reports.view_scope') || $authUser->hasPermission('reports.view_school')))
                <a href="{{ route('reports.index') }}"
                   class="flex items-center px-3.5 py-2.5 rounded-xl font-medium transition {{ request()->routeIs('reports.*') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-700 hover:bg-slate-100' }}">
                    <span>Reports</span>
                </a>
            @endif

            <a href="{{ route('notifications.index') }}"
               class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium transition {{ request()->routeIs('notifications.*') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-700 hover:bg-slate-100' }}">
                <span>Alerts & Messages</span>
                @if($unreadCount > 0)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-500 text-white">{{ $unreadCount }}</span>
                @endif
            </a>

            @if($authUser && ($authUser->hasPermission('users.view') || $authUser->hasPermission('academic_years.manage') || $authUser->hasPermission('file_types.manage') || $authUser->hasPermission('drive_mapping.manage') || $authUser->hasPermission('roles.view') || $authUser->hasPermission('system.settings.manage') || $authUser->hasPermission('audit.view')))
                <div class="pt-4 pb-1 px-3.5 text-xs font-semibold uppercase tracking-wider text-slate-400">
                    {{ $isSuperAdmin ? 'Super Admin Masters' : 'School Masters' }}
                </div>

                @if($authUser->hasPermission('users.view'))
                    <a href="{{ route('admin.users') }}"
                       class="flex items-center px-3.5 py-2 rounded-xl font-medium transition {{ request()->routeIs('admin.users') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">
                        User & Staff Master
                    </a>
                @endif

                @if($authUser->hasPermission('roles.view') || $authUser->hasPermission('roles.manage'))
                    <a href="{{ route('admin.roles') }}"
                       class="flex items-center px-3.5 py-2 rounded-xl font-medium transition {{ request()->routeIs('admin.roles') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">
                        Role & Scope Mapping
                    </a>
                @endif

                @if($authUser->hasPermission('academic_years.manage') || $authUser->hasPermission('teacher_allocations.manage') || $authUser->hasPermission('classes.manage'))
                    <a href="{{ route('admin.academics') }}"
                       class="flex items-center px-3.5 py-2 rounded-xl font-medium transition {{ request()->routeIs('admin.academics') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">
                        Academic & Teacher Master
                    </a>
                @endif

                @if($authUser->hasPermission('file_types.manage'))
                    <a href="{{ route('admin.document-types') }}"
                       class="flex items-center px-3.5 py-2 rounded-xl font-medium transition {{ request()->routeIs('admin.document-types') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">
                        Document Type Master
                    </a>
                @endif

                @if($authUser->hasPermission('drive_mapping.manage'))
                    <a href="{{ route('admin.drive-mappings') }}"
                       class="flex items-center px-3.5 py-2 rounded-xl font-medium transition {{ request()->routeIs('admin.drive-mappings') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">
                        Google Drive Mapping
                    </a>
                @endif

                @if($authUser->hasPermission('system.settings.manage') || $authUser->hasPermission('notifications.manage'))
                    <a href="{{ route('admin.settings') }}"
                       class="flex items-center px-3.5 py-2 rounded-xl font-medium transition {{ request()->routeIs('admin.settings') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">
                        Integrations & Settings
                    </a>
                @endif

                @if($authUser->hasPermission('audit.view'))
                    <a href="{{ route('admin.audit-logs') }}"
                       class="flex items-center px-3.5 py-2 rounded-xl font-medium transition {{ request()->routeIs('admin.audit-logs') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">
                        Activity History
                    </a>
                @endif
            @endif
        </nav>

        @if($authUser)
            <div class="p-4 border-t border-slate-200 bg-slate-50/70">
                <div class="text-sm font-semibold text-slate-900 truncate">{{ $authUser->full_name }}</div>
                <div class="text-xs text-indigo-700 font-medium mt-0.5">{{ $authUser->primaryRole?->name ?? 'Staff' }}</div>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="w-full text-center text-xs font-semibold text-slate-700 bg-white hover:bg-slate-100 border border-slate-300 px-3 py-2 rounded-lg transition cursor-pointer">
                        Sign Out
                    </button>
                </form>
            </div>
        @endif
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0">
        <header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-4 flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-lg sm:text-xl font-bold text-slate-900 break-words">@yield('heading', 'Home')</h1>
                @hasSection('subheading')
                    <p class="text-xs sm:text-sm text-slate-500 mt-0.5">@yield('subheading')</p>
                @endif
            </div>
            <div class="hidden sm:flex items-center gap-2 text-sm shrink-0">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $isSuperAdmin ? 'bg-slate-900 text-white' : 'bg-indigo-50 text-indigo-700' }}">
                    {{ $authUser?->primaryRole?->name ?? 'School Staff' }}
                </span>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-6 max-w-6xl w-full mx-auto space-y-6">
            @if(session('success'))
                <div class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3.5 text-sm text-emerald-900 font-medium">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3.5 text-sm text-rose-900 font-medium">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3.5 text-sm text-rose-900">
                    <div class="font-semibold mb-1">Please check the details below:</div>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script>
    window.toggleMobileNav = function (open) {
        const sidebar = document.getElementById('app-sidebar');
        const backdrop = document.getElementById('mobile-nav-backdrop');
        if (!sidebar || !backdrop) return;
        if (open) {
            sidebar.classList.remove('-translate-x-full');
            backdrop.classList.remove('hidden');
        } else {
            sidebar.classList.add('-translate-x-full');
            backdrop.classList.add('hidden');
        }
    };

    window.excelliaAiAssist = async function (btn, payload, onResult) {
        const origLabel = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Generating...';
        try {
            const resp = await fetch('{{ route("ai.assist") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(payload)
            });
            const data = await resp.json();
            if (!resp.ok || !data.ok) {
                alert(data.message || 'Unable to generate AI suggestion right now.');
                return;
            }
            onResult(data.content, data);
        } catch (e) {
            alert('Network error while contacting AI helper.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = origLabel;
        }
    };
</script>
</body>
</html>
