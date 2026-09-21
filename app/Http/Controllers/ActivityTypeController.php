<?php

namespace App\Http\Controllers;

use App\Models\ActivityType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActivityTypeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        ActivityType::create(['owner_id' => $request->user()->id, ...$data]);

        return to_route('students.index');
    }

    public function update(Request $request, string $activityType): RedirectResponse
    {
        $record = ActivityType::where('owner_id', $request->user()->id)->findOrFail($activityType);
        abort_if($record->archived_at !== null, 409);
        $record->update($request->validate($this->rules()));

        return to_route('students.index');
    }

    public function archive(Request $request, string $activityType): RedirectResponse
    {
        ActivityType::where('owner_id', $request->user()->id)->findOrFail($activityType)->update(['archived_at' => now()]);

        return to_route('students.index');
    }

    private function rules(): array
    {
        return ['name' => ['required', 'string', 'max:160'], 'counts_toward_progress' => ['required', 'boolean']];
    }
}
