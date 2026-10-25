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
@endphp

<div class="min-h-screen flex flex-col lg:flex-row">
    <!-- Clean, Friendly Light School Sidebar -->
    <aside class="w-full lg:w-64 bg-white border-b lg:border-b-0 lg:border-r border-slate-200 flex-shrink-0 flex flex-col">
        <div class="px-5 py-5 border-b border-slate-100 flex items-center gap-3">
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-600 text-white font-bold text-base shadow-xs">ES</span>
            <div>
                <div class="font-bold text-slate-900 text-base leading-tight">Excellia School</div>
                <div class="text-xs text-slate-500 mt-0.5">Session {{ $currentYearBadge }}</div>
            </div>
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
                <div class="pt-4 pb-1 px-3.5 text-xs font-semibold text-slate-400">Academic Supervision</div>

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
                <div class="pt-4 pb-1 px-3.5 text-xs font-semibold text-slate-400">School Setup</div>

                @if($authUser->hasPermission('users.view'))
                    <a href="{{ route('admin.users') }}"
                       class="flex items-center px-3.5 py-2 rounded-xl font-medium transition {{ request()->routeIs('admin.users') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">
                        Staff Accounts
                    </a>
                @endif

                @if($authUser->hasPermission('academic_years.manage') || $authUser->hasPermission('teacher_allocations.manage'))
                    <a href="{{ route('admin.academics') }}"
                       class="flex items-center px-3.5 py-2 rounded-xl font-medium transition {{ request()->routeIs('admin.academics') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">
                        Classes & Teachers
                    </a>
                @endif

                @if($authUser->hasPermission('file_types.manage'))
                    <a href="{{ route('admin.document-types') }}"
                       class="flex items-center px-3.5 py-2 rounded-xl font-medium transition {{ request()->routeIs('admin.document-types') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">
                        Document Types
                    </a>
                @endif

                @if($authUser->hasPermission('roles.view') || $authUser->hasPermission('roles.manage'))
                    <a href="{{ route('admin.roles') }}"
                       class="flex items-center px-3.5 py-2 rounded-xl font-medium transition {{ request()->routeIs('admin.roles') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">
                        Staff Access & Wings
                    </a>
                @endif

                @if($authUser->hasPermission('drive_mapping.manage'))
                    <a href="{{ route('admin.drive-mappings') }}"
                       class="flex items-center px-3.5 py-2 rounded-xl font-medium transition {{ request()->routeIs('admin.drive-mappings') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">
                        Google Drive Folders
                    </a>
                @endif

                @if($authUser->hasPermission('system.settings.manage') || $authUser->hasPermission('notifications.manage'))
                    <a href="{{ route('admin.settings') }}"
                       class="flex items-center px-3.5 py-2 rounded-xl font-medium transition {{ request()->routeIs('admin.settings') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">
                        Reminders & Settings
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
                <div class="text-xs text-slate-500 mt-0.5">{{ $authUser->primaryRole?->name ?? 'Staff' }}</div>
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
        <header class="bg-white border-b border-slate-200 px-6 py-4 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">@yield('heading', 'Home')</h1>
                @hasSection('subheading')
                    <p class="text-sm text-slate-500 mt-0.5">@yield('subheading')</p>
                @endif
            </div>
            <div class="flex items-center gap-2.5 text-sm">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700">
                    {{ $authUser?->primaryRole?->name ?? 'School Staff' }}
                </span>
            </div>
        </header>

        <main class="flex-1 p-6 max-w-6xl w-full mx-auto space-y-6">
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
</body>
</html>
