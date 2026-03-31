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
     * Relations for listing tasks.
     */
    private function taskListRelations(): array
    {
        return [
            'property',
            'creator',
            'workers.role',
            'latestUpdate.user',
            'latestUpdate.attachments',
        ];
    }

    /**
     * Relations for showing one task with full history.
     */
    private function taskShowRelations(): array
    {
        return [
            'property',
            'creator',
            'workers.role',
            'latestUpdate.user',
            'latestUpdate.attachments',
            'updates.user',
            'updates.attachments',
        ];
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
                    if ($attachment->file_path) {
                        Storage::disk($attachment->disk ?: 'public')->delete($attachment->file_path);
                    }
                }

                $update->attachments()->delete();
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
     * Auto-generate weekly report from Monday to Friday.
     * - CEO / MD / Chief Market can see all employee + intern reports
     * - Employee / Intern can see only own weekly report
     *
     * Query params:
     * - week_start=2026-03-23   (optional, any date in the target week)
     * - user_id=5               (optional, for leaders to inspect one worker)
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
            'message' => 'Weekly report generated successfully.',
            'data' => [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
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
        $path = $file->store(
            "task-updates/{$taskUpdate->task_id}/{$folder}",
            'public'
        );

        return TaskUpdateAttachment::create([
            'task_update_id' => $taskUpdate->id,
            'attachment_type' => $attachmentType,
            'disk' => 'public',
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);
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
     *
     * Logic:
     * - tasks_received  => assigned within Monday-Friday
     * - tasks_completed => worker changed task status to completed within Monday-Friday
     * - tasks_approved  => manager approved those completed tasks within Monday-Friday
     *
     * Here "approved" means a manager-side status update to:
     * - understandable
     * - completed
     */
    private function buildWeeklyWorkerReport(User $worker, Carbon $weekStart, Carbon $weekEnd): array
    {
        $tasks = Task::with([
            'property',
            'workers' => function ($query) use ($worker) {
                $query->where('users.id', $worker->id);
            },
            'updates.user.role',
        ])
            ->whereHas('workers', function ($query) use ($worker) {
                $query->where('users.id', $worker->id);
            })
            ->get();

        $receivedTasks = [];
        $completedTasks = [];
        $approvedTasks = [];

        foreach ($tasks as $task) {
            $assignedAt = null;
            $workerRelation = $task->workers->first();

            if ($workerRelation && $workerRelation->pivot && filled($workerRelation->pivot->assigned_at)) {
                $assignedAt = Carbon::parse($workerRelation->pivot->assigned_at);
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
                'tasks_received' => $receivedCount,
                'tasks_completed' => $completedCount,
                'tasks_approved' => $approvedCount,
                'pending_approval' => $pendingApprovalCount,
                'completion_rate' => $completionRate,
                'approval_rate' => $approvalRate,
            ],
            'tasks' => [
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

        $tasksReceived = $reports->sum(fn ($report) => (int) data_get($report, 'totals.tasks_received', 0));
        $tasksCompleted = $reports->sum(fn ($report) => (int) data_get($report, 'totals.tasks_completed', 0));
        $tasksApproved = $reports->sum(fn ($report) => (int) data_get($report, 'totals.tasks_approved', 0));
        $pendingApproval = $reports->sum(fn ($report) => (int) data_get($report, 'totals.pending_approval', 0));

        $averageCompletionRate = $totalWorkers > 0
            ? round($reports->avg(fn ($report) => (float) data_get($report, 'totals.completion_rate', 0)), 2)
            : 0.0;

        $averageApprovalRate = $totalWorkers > 0
            ? round($reports->avg(fn ($report) => (float) data_get($report, 'totals.approval_rate', 0)), 2)
            : 0.0;

        return [
            'total_workers' => $totalWorkers,
            'tasks_received' => $tasksReceived,
            'tasks_completed' => $tasksCompleted,
            'tasks_approved' => $tasksApproved,
            'pending_approval' => $pendingApproval,
            'average_completion_rate' => $averageCompletionRate,
            'average_approval_rate' => $averageApprovalRate,
        ];
    }

    /**
     * Identify manager-side actor.
     */
    private function isManagerActor(?User $user): bool
    {
        return $this->canManageTasks($user);
    }

    /**
     * Best display name for user.
     */
    private function userDisplayName(?User $user): string
    {
        if (!$user) {
            return 'User';
        }

        $fullName = trim(
            collect([
                $user->first_name ?? null,
                $user->last_name ?? null,
            ])->filter()->implode(' ')
        );

        if ($fullName !== '') {
            return $fullName;
        }

        if (filled($user->name ?? null)) {
            return (string) $user->name;
        }

        if (filled($user->full_name ?? null)) {
            return (string) $user->full_name;
        }

        if (filled($user->email ?? null)) {
            return (string) $user->email;
        }

        return 'User #' . $user->id;
    }

    /**
     * Best display label for property.
     */
    private function propertyLabel(Task $task): ?string
    {
        $property = $task->property;

        if (!$property) {
            return null;
        }

        foreach (['name', 'title', 'property_name', 'hotel_name', 'label'] as $field) {
            if (isset($property->{$field}) && filled($property->{$field})) {
                return (string) $property->{$field};
            }
        }

        return $task->property_id ? ('Property #' . $task->property_id) : null;
    }
}