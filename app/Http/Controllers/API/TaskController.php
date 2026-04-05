<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskUpdate;
use App\Models\TaskUpdateAttachment;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskStatusChangedNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    /**
     * Allowed task statuses.
     */
    private function allowedStatuses(): array
    {
        return [
            'received',
            'start',
            'pending',
            'not_understandable',
            'understandable',
            'completed',
        ];
    }

    /**
     * Roles allowed to manage tasks.
     */
    private function managerRoles(): array
    {
        return [
            'ceo',
            'md',
            'chief_market',
            'admin',
        ];
    }

    /**
     * Roles allowed to be workers.
     */
    private function workerRoles(): array
    {
        return [
            'employee',
            'intern',
        ];
    }

    /**
     * Fixed ranking rules used by grading / reward page.
     */
    private function rewardRankingMap(): array
    {
        return [
            'very_poor' => [
                'label' => 'Very Poor',
                'marks_percentage' => 0,
                'advice_required' => true,
            ],
            'poor' => [
                'label' => 'Poor',
                'marks_percentage' => 0,
                'advice_required' => false,
            ],
            'good' => [
                'label' => 'Good',
                'marks_percentage' => 65,
                'advice_required' => false,
            ],
            'very_good' => [
                'label' => 'Very Good',
                'marks_percentage' => 75,
                'advice_required' => false,
            ],
            'excellent' => [
                'label' => 'Excellent',
                'marks_percentage' => 85,
                'advice_required' => false,
            ],
            'talented' => [
                'label' => 'Talented',
                'marks_percentage' => 100,
                'advice_required' => false,
            ],
        ];
    }

    /**
     * Relations for listing tasks.
     */
    private function taskListRelations(): array
{
    $relations = [
        'property',
        'creator',
        'workers.role',
        'latestUpdate.user',
        'latestUpdate.attachments',
    ];

    if ($this->rewardTableReady()) {
        $relations[] = 'rewards';
        $relations[] = 'latestReward';
    }

    return $relations;
}

    /**
     * Relations for showing one task with full history.
     */
    private function taskShowRelations(): array
{
    $relations = [
        'property',
        'creator',
        'workers.role',
        'latestUpdate.user',
        'latestUpdate.attachments',
        'updates.user',
        'updates.attachments',
    ];

    if ($this->rewardTableReady()) {
        $relations[] = 'rewards';
        $relations[] = 'latestReward';
    }

    return $relations;
}

    /**
     * Worker update validation.
     */
    private function workerUpdateRules(): array
    {
        return [
            'status' => ['required', Rule::in($this->allowedStatuses())],
            'comment' => ['nullable', 'string'],

            'voice_note' => ['nullable', 'file', 'mimes:webm,wav,mp3,ogg,m4a', 'max:20480'],

            'images' => ['nullable', 'array'],
            'images.*' => ['file', 'image', 'max:10240'],

            'videos' => ['nullable', 'array'],
            'videos.*' => ['file', 'mimes:mp4,mov,avi,webm,mkv', 'max:102400'],

            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip', 'max:51200'],
        ];
    }

    /**
     * Manager update validation.
     */
    private function managerUpdateRules(): array
    {
        return [
            'propertyId' => ['sometimes', 'exists:properties,id'],
            'taskName' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'milestone' => ['nullable', 'string', 'max:255'],
            'startAt' => ['sometimes', 'date'],
            'endAt' => ['sometimes', 'date'],
            'status' => ['sometimes', Rule::in($this->allowedStatuses())],

            'comment' => ['nullable', 'string'],

            'voice_note' => ['nullable', 'file', 'mimes:webm,wav,mp3,ogg,m4a', 'max:20480'],

            'images' => ['nullable', 'array'],
            'images.*' => ['file', 'image', 'max:10240'],

            'videos' => ['nullable', 'array'],
            'videos.*' => ['file', 'mimes:mp4,mov,avi,webm,mkv', 'max:102400'],

            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip', 'max:51200'],
        ];
    }

    /**
     * List tasks.
     * - Employee/Intern sees only assigned tasks
     * - CEO/MD/Chief Market/Admin sees all tasks
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $query = Task::with($this->taskListRelations());

        if ($this->isWorker($user)) {
            $query->whereHas('workers', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        } elseif (!$this->canManageTasks($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to view tasks.',
            ], 403);
        }

        $tasks = $query
            ->latest('start_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Tasks fetched successfully.',
            'data' => $tasks,
        ]);
    }

    /**
     * Show one task.
     */
    public function show(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();

        if (!$this->canAccessTask($user, $task)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to view this task.',
            ], 403);
        }

        $task->load($this->taskShowRelations());

        return response()->json([
            'success' => true,
            'message' => 'Task fetched successfully.',
            'data' => $task,
        ]);
    }

    /**
     * Save many tasks for one property.
     * Only CEO / MD / Chief Market / Admin can create tasks.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->canManageTasks($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to create tasks.',
            ], 403);
        }

        $validated = $request->validate([
            'propertyId' => ['required', 'exists:properties,id'],
            'tasks' => ['required', 'array', 'min:1'],

            'tasks.*.taskName' => ['required', 'string', 'max:255'],
            'tasks.*.description' => ['nullable', 'string'],
            'tasks.*.milestone' => ['nullable', 'string', 'max:255'],
            'tasks.*.startAt' => ['required', 'date'],
            'tasks.*.endAt' => ['required', 'date'],
            'tasks.*.status' => ['required', Rule::in($this->allowedStatuses())],

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

            $this->validateAssignableUserIds($taskData['assigneeIds'] ?? [], "tasks.$index.assigneeIds");
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

                    $workers = $this->getAssignableUsers($assigneeIds);

                    foreach ($workers as $worker) {
                        $worker->notify(new TaskAssignedNotification($task));
                    }
                }

                $saved[] = $task->load($this->taskShowRelations());
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
     * Update one task.
     * - Employee/Intern: can update status + comment/files, only assigned task
     * - Managers: can update full task + comment/files
     * - When status changes => notify CEO + MD
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();

        if ($this->isWorker($user)) {
            if (!$this->canAccessTask($user, $task)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not allowed to update this task.',
                ], 403);
            }

            $validated = $request->validate($this->workerUpdateRules());

            $oldStatus = (string) $task->status;
            $newStatus = $oldStatus;

            DB::transaction(function () use ($task, $validated, $request, $user, $oldStatus, &$newStatus) {
                $task->update([
                    'status' => $validated['status'],
                ]);

                $newStatus = (string) $task->status;
                $statusChanged = $oldStatus !== $newStatus;

                if ($this->hasUpdatePayload($request, $validated, $statusChanged)) {
                    $this->createTaskUpdateFromRequest(
                        task: $task,
                        user: $user,
                        request: $request,
                        comment: $validated['comment'] ?? null,
                        statusFrom: $statusChanged ? $oldStatus : $newStatus,
                        statusTo: $newStatus,
                    );
                }
            });

            $task->load($this->taskShowRelations());

            if ($oldStatus !== $newStatus) {
                $this->notifyLeadersTaskStatusChanged($task, $oldStatus, $newStatus, $user);
            }

            return response()->json([
                'success' => true,
                'message' => 'Task status updated successfully.',
                'data' => $task,
            ]);
        }

        if (!$this->canManageTasks($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to update this task.',
            ], 403);
        }

        $validated = $request->validate($this->managerUpdateRules());

        $oldStatus = (string) $task->status;
        $newStatus = $oldStatus;

        $startAt = array_key_exists('startAt', $validated)
            ? Carbon::parse($validated['startAt'])
            : Carbon::parse($task->start_at);

        $endAt = array_key_exists('endAt', $validated)
            ? Carbon::parse($validated['endAt'])
            : Carbon::parse($task->end_at);

        if ($endAt->lt($startAt)) {
            throw ValidationException::withMessages([
                'endAt' => 'End date and time must be after start date and time.',
            ]);
        }

        DB::transaction(function () use ($task, $validated, $request, $user, $startAt, $endAt, $oldStatus, &$newStatus) {
            $task->update([
                'property_id' => $validated['propertyId'] ?? $task->property_id,
                'title' => $validated['taskName'] ?? $task->title,
                'description' => array_key_exists('description', $validated)
                    ? $validated['description']
                    : $task->description,
                'milestone' => array_key_exists('milestone', $validated)
                    ? $validated['milestone']
                    : $task->milestone,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => $validated['status'] ?? $task->status,
            ]);

            $newStatus = (string) $task->status;
            $statusChanged = $oldStatus !== $newStatus;

            if ($this->hasUpdatePayload($request, $validated, $statusChanged)) {
                $this->createTaskUpdateFromRequest(
                    task: $task,
                    user: $user,
                    request: $request,
                    comment: $validated['comment'] ?? null,
                    statusFrom: $statusChanged ? $oldStatus : $newStatus,
                    statusTo: $newStatus,
                );
            }
        });

        $task->load($this->taskShowRelations());

        if ($oldStatus !== $newStatus) {
            $this->notifyLeadersTaskStatusChanged($task, $oldStatus, $newStatus, $user);
        }

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully.',
            'data' => $task,
        ]);
    }

    /**
     * Save reward / grading rows for one or many assigned workers.
     * Each selected employee or intern receives one saved record.
     */
    public function saveReward(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();

        if (!$this->canManageTasks($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to reward or grade this task.',
            ], 403);
        }

        if (!$this->rewardTableReady()) {
            return response()->json([
                'success' => false,
                'message' => 'task_rewards table is missing. Please run the migration first.',
            ], 500);
        }

        if (strtolower((string) $task->status) !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Reward can only be saved after the task is completed.',
            ], 422);
        }

        $validated = $request->validate([
            'ranking' => ['required', 'string', 'max:50'],
            'grading' => ['nullable', 'string', 'max:255'],
            'advice' => ['nullable', 'string'],
            'comment' => ['nullable', 'string'],

            'attachment_id' => ['nullable', 'integer'],
            'evidence_attachment_id' => ['nullable', 'integer'],
            'task_update_id' => ['nullable', 'integer'],

            'worker_id' => ['nullable', 'integer', 'exists:users,id'],
            'worker_ids' => ['nullable', 'array'],
            'worker_ids.*' => ['integer', 'distinct', 'exists:users,id'],

            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],

            'recipient_ids' => ['nullable', 'array'],
            'recipient_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        $normalizedRanking = $this->normalizeRewardRanking((string) $validated['ranking']);

        if (!$normalizedRanking) {
            throw ValidationException::withMessages([
                'ranking' => 'Invalid ranking. Allowed: very_poor, poor, good, very_good, excellent, talented.',
            ]);
        }

        $rankingMeta = $this->rewardRankingMap()[$normalizedRanking];

        if (($rankingMeta['advice_required'] ?? false) && blank($validated['advice'] ?? null)) {
            throw ValidationException::withMessages([
                'advice' => 'Advice is required when ranking is Very Poor.',
            ]);
        }

        $recipientIds = $this->extractRewardRecipientIds($validated);

        if (empty($recipientIds)) {
            throw ValidationException::withMessages([
                'worker_ids' => 'Please select at least one employee or intern to receive reward.',
            ]);
        }

        $this->validateTaskRewardRecipients($task, $recipientIds);

        $attachmentId = (int) ($validated['attachment_id'] ?? $validated['evidence_attachment_id'] ?? 0);

        if ($attachmentId <= 0) {
            throw ValidationException::withMessages([
                'attachment_id' => 'Please select attachment/work evidence to grade.',
            ]);
        }

        $attachmentRow = $this->findAttachmentForTask($task, $attachmentId);

        if (!$attachmentRow) {
            throw ValidationException::withMessages([
                'attachment_id' => 'Selected attachment does not belong to this task.',
            ]);
        }

        $taskUpdateId = (int) ($validated['task_update_id'] ?? $attachmentRow->task_update_id ?? 0);

        if ($taskUpdateId <= 0 || (int) $attachmentRow->task_update_id !== $taskUpdateId) {
            throw ValidationException::withMessages([
                'task_update_id' => 'Selected task update does not match the attachment.',
            ]);
        }

        $savedRows = DB::transaction(function () use (
            $task,
            $user,
            $recipientIds,
            $attachmentRow,
            $taskUpdateId,
            $normalizedRanking,
            $rankingMeta,
            $validated
        ) {
            $now = now();
            $saved = [];

            foreach ($recipientIds as $recipientId) {
                $existing = DB::table('task_rewards')
                    ->where('task_id', $task->id)
                    ->where('recipient_user_id', $recipientId)
                    ->where('task_update_id', $taskUpdateId)
                    ->where('attachment_id', $attachmentRow->id)
                    ->first();

                $payload = [
                    'task_id' => $task->id,
                    'recipient_user_id' => $recipientId,
                    'graded_by_user_id' => $user?->id,
                    'task_update_id' => $taskUpdateId,
                    'attachment_id' => $attachmentRow->id,
                    'attachment_type' => $attachmentRow->attachment_type,
                    'attachment_file_name' => $attachmentRow->file_name,
                    'attachment_file_path' => $attachmentRow->file_path,
                    'ranking' => $normalizedRanking,
                    'ranking_label' => $rankingMeta['label'],
                    'marks_percentage' => (int) $rankingMeta['marks_percentage'],
                    'grading' => filled($validated['grading'] ?? null)
                        ? trim((string) $validated['grading'])
                        : $rankingMeta['label'],
                    'advice' => filled($validated['advice'] ?? null)
                        ? trim((string) $validated['advice'])
                        : null,
                    'comment' => filled($validated['comment'] ?? null)
                        ? trim((string) $validated['comment'])
                        : null,
                    'updated_at' => $now,
                ];

                if ($existing) {
                    DB::table('task_rewards')
                        ->where('id', $existing->id)
                        ->update($payload);

                    $saved[] = DB::table('task_rewards')->where('id', $existing->id)->first();
                } else {
                    $insertPayload = $payload + [
                        'created_at' => $now,
                    ];

                    $newId = DB::table('task_rewards')->insertGetId($insertPayload);
                    $saved[] = DB::table('task_rewards')->where('id', $newId)->first();
                }
            }

            return $saved;
        });

        return response()->json([
            'success' => true,
            'message' => count($savedRows) === 1
                ? 'Reward saved successfully.'
                : 'Rewards saved successfully for selected employees/interns.',
            'data' => [
                'task_id' => $task->id,
                'saved_count' => count($savedRows),
                'ranking' => $normalizedRanking,
                'ranking_label' => $rankingMeta['label'],
                'marks_percentage' => (int) $rankingMeta['marks_percentage'],
                'attachment' => [
                    'id' => (int) $attachmentRow->id,
                    'task_update_id' => (int) $attachmentRow->task_update_id,
                    'type' => $attachmentRow->attachment_type,
                    'file_name' => $attachmentRow->file_name,
                    'file_path' => $attachmentRow->file_path,
                ],
                'rewards' => $savedRows,
            ],
        ], 201);
    }

    /**
     * List saved rewards / gradings for one task.
     */
    public function rewards(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();

        if (!$this->canAccessTask($user, $task)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to view rewards for this task.',
            ], 403);
        }

        if (!$this->rewardTableReady()) {
            return response()->json([
                'success' => true,
                'message' => 'No rewards table yet.',
                'data' => [],
            ]);
        }

        $rewards = DB::table('task_rewards')
            ->leftJoin('users as recipients', 'recipients.id', '=', 'task_rewards.recipient_user_id')
            ->leftJoin('users as graders', 'graders.id', '=', 'task_rewards.graded_by_user_id')
            ->where('task_rewards.task_id', $task->id)
            ->orderByDesc('task_rewards.created_at')
            ->get([
                'task_rewards.*',
                'recipients.name as recipient_name',
                'recipients.email as recipient_email',
                'graders.name as grader_name',
                'graders.email as grader_email',
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Task rewards fetched successfully.',
            'data' => $rewards,
        ]);
    }

    /**
     * Delete one task.
     */
    public function destroy(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();

        if (!$this->canManageTasks($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to delete tasks.',
            ], 403);
        }

        DB::transaction(function () use ($task) {
            $task->load(['updates.attachments']);

            foreach ($task->updates as $update) {
                foreach ($update->attachments as $attachment) {
                    $deletePath = $this->normalizeStoredAttachmentPath(
                        $attachment->file_path,
                        $attachment->disk ?: 'public'
                    );

                    if ($deletePath) {
                        Storage::disk($attachment->disk ?: 'public')->delete($deletePath);
                    }
                }

                $update->attachments()->delete();
            }

            if ($this->rewardTableReady()) {
                DB::table('task_rewards')->where('task_id', $task->id)->delete();
            }

            $task->updates()->delete();
            $task->workers()->detach();
            $task->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully.',
        ]);
    }

    /**
     * Assign more workers to existing task.
     */
    public function assignWorkers(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();

        if (!$this->canManageTasks($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to assign workers.',
            ], 403);
        }

        $validated = $request->validate([
            'assigneeIds' => ['required', 'array', 'min:1'],
            'assigneeIds.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        $this->validateAssignableUserIds($validated['assigneeIds'], 'assigneeIds');

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

            $workers = $this->getAssignableUsers($newWorkerIds);

            foreach ($workers as $worker) {
                $worker->notify(new TaskAssignedNotification($task));
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Workers assigned successfully.',
            'data' => $task->load($this->taskShowRelations()),
        ]);
    }

    /**
     * Replace all workers on existing task.
     */
    public function syncWorkers(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();

        if (!$this->canManageTasks($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to replace workers.',
            ], 403);
        }

        $validated = $request->validate([
            'assigneeIds' => ['required', 'array', 'min:1'],
            'assigneeIds.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        $this->validateAssignableUserIds($validated['assigneeIds'], 'assigneeIds');

        $newWorkerIds = collect($validated['assigneeIds'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $currentWorkerIds = $task->workers()
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::transaction(function () use ($task, $newWorkerIds, $request) {
            $task->workers()->detach();

            $attachData = [];

            foreach ($newWorkerIds as $userId) {
                $attachData[$userId] = [
                    'assigned_by' => $request->user()?->id,
                    'assigned_at' => now(),
                ];
            }

            $task->workers()->attach($attachData);
        });

        $addedWorkerIds = array_values(array_diff($newWorkerIds, $currentWorkerIds));
        $workers = $this->getAssignableUsers($addedWorkerIds);

        foreach ($workers as $worker) {
            $worker->notify(new TaskAssignedNotification($task));
        }

        return response()->json([
            'success' => true,
            'message' => 'Workers replaced successfully.',
            'data' => $task->load($this->taskShowRelations()),
        ]);
    }

    /**
     * Logged-in worker sees only own tasks.
     */
    public function myTasks(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->isWorker($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Only employees and interns can access my tasks.',
            ], 403);
        }

        $tasks = Task::with($this->taskListRelations())
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

    /**
     * Auto-generate report for a selected period.
     * - CEO / MD / Chief Market can see all employee + intern reports
     * - Employee / Intern can see only own report
     *
     * Query params:
     * - from_date=2026-04-01
     * - to_date=2026-04-30
     * - week_start=2026-03-23 (fallback)
     * - user_id=5
     */
    public function weeklyReport(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$this->isWorker($user) && !$this->canViewAllWeeklyReports($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to view weekly reports.',
            ], 403);
        }

        $requestedUserId = $request->filled('user_id')
            ? (int) $request->integer('user_id')
            : null;

        if ($requestedUserId) {
            $this->validateAssignableUserIds([$requestedUserId], 'user_id');
        }

        [$weekStart, $weekEnd] = $this->resolveWeeklyReportRange($request);

        $workers = $this->resolveWeeklyReportSubjects(
            authUser: $user,
            requestedUserId: $requestedUserId
        );

        $reports = $workers
            ->map(fn (User $worker) => $this->buildWeeklyWorkerReport($worker, $weekStart, $weekEnd))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Report generated successfully.',
            'data' => [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'from_date' => $weekStart->toDateString(),
                'to_date' => $weekEnd->toDateString(),
                'generated_at' => now()->toDateTimeString(),
                'can_view_all' => $this->canViewAllWeeklyReports($user),
                'summary' => $this->buildWeeklyReportSummary($reports),
                'reports' => $reports,
            ],
        ]);
    }

    /**
     * Save task update history and attachments.
     */
    private function createTaskUpdateFromRequest(
        Task $task,
        User $user,
        Request $request,
        ?string $comment,
        ?string $statusFrom,
        ?string $statusTo
    ): TaskUpdate {
        $taskUpdate = TaskUpdate::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'status_from' => $statusFrom,
            'status_to' => $statusTo,
            'comment' => filled($comment) ? trim((string) $comment) : null,
        ]);

        if ($request->hasFile('voice_note')) {
            $file = $request->file('voice_note');

            if ($file instanceof UploadedFile) {
                $this->storeAttachmentFile(
                    taskUpdate: $taskUpdate,
                    file: $file,
                    attachmentType: 'voice_note',
                    folder: 'voice-notes'
                );
            }
        }

        foreach ($this->normalizeFiles($request->file('images')) as $file) {
            $this->storeAttachmentFile(
                taskUpdate: $taskUpdate,
                file: $file,
                attachmentType: 'image',
                folder: 'images'
            );
        }

        foreach ($this->normalizeFiles($request->file('videos')) as $file) {
            $this->storeAttachmentFile(
                taskUpdate: $taskUpdate,
                file: $file,
                attachmentType: 'video',
                folder: 'videos'
            );
        }

        foreach ($this->normalizeFiles($request->file('documents')) as $file) {
            $this->storeAttachmentFile(
                taskUpdate: $taskUpdate,
                file: $file,
                attachmentType: 'document',
                folder: 'documents'
            );
        }

        $task->touch();

        return $taskUpdate->load(['user', 'attachments']);
    }

    /**
     * Store one attachment file.
     */
    private function storeAttachmentFile(
        TaskUpdate $taskUpdate,
        UploadedFile $file,
        string $attachmentType,
        string $folder
    ): TaskUpdateAttachment {
        $storedPath = $file->store(
            "task-updates/{$taskUpdate->task_id}/{$folder}",
            'public'
        );

        $normalizedPath = $this->normalizeStoredAttachmentPath($storedPath, 'public');

        return TaskUpdateAttachment::create([
            'task_update_id' => $taskUpdate->id,
            'attachment_type' => $attachmentType,
            'disk' => 'public',
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $normalizedPath,
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);
    }

    /**
     * Normalize public disk paths before saving/deleting.
     */
    private function normalizeStoredAttachmentPath(?string $path, string $disk = 'public'): ?string
    {
        $value = trim((string) $path);

        if ($value === '') {
            return null;
        }

        $value = str_replace('\\', '/', $value);
        $value = ltrim($value, '/');

        if ($disk === 'public') {
            if (str_starts_with($value, 'storage/app/public/')) {
                return substr($value, strlen('storage/app/public/'));
            }

            if (str_starts_with($value, 'public/storage/')) {
                return substr($value, strlen('public/storage/'));
            }

            if (str_starts_with($value, 'storage/')) {
                return substr($value, strlen('storage/'));
            }
        }

        return $value;
    }

    /**
     * Normalize uploaded files.
     */
    private function normalizeFiles(mixed $files): array
    {
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (!is_array($files)) {
            return [];
        }

        return array_values(array_filter($files, fn ($file) => $file instanceof UploadedFile));
    }

    /**
     * Check if request contains update log data.
     */
    private function hasUpdatePayload(Request $request, array $validated, bool $statusChanged): bool
    {
        if ($statusChanged) {
            return true;
        }

        if (filled($validated['comment'] ?? null)) {
            return true;
        }

        return $request->hasFile('voice_note')
            || $request->hasFile('images')
            || $request->hasFile('videos')
            || $request->hasFile('documents');
    }

    /**
     * Normalize supported ranking values.
     */
    private function normalizeRewardRanking(string $value): ?string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace(['-', ' '], '_', $normalized);

        return match ($normalized) {
            'very_poor' => 'very_poor',
            'poor' => 'poor',
            'good' => 'good',
            'very_good' => 'very_good',
            'excellent', 'excelent' => 'excellent',
            'talented' => 'talented',
            default => null,
        };
    }

    /**
     * Collect selected reward recipients from all supported payload names.
     */
    private function extractRewardRecipientIds(array $validated): array
    {
        return collect([
            $validated['worker_id'] ?? null,
            ...($validated['worker_ids'] ?? []),
            ...($validated['user_ids'] ?? []),
            ...($validated['recipient_ids'] ?? []),
        ])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Ensure selected reward recipients belong to this task and are valid workers.
     */
    private function validateTaskRewardRecipients(Task $task, array $recipientIds): void
    {
        $this->validateAssignableUserIds($recipientIds, 'worker_ids');

        $assignedWorkerIds = $task->workers()
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $invalidIds = array_values(array_diff($recipientIds, $assignedWorkerIds));

        if (!empty($invalidIds)) {
            $invalidUsers = User::whereIn('id', $invalidIds)->get();

            $names = $invalidUsers
                ->map(fn (User $user) => $this->userDisplayName($user))
                ->values()
                ->implode(', ');

            throw ValidationException::withMessages([
                'worker_ids' => 'Selected reward recipients must already be assigned to this task. Invalid selection: ' . $names,
            ]);
        }
    }

    /**
     * Find attachment row for a task using query builder only.
     */
    private function findAttachmentForTask(Task $task, int $attachmentId): ?object
    {
        return DB::table('task_update_attachments')
            ->join('task_updates', 'task_updates.id', '=', 'task_update_attachments.task_update_id')
            ->where('task_update_attachments.id', $attachmentId)
            ->where('task_updates.task_id', $task->id)
            ->select([
                'task_update_attachments.id',
                'task_update_attachments.task_update_id',
                'task_update_attachments.attachment_type',
                'task_update_attachments.file_name',
                'task_update_attachments.file_path',
                'task_update_attachments.mime_type',
                'task_update_attachments.file_size',
            ])
            ->first();
    }

    /**
     * Check if rewards table is ready.
     */
    private function rewardTableReady(): bool
    {
        return Schema::hasTable('task_rewards');
    }

    /**
     * Normalized role slug.
     */
    private function roleSlug(?User $user): string
    {
        if (!$user) {
            return '';
        }

        $user->loadMissing('role');

        $roleSlug = strtolower(trim((string) $user->role?->slug));
        $roleName = strtolower(trim((string) $user->role?->name));

        return $roleSlug !== '' ? $roleSlug : $roleName;
    }

    /**
     * Is employee or intern.
     */
    private function isWorker(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return in_array($this->roleSlug($user), $this->workerRoles(), true)
            || in_array((int) $user->role_id, [4, 5], true);
    }

    /**
     * Is CEO / MD / Chief Market / Admin.
     */
    private function canManageTasks(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return in_array($this->roleSlug($user), $this->managerRoles(), true);
    }

    /**
     * CEO / MD / Chief Market / Admin can view all weekly reports.
     */
    private function canViewAllWeeklyReports(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return in_array($this->roleSlug($user), ['ceo', 'md', 'chief_market', 'admin'], true);
    }

    /**
     * Check task access.
     */
    private function canAccessTask(?User $user, Task $task): bool
    {
        if (!$user) {
            return false;
        }

        if ($this->canManageTasks($user)) {
            return true;
        }

        if (!$this->isWorker($user)) {
            return false;
        }

        return $task->workers()
            ->where('users.id', $user->id)
            ->exists();
    }

    /**
     * Only employee/intern can be assigned.
     */
    private function validateAssignableUserIds(array $assigneeIds, string $field = 'assigneeIds'): void
    {
        if (empty($assigneeIds)) {
            return;
        }

        $users = User::with('role')
            ->whereIn('id', $assigneeIds)
            ->get();

        $invalidUsers = $users->filter(function (User $user) {
            return !$this->isWorker($user);
        });

        if ($invalidUsers->isNotEmpty()) {
            $names = $invalidUsers
                ->map(fn (User $user) => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->email ?: ('User #' . $user->id))
                ->values()
                ->implode(', ');

            throw ValidationException::withMessages([
                $field => "Only employees and interns can be assigned tasks. Invalid selection: {$names}.",
            ]);
        }
    }

    /**
     * Assignable users collection.
     */
    private function getAssignableUsers(array $userIds): Collection
    {
        if (empty($userIds)) {
            return collect();
        }

        return User::with('role')
            ->whereIn('id', $userIds)
            ->get()
            ->filter(fn (User $user) => $this->isWorker($user))
            ->values();
    }

    /**
     * CEO + MD recipients for task status notifications.
     */
    private function leaderRecipients(): Collection
    {
        return User::with('role')
            ->where('is_active', true)
            ->whereNotNull('email')
            ->whereHas('role', function ($query) {
                $query->whereIn('slug', ['ceo', 'md']);
            })
            ->get();
    }

    /**
     * Notify CEO + MD when task status changes.
     */
    private function notifyLeadersTaskStatusChanged(
        Task $task,
        string $oldStatus,
        string $newStatus,
        ?User $changedBy = null
    ): void {
        if ($oldStatus === $newStatus) {
            return;
        }

        $leaders = $this->leaderRecipients();

        if ($leaders->isEmpty()) {
            return;
        }

        $task->loadMissing(['property', 'workers.role', 'creator']);

        $taskUrl = rtrim((string) env('FRONTEND_APP_URL', config('app.url')), '/')
            . '/dashboard/tasks/' . $task->id;

        foreach ($leaders as $leader) {
            $leader->notify(new TaskStatusChangedNotification(
                task: $task,
                oldStatus: $oldStatus,
                newStatus: $newStatus,
                changedBy: $changedBy,
                taskUrl: $taskUrl
            ));
        }
    }

    /**
     * Resolve weekly range as Monday to Friday.
     */
    private function resolveWeeklyReportRange(Request $request): array
    {
        $hasFromDate = $request->filled('from_date');
        $hasToDate = $request->filled('to_date');

        if ($hasFromDate || $hasToDate) {
            $start = $hasFromDate
                ? Carbon::parse((string) $request->input('from_date'))->startOfDay()
                : Carbon::parse((string) $request->input('to_date'))->startOfDay();

            $end = $hasToDate
                ? Carbon::parse((string) $request->input('to_date'))->endOfDay()
                : Carbon::parse((string) $request->input('from_date'))->endOfDay();

            if ($end->lt($start)) {
                throw ValidationException::withMessages([
                    'to_date' => 'To date must be after or equal to From date.',
                ]);
            }

            return [$start, $end];
        }

        $referenceDate = $request->filled('week_start')
            ? Carbon::parse((string) $request->input('week_start'))
            : now();

        $weekStart = $referenceDate->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(4)->endOfDay();

        return [$weekStart, $weekEnd];
    }

    /**
     * Workers included in the requested weekly report.
     */
    private function resolveWeeklyReportSubjects(User $authUser, ?int $requestedUserId = null): Collection
    {
        if ($this->isWorker($authUser)) {
            return collect([$authUser->loadMissing('role')]);
        }

        $query = User::with('role')
            ->whereHas('role', function ($query) {
                $query->whereIn('slug', $this->workerRoles());
            });

        if ($requestedUserId) {
            $query->where('id', $requestedUserId);
        }

        return $query->orderBy('id')->get();
    }

    /**
     * Build one worker weekly report.
     */
    private function buildWeeklyWorkerReport(User $worker, Carbon $weekStart, Carbon $weekEnd): array
{
    $relations = [
        'property',
        'workers' => function ($query) use ($worker) {
            $query->where('users.id', $worker->id);
        },
        'updates.user.role',
    ];

    if ($this->rewardTableReady()) {
        $relations[] = 'rewards';
    }

    $tasks = Task::with($relations)
        ->whereHas('workers', function ($query) use ($worker) {
            $query->where('users.id', $worker->id);
        })
        ->get();

    $receivedTasks = [];
    $completedTasks = [];
    $approvedTasks = [];

    $allTasks = [];
    $rewardedTasks = [];
    $gradedTasks = [];
    $overdueTasks = [];

    $statusKeys = $this->reportStatusKeys();
    $statusCounts = [];
    $tasksByStatus = [];

    foreach ($statusKeys as $statusKey) {
        $statusCounts[$statusKey] = 0;
        $tasksByStatus[$statusKey] = [];
    }

    $rewardedCount = 0;
    $gradedCount = 0;
    $overdueCount = 0;
    $openCount = 0;
    $gradeTotal = 0.0;
    $gradeSamples = 0;
    $rankingBreakdown = [];

    foreach ($tasks as $task) {
        $assignedAt = null;
        $workerRelation = $task->workers->first();

        if ($workerRelation && $workerRelation->pivot && filled($workerRelation->pivot->assigned_at)) {
            $assignedAt = Carbon::parse($workerRelation->pivot->assigned_at);
        }

        $statusKey = $this->normalizeReportStatusKey((string) $task->status);
        $workerReward = $this->latestRewardForWorker($task, (int) $worker->id);
        $rewarded = $workerReward !== null;

        $marks = $this->extractRewardMarks($workerReward);
        $rankingLabel = $this->extractRewardLabel($workerReward);
        $graded = $marks !== null || filled($rankingLabel);

        $overdue = $this->isTaskCurrentlyOverdue($task);
        $allTasks[] = $this->buildTaskReportRow(
            task: $task,
            assignedAt: $assignedAt,
            reward: $workerReward,
            overdue: $overdue
        );

        $statusCounts[$statusKey] = ($statusCounts[$statusKey] ?? 0) + 1;
        $tasksByStatus[$statusKey][] = $this->buildTaskReportRow(
            task: $task,
            assignedAt: $assignedAt,
            reward: $workerReward,
            overdue: $overdue
        );

        if ($rewarded) {
            $rewardedCount++;
            $rewardedTasks[] = $this->buildTaskReportRow(
                task: $task,
                assignedAt: $assignedAt,
                reward: $workerReward,
                overdue: $overdue
            );
        }

        if ($graded) {
            $gradedCount++;
            $gradedTasks[] = $this->buildTaskReportRow(
                task: $task,
                assignedAt: $assignedAt,
                reward: $workerReward,
                overdue: $overdue
            );

            if ($marks !== null) {
                $gradeTotal += $marks;
                $gradeSamples++;
            }

            if (filled($rankingLabel)) {
                $rankingBreakdown[$rankingLabel] = ($rankingBreakdown[$rankingLabel] ?? 0) + 1;
            }
        }

        if ($overdue) {
            $overdueCount++;
            $overdueTasks[] = $this->buildTaskReportRow(
                task: $task,
                assignedAt: $assignedAt,
                reward: $workerReward,
                overdue: true
            );
        }

        if (strtolower((string) $task->status) !== 'completed') {
            $openCount++;
        }

        if ($assignedAt && $assignedAt->between($weekStart, $weekEnd, true)) {
            $receivedTasks[$task->id] = [
                'id' => $task->id,
                'title' => $task->title,
                'status' => (string) $task->status,
                'property_id' => $task->property_id,
                'property_name' => $this->propertyLabel($task),
                'assigned_at' => $assignedAt->toDateTimeString(),
                'start_at' => optional($task->start_at)->toDateTimeString(),
                'end_at' => optional($task->end_at)->toDateTimeString(),
                'rewarded' => $rewarded,
                'graded' => $graded,
                'marks_percentage' => $marks,
                'ranking' => $rankingLabel,
            ];
        }

        $completionUpdate = collect($task->updates ?? [])
            ->filter(function (TaskUpdate $update) use ($worker, $weekStart, $weekEnd) {
                return (int) $update->user_id === (int) $worker->id
                    && strtolower((string) $update->status_to) === 'completed'
                    && Carbon::parse($update->created_at)->between($weekStart, $weekEnd, true);
            })
            ->sortByDesc(fn (TaskUpdate $update) => $update->created_at)
            ->first();

        if ($completionUpdate) {
            $completedTasks[$task->id] = [
                'id' => $task->id,
                'title' => $task->title,
                'status' => (string) $task->status,
                'property_id' => $task->property_id,
                'property_name' => $this->propertyLabel($task),
                'completed_at' => Carbon::parse($completionUpdate->created_at)->toDateTimeString(),
                'start_at' => optional($task->start_at)->toDateTimeString(),
                'end_at' => optional($task->end_at)->toDateTimeString(),
                'rewarded' => $rewarded,
                'graded' => $graded,
                'marks_percentage' => $marks,
                'ranking' => $rankingLabel,
            ];
        }

        $approvalUpdate = collect($task->updates ?? [])
            ->filter(function (TaskUpdate $update) use ($weekStart, $weekEnd) {
                return $this->isManagerActor($update->user)
                    && in_array(strtolower((string) $update->status_to), ['understandable', 'completed'], true)
                    && Carbon::parse($update->created_at)->between($weekStart, $weekEnd, true);
            })
            ->sortByDesc(fn (TaskUpdate $update) => $update->created_at)
            ->first();

        if ($completionUpdate && $approvalUpdate) {
            $approvedTasks[$task->id] = [
                'id' => $task->id,
                'title' => $task->title,
                'status' => (string) $task->status,
                'property_id' => $task->property_id,
                'property_name' => $this->propertyLabel($task),
                'approved_at' => Carbon::parse($approvalUpdate->created_at)->toDateTimeString(),
                'approved_by' => $this->userDisplayName($approvalUpdate->user),
                'start_at' => optional($task->start_at)->toDateTimeString(),
                'end_at' => optional($task->end_at)->toDateTimeString(),
                'rewarded' => $rewarded,
                'graded' => $graded,
                'marks_percentage' => $marks,
                'ranking' => $rankingLabel,
            ];
        }
    }

    $receivedCount = count($receivedTasks);
    $completedCount = count($completedTasks);
    $approvedCount = count($approvedTasks);
    $pendingApprovalCount = max($completedCount - $approvedCount, 0);

    $completionRate = $receivedCount > 0
        ? round(($completedCount / $receivedCount) * 100, 2)
        : 0.0;

    $approvalRate = $completedCount > 0
        ? round(($approvedCount / $completedCount) * 100, 2)
        : 0.0;

    $averageGrade = $gradeSamples > 0
        ? round($gradeTotal / $gradeSamples, 2)
        : 0.0;

    return [
        'user' => [
            'id' => $worker->id,
            'name' => $this->userDisplayName($worker),
            'email' => $worker->email,
            'role' => $this->roleSlug($worker),
        ],
        'week' => [
            'start' => $weekStart->toDateString(),
            'end' => $weekEnd->toDateString(),
        ],
        'totals' => [
            'tasks_total' => $tasks->count(),
            'tasks_received' => $receivedCount,
            'tasks_completed' => $completedCount,
            'tasks_approved' => $approvedCount,
            'pending_approval' => $pendingApprovalCount,
            'tasks_rewarded' => $rewardedCount,
            'tasks_graded' => $gradedCount,
            'tasks_overdue' => $overdueCount,
            'tasks_open' => $openCount,
            'status_counts' => $statusCounts,
            'completion_rate' => $completionRate,
            'approval_rate' => $approvalRate,
            'average_grade' => $averageGrade,
        ],
        'rewards' => [
            'rewarded_tasks' => $rewardedCount,
            'graded_tasks' => $gradedCount,
            'average_marks_percentage' => $averageGrade,
            'ranking_breakdown' => $rankingBreakdown,
        ],
        'tasks' => [
            'all' => array_values($allTasks),
            'by_status' => $tasksByStatus,
            'rewarded' => array_values($rewardedTasks),
            'graded' => array_values($gradedTasks),
            'overdue' => array_values($overdueTasks),
            'received' => array_values($receivedTasks),
            'completed' => array_values($completedTasks),
            'approved' => array_values($approvedTasks),
        ],
    ];
}

    /**
     * Summary across all worker reports.
     */
    private function buildWeeklyReportSummary(Collection $reports): array
{
    $totalWorkers = $reports->count();

    $tasksTotal = $reports->sum(fn ($report) => (int) data_get($report, 'totals.tasks_total', 0));
    $tasksReceived = $reports->sum(fn ($report) => (int) data_get($report, 'totals.tasks_received', 0));
    $tasksCompleted = $reports->sum(fn ($report) => (int) data_get($report, 'totals.tasks_completed', 0));
    $tasksApproved = $reports->sum(fn ($report) => (int) data_get($report, 'totals.tasks_approved', 0));
    $pendingApproval = $reports->sum(fn ($report) => (int) data_get($report, 'totals.pending_approval', 0));
    $tasksRewarded = $reports->sum(fn ($report) => (int) data_get($report, 'totals.tasks_rewarded', 0));
    $tasksGraded = $reports->sum(fn ($report) => (int) data_get($report, 'totals.tasks_graded', 0));
    $tasksOverdue = $reports->sum(fn ($report) => (int) data_get($report, 'totals.tasks_overdue', 0));
    $tasksOpen = $reports->sum(fn ($report) => (int) data_get($report, 'totals.tasks_open', 0));

    $averageCompletionRate = $totalWorkers > 0
        ? round($reports->avg(fn ($report) => (float) data_get($report, 'totals.completion_rate', 0)), 2)
        : 0.0;

    $averageApprovalRate = $totalWorkers > 0
        ? round($reports->avg(fn ($report) => (float) data_get($report, 'totals.approval_rate', 0)), 2)
        : 0.0;

    $averageGrade = $totalWorkers > 0
        ? round($reports->avg(fn ($report) => (float) data_get($report, 'totals.average_grade', 0)), 2)
        : 0.0;

    $statusCounts = [];
    foreach ($this->reportStatusKeys() as $statusKey) {
        $statusCounts[$statusKey] = 0;
    }

    $rankingBreakdown = [];

    foreach ($reports as $report) {
        foreach ((array) data_get($report, 'totals.status_counts', []) as $statusKey => $count) {
            $normalizedStatusKey = $this->normalizeReportStatusKey((string) $statusKey);
            $statusCounts[$normalizedStatusKey] = ($statusCounts[$normalizedStatusKey] ?? 0) + (int) $count;
        }

        foreach ((array) data_get($report, 'rewards.ranking_breakdown', []) as $label => $count) {
            $rankingBreakdown[(string) $label] = ($rankingBreakdown[(string) $label] ?? 0) + (int) $count;
        }
    }

    return [
        'total_workers' => $totalWorkers,
        'tasks_total' => $tasksTotal,
        'tasks_received' => $tasksReceived,
        'tasks_completed' => $tasksCompleted,
        'tasks_approved' => $tasksApproved,
        'pending_approval' => $pendingApproval,
        'tasks_rewarded' => $tasksRewarded,
        'tasks_graded' => $tasksGraded,
        'tasks_overdue' => $tasksOverdue,
        'tasks_open' => $tasksOpen,
        'status_counts' => $statusCounts,
        'ranking_breakdown' => $rankingBreakdown,
        'average_completion_rate' => $averageCompletionRate,
        'average_approval_rate' => $averageApprovalRate,
        'average_grade' => $averageGrade,
    ];
}

    private function reportStatusKeys(): array
{
    return [
        'received',
        'start',
        'pending',
        'understandable',
        'not_understandable',
        'completed',
        'other',
    ];
}

private function normalizeReportStatusKey(string $status): string
{
    $normalized = strtolower(trim($status));

    return in_array($normalized, $this->reportStatusKeys(), true)
        ? $normalized
        : 'other';
}

private function buildTaskReportRow(
    Task $task,
    ?Carbon $assignedAt = null,
    mixed $reward = null,
    bool $overdue = false
): array {
    $marks = $this->extractRewardMarks($reward);
    $ranking = $this->extractRewardLabel($reward);

    return [
        'id' => $task->id,
        'title' => $task->title,
        'status' => (string) $task->status,
        'property_id' => $task->property_id,
        'property_name' => $this->propertyLabel($task),
        'assigned_at' => $assignedAt?->toDateTimeString(),
        'start_at' => optional($task->start_at)->toDateTimeString(),
        'end_at' => optional($task->end_at)->toDateTimeString(),
        'overdue' => $overdue,
        'rewarded' => $reward !== null,
        'graded' => $marks !== null || filled($ranking),
        'ranking' => $ranking,
        'marks_percentage' => $marks,
    ];
}

private function latestRewardForWorker(Task $task, int $workerId): mixed
{
    if (!$this->rewardTableReady() || !isset($task->rewards)) {
        return null;
    }

    $rewards = collect($task->rewards)
        ->filter(function ($reward) use ($workerId) {
            $recipientId = $this->extractRewardRecipientUserId($reward);
            return $recipientId !== null && $recipientId === $workerId;
        })
        ->sortByDesc(function ($reward) {
            return $reward->created_at ?? null;
        })
        ->values();

    return $rewards->first();
}

private function extractRewardRecipientUserId(mixed $reward): ?int
{
    if (!$reward) {
        return null;
    }

    foreach (['recipient_user_id', 'worker_id', 'user_id', 'recipient_id'] as $field) {
        $value = data_get($reward, $field);

        if ($value !== null && $value !== '') {
            return (int) $value;
        }
    }

    return null;
}

private function extractRewardMarks(mixed $reward): ?float
{
    if (!$reward) {
        return null;
    }

    foreach (['marks_percentage', 'percentage'] as $field) {
        $value = data_get($reward, $field);

        if ($value !== null && $value !== '') {
            return round((float) $value, 2);
        }
    }

    return null;
}

private function extractRewardLabel(mixed $reward): ?string
{
    if (!$reward) {
        return null;
    }

    foreach (['ranking_label', 'grading', 'grade', 'ranking'] as $field) {
        $value = trim((string) data_get($reward, $field));

        if ($value !== '') {
            return $value;
        }
    }

    return null;
}

private function isTaskCurrentlyOverdue(Task $task): bool
{
    if (!$task->end_at) {
        return false;
    }

    if (strtolower((string) $task->status) === 'completed') {
        return false;
    }

    $endAt = $task->end_at instanceof Carbon
        ? $task->end_at
        : Carbon::parse($task->end_at);

    return $endAt->lt(now());
}


    /**
     * Check if actor is manager.
     */
    private function isManagerActor(?User $user): bool
    {
        return $this->canManageTasks($user);
    }

    /**
     * Property label helper.
     */
    private function propertyLabel(Task $task): string
    {
        return (string) (
            $task->property?->title
            ?? $task->property?->name
            ?? 'No property'
        );
    }

    /**
     * User display helper.
     */
    private function userDisplayName(?User $user): string
    {
        if (!$user) {
            return 'Unknown User';
        }

        $name = trim(
            implode(' ', array_filter([
                $user->first_name ?? null,
                $user->last_name ?? null,
            ]))
        );

        return $name !== ''
            ? $name
            : ($user->name ?? $user->email ?? ('User #' . $user->id));
    }
}