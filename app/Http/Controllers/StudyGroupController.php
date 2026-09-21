<?php

namespace App\Http\Controllers;

use App\Models\GroupMembership;
use App\Models\StudyGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudyGroupController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules($request->user()->id));
        DB::transaction(function () use ($request, $data) {
            $group = StudyGroup::create(['owner_id' => $request->user()->id, 'name' => $data['name']]);
            $this->syncMembers($group, $data['student_ids'] ?? []);
        });

        return to_route('students.index');
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        $record = StudyGroup::where('owner_id', $request->user()->id)->findOrFail($group);
        abort_if($record->archived_at !== null, 409);
        $data = $request->validate($this->rules($request->user()->id));
        DB::transaction(function () use ($record, $data) {
            $record->update(['name' => $data['name']]);
            $this->syncMembers($record, $data['student_ids'] ?? []);
        });

        return to_route('students.index');
    }

    public function archive(Request $request, string $group): RedirectResponse
    {
        $record = StudyGroup::where('owner_id', $request->user()->id)->findOrFail($group);
        DB::transaction(function () use ($record) {
            $record->update(['archived_at' => now()]);
            DB::table('group_memberships')->where('group_id', $record->id)->whereNull('left_at')->update(['left_at' => now(), 'active_student_id' => null]);
        });

        return to_route('students.index');
    }

    private function rules(int $ownerId): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'student_ids' => ['sometimes', 'array', 'max:200'],
            'student_ids.*' => ['required', 'ulid', 'distinct', Rule::exists('students', 'id')->where('owner_id', $ownerId)->whereNull('archived_at')],
        ];
    }

    private function syncMembers(StudyGroup $group, array $studentIds): void
    {
        $active = GroupMembership::where('group_id', $group->id)->whereNull('left_at')->get()->keyBy('student_id');
        foreach ($active as $studentId => $membership) {
            if (! in_array($studentId, $studentIds, true)) {
                $membership->update(['left_at' => now(), 'active_student_id' => null]);
            }
        }
        foreach ($studentIds as $studentId) {
            if (! $active->has($studentId)) {
                GroupMembership::create(['group_id' => $group->id, 'student_id' => $studentId, 'active_student_id' => $studentId, 'joined_at' => now()]);
            }
        }
    }
}
