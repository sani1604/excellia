<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Academic Portal') — Excellia School (2026–27)</title>
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
@endphp

<div class="min-h-screen flex flex-col lg:flex-row">
    <!-- Institutional Sidebar -->
    <aside class="w-full lg:w-68 bg-slate-900 text-slate-200 flex-shrink-0 flex flex-col border-b lg:border-b-0 lg:border-r border-slate-800">
        <div class="px-5 py-5 border-b border-slate-800 flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-500 text-white font-bold text-sm shadow-sm">ES</span>
                    <span class="font-semibold text-white tracking-tight text-base">Excellia School</span>
                </div>
                <p class="text-xs text-slate-400 mt-1">Academic Document Portal · {{ $currentYearBadge }}</p>
            </div>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-1 text-sm overflow-y-auto">
            <a href="{{ route('dashboard') }}"
               class="flex items-center justify-between px-3 py-2 rounded-lg font-medium transition {{ request()->routeIs('dashboard') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                <span>Dashboard</span>
            </a>

            @if($authUser?->hasPermission('tasks.view_own'))
                <div class="pt-3 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Teacher Workspace</div>
                <a href="{{ route('teacher.tasks') }}"
                   class="flex items-center px-3 py-2 rounded-lg font-medium transition {{ request()->routeIs('teacher.tasks') || request()->routeIs('tasks.show') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    My Assigned Tasks
                </a>
                <a href="{{ route('teacher.submissions') }}"
                   class="flex items-center px-3 py-2 rounded-lg font-medium transition {{ request()->routeIs('teacher.submissions') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    My Submissions & History
                </a>
            @endif

            @if($authUser && ($authUser->hasPermission('submissions.review') || $authUser->hasPermission('tasks.view_scope') || $authUser->hasPermission('tasks.create') || $authUser->hasPermission('reports.view_school')))
                <div class="pt-3 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Academic Monitoring</div>

                @if($authUser->hasPermission('submissions.review') || $authUser->hasPermission('reports.view_scope') || $authUser->hasPermission('reports.view_school'))
                    <a href="{{ route('reviews.queue') }}"
                       class="flex items-center px-3 py-2 rounded-lg font-medium transition {{ request()->routeIs('reviews.*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        Review Queue
                    </a>
                @endif

                @if($authUser->hasPermission('tasks.view_scope') || $authUser->hasPermission('reports.view_scope') || $authUser->hasPermission('reports.view_school'))
                    <a href="{{ route('overdue.monitor') }}"
                       class="flex items-center px-3 py-2 rounded-lg font-medium transition {{ request()->routeIs('overdue.*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        Overdue & Escalations
                    </a>
                @endif

                @if($authUser->hasPermission('tasks.create') || $authUser->hasPermission('tasks.assign'))
                    <a href="{{ route('tasks.manage') }}"
                       class="flex items-center px-3 py-2 rounded-lg font-medium transition {{ request()->routeIs('tasks.manage') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        Task Assignment & Schedule
                    </a>
                @endif
            @endif

            @if($authUser && ($authUser->hasPermission('reports.view_own') || $authUser->hasPermission('reports.view_scope') || $authUser->hasPermission('reports.view_school')))
                <a href="{{ route('reports.index') }}"
                   class="flex items-center px-3 py-2 rounded-lg font-medium transition {{ request()->routeIs('reports.*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    Compliance Reports
                </a>
            @endif

            @if($authUser && ($authUser->hasPermission('users.view') || $authUser->hasPermission('academic_years.manage') || $authUser->hasPermission('file_types.manage') || $authUser->hasPermission('drive_mapping.manage') || $authUser->hasPermission('roles.view') || $authUser->hasPermission('system.settings.manage') || $authUser->hasPermission('audit.view')))
                <div class="pt-3 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Administration & Governance</div>

                @if($authUser->hasPermission('users.view'))
                    <a href="{{ route('admin.users') }}"
                       class="flex items-center px-3 py-2 rounded-lg font-medium transition {{ request()->routeIs('admin.users') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        User Master
                    </a>
                @endif

                @if($authUser->hasPermission('academic_years.manage') || $authUser->hasPermission('teacher_allocations.manage'))
                    <a href="{{ route('admin.academics') }}"
                       class="flex items-center px-3 py-2 rounded-lg font-medium transition {{ request()->routeIs('admin.academics') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        Academic Masters & Allocations
                    </a>
                @endif

                @if($authUser->hasPermission('file_types.manage'))
                    <a href="{{ route('admin.document-types') }}"
                       class="flex items-center px-3 py-2 rounded-lg font-medium transition {{ request()->routeIs('admin.document-types') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        Document Type Master
                    </a>
                @endif

                @if($authUser->hasPermission('drive_mapping.manage'))
                    <a href="{{ route('admin.drive-mappings') }}"
                       class="flex items-center px-3 py-2 rounded-lg font-medium transition {{ request()->routeIs('admin.drive-mappings') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        Google Drive Mappings
                    </a>
                @endif

                @if($authUser->hasPermission('roles.view') || $authUser->hasPermission('roles.manage'))
                    <a href="{{ route('admin.roles') }}"
                       class="flex items-center px-3 py-2 rounded-lg font-medium transition {{ request()->routeIs('admin.roles') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        Roles, Permissions & Scopes
                    </a>
                @endif

                @if($authUser->hasPermission('system.settings.manage') || $authUser->hasPermission('notifications.manage'))
                    <a href="{{ route('admin.settings') }}"
                       class="flex items-center px-3 py-2 rounded-lg font-medium transition {{ request()->routeIs('admin.settings') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        Settings & Escalation Rules
                    </a>
                @endif

                @if($authUser->hasPermission('audit.view'))
                    <a href="{{ route('admin.audit-logs') }}"
                       class="flex items-center px-3 py-2 rounded-lg font-medium transition {{ request()->routeIs('admin.audit-logs') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        Audit Log
                    </a>
                @endif
            @endif

            <a href="{{ route('notifications.index') }}"
               class="flex items-center justify-between px-3 py-2 rounded-lg font-medium transition {{ request()->routeIs('notifications.*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                <span>Notifications</span>
                @if($unreadCount > 0)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-500 text-slate-950">{{ $unreadCount }}</span>
                @endif
            </a>
        </nav>

        @if($authUser)
            <div class="p-4 border-t border-slate-800 bg-slate-950/60">
                <div class="text-xs font-semibold text-white truncate">{{ $authUser->full_name }}</div>
                <div class="text-[11px] text-indigo-300 mt-0.5">{{ $authUser->primaryRole?->name ?? 'User' }}</div>
                <div class="text-[11px] text-slate-400 truncate">{{ $authUser->email }}</div>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="w-full text-left text-xs font-medium text-slate-300 hover:text-white bg-slate-800 hover:bg-slate-700 px-3 py-1.5 rounded-md transition cursor-pointer">
                        Sign out
                    </button>
                </form>
            </div>
        @endif
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0">
        <header class="bg-white border-b border-slate-200 px-6 py-4 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-lg font-semibold text-slate-900">@yield('heading', 'Dashboard')</h1>
                @hasSection('subheading')
                    <p class="text-xs text-slate-500 mt-0.5">@yield('subheading')</p>
                @endif
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">
                    TZ: {{ \App\Models\SystemSetting::timezone() }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                    {{ $authUser?->primaryRole?->key }}
                </span>
            </div>
        </header>

        <main class="flex-1 p-6 max-w-7xl w-full mx-auto space-y-6">
            @if(session('success'))
                <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800 font-medium">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800 font-medium">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800">
                    <div class="font-semibold mb-1">Please correct the following errors:</div>
                    <ul class="list-disc list-inside space-y-0.5 text-xs">
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
</body>
</html>
