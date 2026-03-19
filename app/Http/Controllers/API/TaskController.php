<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskUpdate;
use App\Models\TaskUpdateAttachment;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
     * Relations for listing tasks.
     */
    private function taskListRelations(): array
    {
        return [
            'property',
            'creator',
            'workers',
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
            'workers',
            'latestUpdate.user',
            'latestUpdate.attachments',
            'updates.user',
            'updates.attachments',
        ];
    }

    /**
     * Employee update validation.
     */
    private function employeeUpdateRules(): array
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
     * Admin update validation.
     */
    private function adminUpdateRules(): array
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
     * - Employee sees only assigned tasks
     * - Admin/other roles can see all tasks
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Task::with($this->taskListRelations());

        if ($this->isEmployee($user)) {
            $query->whereHas('workers', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
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
     * - Employee can only view tasks assigned to them
     * - Admin/other roles can view all tasks
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
     * - Employee: can update status + comment/files, and only on assigned task
     * - Admin/other roles: can update full task + comment/files
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();

        if ($this->isEmployee($user)) {
            if (!$this->canAccessTask($user, $task)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not allowed to update this task.',
                ], 403);
            }

            $validated = $request->validate($this->employeeUpdateRules());

            $oldStatus = (string) $task->status;

            DB::transaction(function () use ($task, $validated, $request, $user, $oldStatus) {
                $task->update([
                    'status' => $validated['status'],
                ]);

                $statusChanged = $oldStatus !== (string) $task->status;

                if ($this->hasUpdatePayload($request, $validated, $statusChanged)) {
                    $this->createTaskUpdateFromRequest(
                        task: $task,
                        user: $user,
                        request: $request,
                        comment: $validated['comment'] ?? null,
                        statusFrom: $statusChanged ? $oldStatus : (string) $task->status,
                        statusTo: (string) $task->status,
                    );
                }
            });

            $task->load($this->taskShowRelations());

            return response()->json([
                'success' => true,
                'message' => 'Task status updated successfully.',
                'data' => $task,
            ]);
        }

        $validated = $request->validate($this->adminUpdateRules());

        $oldStatus = (string) $task->status;

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

        DB::transaction(function () use ($task, $validated, $request, $user, $startAt, $endAt, $oldStatus) {
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

            $statusChanged = $oldStatus !== (string) $task->status;

            if ($this->hasUpdatePayload($request, $validated, $statusChanged)) {
                $this->createTaskUpdateFromRequest(
                    task: $task,
                    user: $user,
                    request: $request,
                    comment: $validated['comment'] ?? null,
                    statusFrom: $statusChanged ? $oldStatus : (string) $task->status,
                    statusTo: (string) $task->status,
                );
            }
        });

        $task->load($this->taskShowRelations());

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

        if ($this->isEmployee($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Employees are not allowed to delete tasks.',
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
            'data' => $task->load($this->taskShowRelations()),
        ]);
    }

    /**
     * Worker task box.
     * Logged in worker sees tasks assigned to him/her.
     */
    public function myTasks(Request $request): JsonResponse
    {
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

        // Make task.updated_at reflect latest comment/file/status activity
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
     * Check if logged in user is employee.
     */
    private function isEmployee(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $user->loadMissing('role');

        $roleSlug = strtolower(trim((string) $user->role?->slug));
        $roleName = strtolower(trim((string) $user->role?->name));

        return $roleSlug === 'employee'
            || $roleName === 'employee'
            || (int) $user->role_id === 4;
    }

    /**
     * Check if user can access a task.
     */
    private function canAccessTask(?User $user, Task $task): bool
    {
        if (!$user) {
            return false;
        }

        if (!$this->isEmployee($user)) {
            return true;
        }

        return $task->workers()
            ->where('users.id', $user->id)
            ->exists();
    }
}