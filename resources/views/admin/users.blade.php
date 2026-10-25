@extends('layouts.app')

@section('title', 'User & Staff Master')
@section('heading', 'User & Staff Master')
@section('subheading', 'Create, edit, reset passwords, assign roles, or deactivate staff accounts.')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Create Staff Member Form -->
        @if(auth()->user()->hasPermission('users.create'))
            <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 h-fit">
                <h2 class="text-base font-bold text-slate-900">Add Staff Member</h2>
                <p class="text-sm text-slate-500 mt-0.5">Staff can sign in via Google Workspace or password.</p>

                <form method="POST" action="{{ route('admin.users.store') }}" class="mt-4 space-y-3.5 text-sm">
                    @csrf
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Full Name *</label>
                        <input type="text" name="full_name" required placeholder="e.g. Meera Sharma"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">School Email *</label>
                        <input type="email" name="email" required placeholder="name@excelliaschool.edu.in"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Primary Role *</label>
                            <select name="primary_role_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">WhatsApp / Mobile</label>
                            <input type="text" name="phone" placeholder="919876543210"
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Employee Code</label>
                            <input type="text" name="employee_code" placeholder="EXC-TCH-110"
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Initial Password</label>
                            <input type="password" name="password" placeholder="Default: Excellia@2026"
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
                        </div>
                    </div>
                    <label class="inline-flex items-center gap-2 pt-1 text-xs text-slate-600">
                        <input type="checkbox" name="can_access_restricted_files" value="1">
                        <span>Allow viewing confidential exam / student answer files</span>
                    </label>
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                        Create Staff Account
                    </button>
                </form>
            </div>
        @endif

        <!-- Staff Directory -->
        <div class="{{ auth()->user()->hasPermission('users.create') ? 'lg:col-span-2' : 'lg:col-span-3' }} bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="p-4 sm:px-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <h2 class="text-base font-bold text-slate-900">Registered Staff ({{ $users->total() }})</h2>
                <form method="GET" action="{{ route('admin.users') }}" class="flex flex-wrap items-center gap-2 text-sm">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, email, code..."
                           class="flex-1 min-w-40 rounded-xl border border-slate-300 px-3 py-1.5">
                    <select name="role_id" class="rounded-xl border border-slate-300 px-3 py-1.5 bg-white">
                        <option value="">All Roles</option>
                        @foreach($roles as $r)
                            <option value="{{ $r->id }}" @selected(request('role_id') == $r->id)>{{ $r->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-slate-900 text-white font-semibold cursor-pointer">Search</button>
                </form>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach($users as $u)
                    <div class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                        <div class="space-y-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-base font-bold text-slate-900">{{ $u->full_name }}</span>
                                <span class="px-2.5 py-0.5 rounded-full {{ $u->primaryRole?->key === 'SUPER_ADMIN' ? 'bg-slate-900 text-white' : 'bg-indigo-50 text-indigo-700' }} text-xs font-semibold">
                                    {{ $u->primaryRole?->name }}
                                </span>
                                @if(!$u->is_active)
                                    <span class="px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-700 text-xs font-semibold">Inactive</span>
                                @endif
                                @if($u->needs_reassignment_review && !$u->is_active)
                                    <span class="px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-900 text-xs font-semibold">Classes Need New Teacher</span>
                                @endif
                            </div>
                            <div class="text-sm text-slate-600 break-words">
                                {{ $u->email }}
                                @if($u->phone) · {{ $u->phone }} @endif
                                @if($u->employee_code) · {{ $u->employee_code }} @endif
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            @if(auth()->user()->hasPermission('users.update'))
                                <details class="text-left">
                                    <summary class="px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold cursor-pointer inline-block">
                                        Edit
                                    </summary>
                                    <form method="POST" action="{{ route('admin.users.update', $u) }}" class="mt-2 p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2.5 w-72 max-w-[82vw] text-xs">
                                        @csrf
                                        @method('PATCH')
                                        <input type="text" name="full_name" value="{{ $u->full_name }}" required class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5" placeholder="Full Name">
                                        <input type="email" name="email" value="{{ $u->email }}" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5" placeholder="School Email">
                                        <div class="grid grid-cols-2 gap-1.5">
                                            <input type="text" name="employee_code" value="{{ $u->employee_code }}" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5" placeholder="Staff Code">
                                            <input type="text" name="phone" value="{{ $u->phone }}" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5" placeholder="WhatsApp">
                                        </div>
                                        <select name="primary_role_id" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                            @foreach($roles as $role)
                                                <option value="{{ $role->id }}" @selected($u->primary_role_id === $role->id)>{{ $role->name }}</option>
                                            @endforeach
                                        </select>
                                        <input type="password" name="password" placeholder="New password (leave blank to keep)" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                        <label class="flex items-center gap-1.5">
                                            <input type="checkbox" name="can_access_restricted_files" value="1" @checked($u->can_access_restricted_files)>
                                            <span>Confidential file access</span>
                                        </label>
                                        @if(!$u->is_active)
                                            <label class="flex items-center gap-1.5 text-emerald-700 font-semibold">
                                                <input type="checkbox" name="is_active" value="1">
                                                <span>Reactivate staff account</span>
                                            </label>
                                        @endif
                                        <button type="submit" class="w-full py-1.5 rounded-lg bg-indigo-600 text-white font-semibold cursor-pointer">Save Changes</button>
                                    </form>
                                </details>
                            @endif

                            @if($u->is_active && $u->id !== auth()->id() && auth()->user()->hasPermission('users.deactivate'))
                                <form method="POST" action="{{ route('admin.users.deactivate', $u) }}">
                                    @csrf
                                    <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-semibold cursor-pointer">
                                        Deactivate
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="px-5 py-3 border-t border-slate-100">{{ $users->links() }}</div>
        </div>
    </div>
@endsection
