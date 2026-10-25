@extends('layouts.app')

@section('title', 'Role & Scope Mapping Master')
@section('heading', 'Role, Permission & Scope Mapping Master')
@section('subheading', 'Manage Coordinator/Staff wing & class mappings, school roles, and role permissions.')

@section('content')
    @php
        $activeTab = $tab ?? request('tab', 'scopes');
        $wingLabels = [
            'PRE_PRIMARY' => 'Pre-Primary Wing (PG, Nursery, KG)',
            'PRIMARY' => 'Primary Wing (Classes I – V)',
            'MIDDLE' => 'Middle Wing (Classes VI – VIII)',
            'SECONDARY' => 'Secondary Wing (Classes IX – X)',
            'SENIOR_SECONDARY' => 'Senior Secondary Wing (Classes XI – XII)',
        ];
        $classMap = $classes->keyBy('id');
        $sectionMap = $sections->keyBy('id');
        $subjectMap = $subjects->keyBy('id');
    @endphp

    <!-- Clean 2-Tab Bar -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1">
        <a href="{{ route('admin.roles', ['tab' => 'scopes']) }}"
           class="px-4 py-2.5 rounded-xl text-sm font-semibold border whitespace-nowrap transition {{ $activeTab === 'scopes' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">
            1. Staff Wing & Class Mapping ({{ $scopeAssignments->count() }})
        </a>
        <a href="{{ route('admin.roles', ['tab' => 'roles']) }}"
           class="px-4 py-2.5 rounded-xl text-sm font-semibold border whitespace-nowrap transition {{ $activeTab === 'roles' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">
            2. Roles & Permission Matrix ({{ $roles->count() }})
        </a>
    </div>

    @if($activeTab === 'scopes')
        <!-- TAB 1: Coordinator & Staff Scope Mapping CRUD -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 h-fit">
                <h2 class="text-base font-bold text-slate-900">Map Staff to Wing / Class / Subject</h2>
                <p class="text-sm text-slate-500 mt-0.5">Grant Coordinator, Headmistress, or Viewer access to specific wings or classes.</p>

                <form method="POST" action="{{ route('admin.scopes.store') }}" class="mt-4 space-y-3.5 text-sm">
                    @csrf
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Staff Member *</label>
                        <select name="user_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->full_name }} ({{ $u->primaryRole?->name }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Session</label>
                            <select name="academic_year_id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                                <option value="">All Sessions</option>
                                @foreach($academicYears as $yr)
                                    <option value="{{ $yr->id }}" @selected($yr->is_current)>{{ $yr->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Mapping Type *</label>
                            <select name="scope_type" required
                                    onchange="['STAGE','CLASS','SECTION','SUBJECT'].forEach(t=>document.getElementById('picker_'+t).classList.toggle('hidden',this.value!==t));"
                                    class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                                <option value="STAGE">School Wing</option>
                                <option value="CLASS">Specific Class</option>
                                <option value="SECTION">Specific Section</option>
                                <option value="SUBJECT">Specific Subject</option>
                                <option value="SCHOOL">Whole School</option>
                            </select>
                        </div>
                    </div>

                    <div id="picker_STAGE">
                        <label class="block font-medium text-slate-700 mb-1">Choose School Wing</label>
                        <select name="scope_value" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                            @foreach($wingLabels as $val => $lbl)
                                <option value="{{ $val }}">{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div id="picker_CLASS" class="hidden">
                        <label class="block font-medium text-slate-700 mb-1">Choose Class</label>
                        <select name="class_scope_id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                            @foreach($classes as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div id="picker_SECTION" class="hidden">
                        <label class="block font-medium text-slate-700 mb-1">Choose Class & Section</label>
                        <select name="section_scope_id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                            @foreach($sections as $sec)
                                <option value="{{ $sec->id }}">{{ $sec->schoolClass?->name }} — Section {{ $sec->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div id="picker_SUBJECT" class="hidden">
                        <label class="block font-medium text-slate-700 mb-1">Choose Subject</label>
                        <select name="subject_scope_id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-white">
                            @foreach($subjects as $sub)
                                <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-2 pt-1 text-xs text-slate-700">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="can_review" value="1" checked>
                            <span>Can check, approve, or return teacher files</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="can_access_restricted" value="1">
                            <span>Can open confidential exam & answer files in this area</span>
                        </label>
                    </div>

                    <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                        Save Scope Mapping
                    </button>
                </form>
            </div>

            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="px-5 sm:px-6 py-4 border-b border-slate-100">
                    <h2 class="text-base font-bold text-slate-900">Active & Historical Scope Mappings</h2>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($scopeAssignments as $sa)
                        @php
                            $friendlyTarget = match($sa->scope_type) {
                                'STAGE' => $wingLabels[$sa->scope_value] ?? $sa->scope_value,
                                'CLASS' => $classMap[$sa->scope_id]?->name ?? ('Class #' . $sa->scope_id),
                                'SECTION' => ($sectionMap[$sa->scope_id]?->schoolClass?->name ?? '') . ' — Section ' . ($sectionMap[$sa->scope_id]?->name ?? $sa->scope_id),
                                'SUBJECT' => $subjectMap[$sa->scope_id]?->name ?? ('Subject #' . $sa->scope_id),
                                'SCHOOL' => 'Entire School',
                                default => $sa->scope_type,
                            };
                        @endphp
                        <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="space-y-0.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-bold text-slate-900">{{ $sa->user?->full_name }}</span>
                                    <span class="text-xs text-slate-500">({{ $sa->user?->primaryRole?->name }})</span>
                                    @if(!$sa->is_active)
                                        <span class="px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 text-xs">Inactive</span>
                                    @endif
                                </div>
                                <div class="text-sm text-indigo-700 font-medium">
                                    {{ $friendlyTarget }}
                                    <span class="text-xs text-slate-400 font-normal">· {{ $sa->academicYear?->name ?? 'All Sessions' }}</span>
                                </div>
                                <div class="flex flex-wrap gap-1.5 pt-0.5">
                                    @if($sa->can_review)
                                        <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-medium">Checks Files</span>
                                    @endif
                                    @if($sa->can_access_restricted)
                                        <span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 text-xs font-medium">Confidential Exam Access</span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                <details class="text-left">
                                    <summary class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold cursor-pointer inline-block">
                                        Edit
                                    </summary>
                                    <form method="POST" action="{{ route('admin.scopes.update', $sa) }}" class="mt-2 p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2 w-56 text-xs">
                                        @csrf
                                        @method('PATCH')
                                        <label class="flex items-center gap-1.5"><input type="checkbox" name="can_review" value="1" @checked($sa->can_review)><span>Can Review Files</span></label>
                                        <label class="flex items-center gap-1.5"><input type="checkbox" name="can_access_restricted" value="1" @checked($sa->can_access_restricted)><span>Confidential Files</span></label>
                                        <label class="flex items-center gap-1.5"><input type="checkbox" name="is_active" value="1" @checked($sa->is_active)><span>Active</span></label>
                                        <button type="submit" class="w-full py-1.5 rounded-lg bg-indigo-600 text-white font-semibold cursor-pointer">Save</button>
                                    </form>
                                </details>

                                @if($sa->is_active)
                                    <form method="POST" action="{{ route('admin.scopes.revoke', $sa) }}">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 text-xs font-semibold cursor-pointer">
                                            Deactivate
                                        </button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('admin.scopes.destroy', $sa) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-semibold cursor-pointer">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <!-- TAB 2: Full Role Master CRUD & Permission Matrix -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @if(auth()->user()->hasPermission('roles.manage'))
                <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 h-fit">
                    <h2 class="text-base font-bold text-slate-900">Create Custom School Role</h2>
                    <p class="text-sm text-slate-500 mt-0.5">Add additional school roles (e.g. Exam Cell, HOD Science).</p>

                    <form method="POST" action="{{ route('admin.roles.store') }}" class="mt-4 space-y-3.5 text-sm">
                        @csrf
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Role Display Name *</label>
                            <input type="text" name="name" required placeholder="e.g. Examination Cell"
                                   class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Role Code *</label>
                            <input type="text" name="key" required placeholder="e.g. EXAM_CELL"
                                   class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 uppercase">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Description</label>
                            <input type="text" name="description" placeholder="Manages examination papers and schedules"
                                   class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5">
                        </div>
                        <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                            Create Role
                        </button>
                    </form>
                </div>
            @endif

            <div class="{{ auth()->user()->hasPermission('roles.manage') ? 'lg:col-span-2' : 'lg:col-span-3' }} space-y-4">
                @foreach($roles as $role)
                    @php $assignedIds = $role->permissions->pluck('id')->all(); @endphp
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-base font-bold text-slate-900">{{ $role->name }}</span>
                                    <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-xs font-mono">{{ $role->key }}</span>
                                    @if($role->is_system_role)
                                        <span class="px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 text-xs font-semibold">Core Role</span>
                                    @endif
                                    <span class="text-xs text-slate-500">· {{ $role->users_count ?? 0 }} user(s)</span>
                                </div>
                                @if($role->description)
                                    <p class="text-xs text-slate-500 mt-0.5">{{ $role->description }}</p>
                                @endif
                            </div>

                            @if(auth()->user()->hasPermission('roles.manage'))
                                <div class="flex items-center gap-2 shrink-0">
                                    <details class="text-left">
                                        <summary class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold cursor-pointer inline-block">
                                            Edit Role
                                        </summary>
                                        <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="mt-2 p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2 w-64 text-xs">
                                            @csrf
                                            @method('PATCH')
                                            <input type="text" name="name" value="{{ $role->name }}" required class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                            <input type="text" name="description" value="{{ $role->description }}" placeholder="Description" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                            @if(!$role->is_system_role)
                                                <label class="flex items-center gap-1.5"><input type="checkbox" name="is_active" value="1" @checked($role->is_active)><span>Active</span></label>
                                            @endif
                                            <button type="submit" class="w-full py-1.5 rounded-lg bg-indigo-600 text-white font-semibold cursor-pointer">Save Role</button>
                                        </form>
                                    </details>

                                    @if(!$role->is_system_role && ($role->users_count ?? 0) === 0)
                                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-semibold cursor-pointer">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <details class="rounded-xl border border-slate-100 bg-slate-50/70 p-3.5">
                            <summary class="text-xs font-semibold text-indigo-700 cursor-pointer flex items-center justify-between">
                                <span>Configure Permissions ({{ count($assignedIds) }} enabled)</span>
                                <span>Show / Hide</span>
                            </summary>
                            <form method="POST" action="{{ route('admin.roles.permissions', $role) }}" class="mt-4 space-y-4">
                                @csrf
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                                    @foreach($permissionsByModule as $module => $perms)
                                        <div class="rounded-xl border border-slate-200 bg-white p-3">
                                            <div class="font-bold text-slate-700 uppercase mb-2">{{ $module }}</div>
                                            <div class="space-y-1.5">
                                                @foreach($perms as $perm)
                                                    <label class="flex items-start gap-2">
                                                        <input type="checkbox" name="permission_ids[]" value="{{ $perm->id }}" @checked(in_array($perm->id, $assignedIds, true)) class="mt-0.5">
                                                        <span>{{ $perm->name }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @if(auth()->user()->hasPermission('permissions.manage'))
                                    <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-semibold cursor-pointer">
                                        Save {{ $role->name }} Permissions
                                    </button>
                                @endif
                            </form>
                        </details>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection
