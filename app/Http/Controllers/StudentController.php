<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $ownerId = $request->user()->id;
        $search = trim((string) $request->query('q', ''));
        $status = $request->query('status') === 'archived' ? 'archived' : 'active';
        $students = Student::query()->where('owner_id', $ownerId)
            ->when($status === 'archived', fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at'))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')->paginate(20)->withQueryString();
        $groups = DB::table('study_groups')->where('owner_id', $ownerId)->orderBy('name')->get();
        $memberIds = DB::table('group_memberships')->whereIn('group_id', $groups->pluck('id'))->whereNull('left_at')->get(['group_id', 'student_id'])->groupBy('group_id');
        $groups->each(function ($group) use ($memberIds) {
            $group->student_ids = ($memberIds[$group->id] ?? collect())->pluck('student_id');
        });

        return Inertia::render('Students/Index', [
            'students' => $students,
            'filters' => ['q' => $search, 'status' => $status],
            'groups' => $groups,
            'groupCandidates' => Student::where('owner_id', $ownerId)->whereNull('archived_at')->orderBy('name')->get(['id', 'code', 'name']),
            'activities' => DB::table('activity_types')->where('owner_id', $ownerId)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $ownerId = $request->user()->id;
        $data = $request->validate($this->rules($ownerId));
        Student::create(['owner_id' => $ownerId, ...$data]);

        return to_route('students.index');
    }

    public function update(Request $request, string $student): RedirectResponse
    {
        $ownerId = $request->user()->id;
        $record = Student::where('owner_id', $ownerId)->findOrFail($student);
        abort_if($record->archived_at !== null, 409);
        $data = $request->validate($this->rules($ownerId, $record->id));
        $record->update($data);

        return to_route('students.index');
    }

    public function archive(Request $request, string $student): RedirectResponse
    {
        $record = Student::where('owner_id', $request->user()->id)->findOrFail($student);
        DB::transaction(function () use ($record) {
            $record->update(['archived_at' => now()]);
            DB::table('group_memberships')->where('student_id', $record->id)->whereNull('left_at')->update(['left_at' => now(), 'active_student_id' => null]);
        });

        return to_route('students.index');
    }

    public function restore(Request $request, string $student): RedirectResponse
    {
        Student::where('owner_id', $request->user()->id)->findOrFail($student)->update(['archived_at' => null]);

        return to_route('students.index');
    }

    private function rules(int $ownerId, ?string $studentId = null): array
    {
        $codeRule = Rule::unique('students', 'code')->where('owner_id', $ownerId);
        if ($studentId) {
            $codeRule->ignore($studentId);
        }

        return [
            'code' => ['required', 'string', 'max:40', $codeRule],
            'name' => ['required', 'string', 'max:160'],
            'contact' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
