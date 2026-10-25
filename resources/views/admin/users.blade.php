@extends('layouts.app')

@section('title', 'User Master')
@section('heading', 'User Master')
@section('subheading', 'Create, update, assign primary roles & restricted-file permissions, and safely deactivate accounts while preserving history')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Create User Form -->
        @if(auth()->user()->hasPermission('users.create'))
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="text-sm font-semibold text-slate-900">Create Portal User</h2>
                <form method="POST" action="{{ route('admin.users.store') }}" class="mt-4 space-y-3 text-xs">
                    @csrf
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Employee Code</label>
                        <input type="text" name="employee_code" placeholder="EXC-TCH-110" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Full Name *</label>
                        <input type="text" name="full_name" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">School Email *</label>
                        <input type="email" name="email" required placeholder="teacher@excelliaschool.edu.in" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Phone (WhatsApp)</label>
                            <input type="text" name="phone" placeholder="+91 9876543210" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Primary Role *</label>
                            <select name="primary_role_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Initial Password</label>
                        <input type="password" name="password" placeholder="Defaults to Excellia@2026 if blank" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <label class="inline-flex items-center gap-2 pt-1">
                        <input type="checkbox" name="can_access_restricted_files" value="1">
                        <span class="text-slate-700">Explicitly grant restricted file access (audited)</span>
                    </label>
                    <button type="submit" class="w-full py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                        Create User
                    </button>
                </form>
            </div>
        @endif

        <!-- Users List -->
        <div class="{{ auth()->user()->hasPermission('users.create') ? 'lg:col-span-2' : 'lg:col-span-3' }} bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-slate-900">Registered Users</h2>
                <form method="GET" action="{{ route('admin.users') }}" class="flex items-center gap-2 text-xs">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name/email/code..."
                           class="rounded-lg border border-slate-300 px-3 py-1.5">
                    <select name="role_id" class="rounded-lg border border-slate-300 px-2.5 py-1.5">
                        <option value="">All Roles</option>
                        @foreach($roles as $r)
                            <option value="{{ $r->id }}" @selected(request('role_id') == $r->id)>{{ $r->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-slate-900 text-white font-semibold cursor-pointer">Filter</button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left">User</th>
                            <th class="px-4 py-3 text-left">Role & Scope</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right">Edit / Deactivate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($users as $u)
                            <tr>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-semibold text-slate-900">{{ $u->full_name }}</div>
                                    <div class="text-slate-500">{{ $u->email }} · <span class="font-mono">{{ $u->employee_code ?? '—' }}</span></div>
                                    @if($u->phone)
                                        <div class="text-[11px] text-slate-400 mt-0.5">Phone: {{ $u->phone }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 font-semibold">{{ $u->primaryRole?->key }}</span>
                                    @if($u->can_access_restricted_files)
                                        <span class="ml-1 px-1.5 py-0.5 rounded bg-rose-50 text-rose-700 font-bold text-[10px]">Restricted Files Granted</span>
                                    @endif
                                    @if($u->activeScopeAssignments->isNotEmpty())
                                        <div class="text-[11px] text-slate-500 mt-1">
                                            Scopes: {{ $u->activeScopeAssignments->map(fn($s) => $s->scope_type . ':' . ($s->scope_value ?? $s->scope_id ?? 'ALL'))->implode(', ') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top">
                                    @if($u->is_active)
                                        <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-semibold">Active</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded bg-slate-200 text-slate-600 font-semibold">Inactive</span>
                                        @if($u->needs_reassignment_review)
                                            <div class="text-[10px] text-amber-700 font-bold mt-0.5">Allocations Flagged</div>
                                        @endif
                                    @endif
                                    <div class="text-[10px] text-slate-400 mt-0.5">Provider: {{ $u->login_provider }}</div>
                                </td>
                                <td class="px-4 py-3 text-right align-top space-y-2">
                                    @if(auth()->user()->hasPermission('users.update'))
                                        <details class="inline-block text-left">
                                            <summary class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold cursor-pointer inline-block">
                                                Edit
                                            </summary>
                                            <form method="POST" action="{{ route('admin.users.update', $u) }}" class="mt-2 p-3 rounded-lg bg-slate-50 border border-slate-200 space-y-2 w-64">
                                                @csrf
                                                @method('PATCH')
                                                <input type="text" name="full_name" value="{{ $u->full_name }}" required class="w-full rounded border border-slate-300 px-2 py-1" placeholder="Full Name">
                                                <input type="text" name="employee_code" value="{{ $u->employee_code }}" class="w-full rounded border border-slate-300 px-2 py-1" placeholder="Employee Code">
                                                <input type="text" name="phone" value="{{ $u->phone }}" class="w-full rounded border border-slate-300 px-2 py-1" placeholder="WhatsApp / Phone">
                                                <select name="primary_role_id" class="w-full rounded border border-slate-300 px-2 py-1">
                                                    @foreach($roles as $role)
                                                        <option value="{{ $role->id }}" @selected($u->primary_role_id === $role->id)>{{ $role->name }}</option>
                                                    @endforeach
                                                </select>
                                                <label class="flex items-center gap-1.5">
                                                    <input type="checkbox" name="can_access_restricted_files" value="1" @checked($u->can_access_restricted_files)>
                                                    <span>Restricted file access</span>
                                                </label>
                                                @if(!$u->is_active)
                                                    <label class="flex items-center gap-1.5 text-emerald-700 font-semibold">
                                                        <input type="checkbox" name="is_active" value="1">
                                                        <span>Reactivate account</span>
                                                    </label>
                                                @endif
                                                <button type="submit" class="w-full py-1 rounded bg-indigo-600 text-white font-semibold cursor-pointer">Save User</button>
                                            </form>
                                        </details>
                                    @endif

                                    @if($u->is_active && $u->id !== auth()->id() && auth()->user()->hasPermission('users.deactivate'))
                                        <form method="POST" action="{{ route('admin.users.deactivate', $u) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 rounded bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold cursor-pointer">
                                                Deactivate
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-slate-100">{{ $users->links() }}</div>
        </div>
    </div>
@endsection
