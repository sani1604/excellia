@extends('layouts.app')

@section('title', 'Staff Access & Wings')
@section('heading', 'Coordinator Wings & Staff Access')
@section('subheading', 'Assign which school wing, class, or subject each Coordinator or Headmistress supervises.')

@section('content')
    @php
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Friendly Dropdown-Only Access Form (Zero raw database IDs!) -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h2 class="text-base font-bold text-slate-900">Assign Supervision Area</h2>
            <p class="text-sm text-slate-500 mt-0.5">Choose a staff member and select their wing, class, or subject.</p>

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

                <div class="grid grid-cols-2 gap-3">
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
                        <label class="block font-medium text-slate-700 mb-1">Supervises By *</label>
                        <select id="scope_type_picker" name="scope_type" required
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

                <!-- Dynamic Human-Friendly Pickers -->
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
                        <span>Can check, approve, or send back teacher files</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="can_access_restricted" value="1">
                        <span>Can open confidential exam & answer files in this area</span>
                    </label>
                </div>

                <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                    Save Access
                </button>
            </form>
        </div>

        <!-- Assigned Wings & Classes List -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-base font-bold text-slate-900">Current Supervision Assignments</h2>
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
                                    <span class="px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 text-xs">Removed</span>
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

                        @if($sa->is_active)
                            <form method="POST" action="{{ route('admin.scopes.revoke', $sa) }}" class="shrink-0">
                                @csrf
                                <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-semibold cursor-pointer">
                                    Remove
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Advanced Role Permissions Tucked Inside Collapsible Section -->
    <details class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <summary class="px-6 py-4 font-bold text-base text-slate-900 cursor-pointer flex items-center justify-between hover:bg-slate-50">
            <span>Advanced Role Permissions ({{ $roles->count() }} School Roles)</span>
            <span class="text-xs font-medium text-indigo-600">Click to view or adjust</span>
        </summary>
        <div class="p-6 border-t border-slate-100 space-y-4">
            @foreach($roles as $role)
                @php $assignedIds = $role->permissions->pluck('id')->all(); @endphp
                <details class="rounded-xl border border-slate-200 p-4">
                    <summary class="font-bold text-sm text-slate-900 cursor-pointer flex items-center justify-between">
                        <span>{{ $role->name }}</span>
                        <span class="text-xs font-normal text-slate-500">{{ count($assignedIds) }} capabilities enabled</span>
                    </summary>
                    <form method="POST" action="{{ route('admin.roles.permissions', $role) }}" class="mt-4 space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                            @foreach($permissionsByModule as $module => $perms)
                                <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
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
            @endforeach
        </div>
    </details>
@endsection
