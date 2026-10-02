<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskRequest;
use App\Models\Task;
use App\Services\LeadTimeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * To-dos: add one (on a lead or on its own), tick it off, or remove it.
 */
class TaskController extends Controller
{
    public function store(TaskRequest $request): RedirectResponse
    {
        $task = new Task(['title' => $request->validated('title'), 'lead_id' => $request->validated('lead_id'), 'user_id' => $request->assigneeId()]);
        $task->due_at = $request->dueAt();
        $task->organization_id = $request->user()->organization_id;
        $task->created_by = $request->user()->id;
        $task->save();

        return back()->with('status', 'To-do added.');
    }

    /** Tick off, or untick. Ticking off a lead's to-do notes it in the lead's history. */
    public function update(Request $request, Task $task, LeadTimeline $timeline): RedirectResponse
    {
        Gate::authorize('update', $task);
        $done = $request->boolean('done');

        $task->forceFill(['done_at' => $done ? now() : null])->save();

        if ($done && $task->lead !== null) {
            $timeline->note($task->lead, "Done: {$task->title}", $request->user());
        }

        return back()->with('status', $done ? 'Nice, that\'s done.' : 'To-do reopened.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);
        $task->delete();

        return back()->with('status', 'To-do removed.');
    }
}
