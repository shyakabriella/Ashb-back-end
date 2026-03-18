<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    /**
     * Save many tasks for one property.
     * Matches your frontend payload:
     * {
     *   propertyId: "1",
     *   tasks: [
     *     {
     *       taskName: "",
     *       description: "",
     *       milestone: "",
     *       startAt: "",
     *       endAt: "",
     *       assigneeIds: [],
     *       status: "pending"
     *     }
     *   ]
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'propertyId' => ['required', 'exists:properties,id'],
            'tasks' => ['required', 'array', 'min:1'],

            'tasks.*.taskName' => ['required', 'string', 'max:255'],
            'tasks.*.description' => ['nullable', 'string'],
            'tasks.*.milestone' => ['nullable', 'string', 'max:255'],
            'tasks.*.startAt' => ['required', 'date'],
            'tasks.*.endAt' => ['required', 'date'],
            'tasks.*.status' => ['required', Rule::in(['pending', 'in_progress', 'completed'])],

            'tasks.*.assigneeIds' => ['nullable', 'array'],
            'tasks.*.assigneeIds.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        foreach ($validated['tasks'] as $index => $taskData) {
            $startAt = Carbon::parse($taskData['startAt']);
            $endAt = Carbon::parse($taskData['endAt']);

            if ($endAt->lt($startAt)) {
                throw ValidationException::withMessages([
                    "tasks.$index.endAt" => 'End date and time must be after start date and time.',
                ]);
            }
        }

        $createdTasks = DB::transaction(function () use ($validated, $request) {
            $saved = [];

            foreach ($validated['tasks'] as $taskData) {
                $task = Task::create([
                    'property_id' => $validated['propertyId'],
                    'title' => $taskData['taskName'],
                    'description' => $taskData['description'] ?? null,
                    'milestone' => $taskData['milestone'] ?? null,
                    'start_at' => $taskData['startAt'],
                    'end_at' => $taskData['endAt'],
                    'status' => $taskData['status'],
                    'created_by' => $request->user()?->id,
                ]);

                $assigneeIds = collect($taskData['assigneeIds'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                if (!empty($assigneeIds)) {
                    $attachData = [];

                    foreach ($assigneeIds as $userId) {
                        $attachData[$userId] = [
                            'assigned_by' => $request->user()?->id,
                            'assigned_at' => now(),
                        ];
                    }

                    $task->workers()->attach($attachData);

                    $workers = User::whereIn('id', $assigneeIds)->get();

                    foreach ($workers as $worker) {
                        $worker->notify(new TaskAssignedNotification($task));
                    }
                }

                $saved[] = $task->load(['property', 'workers', 'creator']);
            }

            return $saved;
        });

        return response()->json([
            'success' => true,
            'message' => 'Tasks created successfully.',
            'data' => $createdTasks,
        ], 201);
    }

    /**
     * Assign more workers to an existing task.
     * This adds new workers without removing old ones.
     */
    public function assignWorkers(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'assigneeIds' => ['required', 'array', 'min:1'],
            'assigneeIds.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        $currentWorkerIds = $task->workers()
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $newWorkerIds = collect($validated['assigneeIds'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn ($id) => in_array($id, $currentWorkerIds, true))
            ->values()
            ->all();

        if (!empty($newWorkerIds)) {
            $attachData = [];

            foreach ($newWorkerIds as $userId) {
                $attachData[$userId] = [
                    'assigned_by' => $request->user()?->id,
                    'assigned_at' => now(),
                ];
            }

            $task->workers()->attach($attachData);

            $workers = User::whereIn('id', $newWorkerIds)->get();

            foreach ($workers as $worker) {
                $worker->notify(new TaskAssignedNotification($task));
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Workers assigned successfully.',
            'data' => $task->load(['property', 'workers', 'creator']),
        ]);
    }

    /**
     * Worker task box.
     * Logged in worker sees tasks assigned to him/her.
     */
    public function myTasks(Request $request): JsonResponse
    {
        $tasks = Task::with(['property', 'creator', 'workers'])
            ->whereHas('workers', function ($query) use ($request) {
                $query->where('users.id', $request->user()->id);
            })
            ->latest('start_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'My assigned tasks fetched successfully.',
            'data' => $tasks,
        ]);
    }
}