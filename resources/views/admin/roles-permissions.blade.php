@extends('layouts.app')

@section('title', 'Roles, Permissions & Scopes')
@section('heading', 'Roles, Permission Matrix & User Scope Assignments')
@section('subheading', 'Database-backed role_permissions and user_scope_assignments enforced across all backend endpoints & files')

@section('content')
    <!-- User Scope Assignment Builder -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-900">Assign Academic Scope to User</h2>
            <p class="text-xs text-slate-500 mt-0.5">Grant Coordinator, Headmistress, or Viewer access to specific bands, classes, sections, or subjects.</p>

            <form method="POST" action="{{ route('admin.scopes.store') }}" class="mt-4 space-y-3 text-xs">
                @csrf
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">User *</label>
                    <select name="user_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->full_name }} ({{ $u->primaryRole?->key }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Academic Year</label>
                        <select name="academic_year_id" class="w-full rounded-lg border border-slate-300 px-2.5 py-2">
                            <option value="">All Years</option>
                            @foreach($academicYears as $yr)
                                <option value="{{ $yr->id }}" @selected($yr->is_current)>{{ $yr->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Scope Type *</label>
                        <select name="scope_type" required class="w-full rounded-lg border border-slate-300 px-2.5 py-2">
                            <option value="STAGE">STAGE (Academic Band)</option>
                            <option value="CLASS">CLASS</option>
                            <option value="SECTION">SECTION</option>
                            <option value="SUBJECT">SUBJECT</option>
                            <option value="SCHOOL">SCHOOL (Full School)</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Stage Band (if STAGE)</label>
                        <select name="scope_value" class="w-full rounded-lg border border-slate-300 px-2.5 py-2">
                            <option value="">—</option>
                            <option value="PRE_PRIMARY">PRE_PRIMARY</option>
                            <option value="PRIMARY">PRIMARY</option>
                            <option value="MIDDLE">MIDDLE</option>
                            <option value="SECONDARY">SECONDARY</option>
                            <option value="SENIOR_SECONDARY">SENIOR_SECONDARY</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Record ID (if CLASS/SEC/SUBJ)</label>
                        <input type="number" name="scope_id" placeholder="Class/Section/Subject ID" class="w-full rounded-lg border border-slate-300 px-2.5 py-2">
                    </div>
                </div>
                <div class="space-y-1.5 pt-1">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="can_review" value="1">
                        <span>Authorise review/approve/return decisions within this scope</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="can_access_restricted" value="1">
                        <span>Authorise restricted file access within this scope</span>
                    </label>
                </div>
                <button type="submit" class="w-full py-2 rounded-lg bg-indigo-600 text-white font-semibold cursor-pointer">Save Scope Assignment</button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="text-sm font-semibold text-slate-900">Active User Scope Assignments</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase">
                        <tr>
                            <th class="px-4 py-2.5 text-left">User & Role</th>
                            <th class="px-4 py-2.5 text-left">Scope</th>
                            <th class="px-4 py-2.5 text-left">Flags</th>
                            <th class="px-4 py-2.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($scopeAssignments as $sa)
                            <tr>
                                <td class="px-4 py-2.5">
                                    <span class="font-semibold text-slate-900">{{ $sa->user?->full_name }}</span>
                                    <span class="text-slate-500">({{ $sa->user?->primaryRole?->key }})</span>
                                </td>
                                <td class="px-4 py-2.5 font-mono">
                                    {{ $sa->scope_type }}: {{ $sa->scope_value ?? $sa->scope_id ?? 'SCHOOL' }}
                                    ({{ $sa->academicYear?->name ?? 'All' }})
                                </td>
                                <td class="px-4 py-2.5">
                                    @if($sa->can_review)
                                        <span class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 font-semibold">Can Review</span>
                                    @endif
                                    @if($sa->can_access_restricted)
                                        <span class="px-1.5 py-0.5 rounded bg-rose-50 text-rose-700 font-semibold">Restricted Files</span>
                                    @endif
                                    @if(!$sa->is_active)
                                        <span class="px-1.5 py-0.5 rounded bg-slate-200 text-slate-600">Revoked</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    @if($sa->is_active)
                                        <form method="POST" action="{{ route('admin.scopes.revoke', $sa) }}">
                                            @csrf
                                            <button type="submit" class="text-rose-600 hover:underline font-semibold cursor-pointer">Revoke</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Role Permissions Matrix -->
    <div class="space-y-4">
        @foreach($roles as $role)
            @php $assignedIds = $role->permissions->pluck('id')->all(); @endphp
            <form method="POST" action="{{ route('admin.roles.permissions', $role) }}" class="bg-white rounded-xl border border-slate-200 p-5">
                @csrf
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                    <div>
                        <span class="text-sm font-bold text-slate-900">{{ $role->name }}</span>
                        <code class="ml-2 text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-700">{{ $role->key }}</code>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $role->description }}</p>
                    </div>
                    @if(auth()->user()->hasPermission('permissions.manage'))
                        <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-slate-900 text-white text-xs font-semibold cursor-pointer">
                            Save {{ $role->key }} Permissions
                        </button>
                    @endif
                </div>

                <div class="mt-3 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 text-xs">
                    @foreach($permissionsByModule->flatten() as $perm)
                        <label class="inline-flex items-center gap-1.5 text-slate-700">
                            <input type="checkbox" name="permission_ids[]" value="{{ $perm->id }}" @checked(in_array($perm->id, $assignedIds, true))>
                            <span class="truncate" title="{{ $perm->key }}">{{ $perm->key }}</span>
                        </label>
                    @endforeach
                </div>
            </form>
        @endforeach
    </div>
@endsection
