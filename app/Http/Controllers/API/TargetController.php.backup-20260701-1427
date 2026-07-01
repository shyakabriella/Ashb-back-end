<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Target;
use App\Models\User;
use App\Services\TargetScoreService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TargetController extends Controller
{
    public function __construct(
        private readonly TargetScoreService $targetScoreService
    ) {
    }

    /**
     * List monthly target scores.
     * - Employee/Intern sees only own score.
     * - CEO/MD/Chief Market/Admin can see all workers or one selected worker.
     *
     * Query params:
     * - month=2026-06
     * - user_id=5
     * - include_tasks=1 (task breakdown is returned only for one worker)
     */
    public function index(Request $request): JsonResponse
    {
        $authUser = $request->user();

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'include_tasks' => ['nullable', 'boolean'],
        ]);

        $month = $validated['month'] ?? now()->format('Y-m');
        $requestedUserId = isset($validated['user_id'])
            ? (int) $validated['user_id']
            : null;

        $workers = $this->resolveWorkers($authUser, $requestedUserId);
        $includeTasks = $request->boolean('include_tasks') && $workers->count() === 1;

        $scores = $workers
            ->map(fn (User $worker) => $this->targetScoreService->calculateForWorker(
                worker: $worker,
                month: $month,
                includeTasks: $includeTasks
            ))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Monthly target scores fetched successfully.',
            'data' => [
                'month' => $month,
                'workers_count' => $scores->count(),
                'scores' => $scores,
            ],
        ]);
    }

    /**
     * Create or replace one worker's monthly target.
     */
    public function store(Request $request): JsonResponse
    {
        $authUser = $request->user();

        if (!$this->canManageTargets($authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to create targets.',
            ], 403);
        }

        $validated = $request->validate($this->targetRules(requiredUser: true, requiredMonth: true));
        $worker = User::with('role')->findOrFail((int) $validated['user_id']);
        $this->ensureWorker($worker, 'user_id');

        $target = Target::query()->updateOrCreate(
            [
                'user_id' => $worker->id,
                'target_month' => Carbon::parse($validated['month'] . '-01')->startOfMonth()->toDateString(),
            ],
            [
                'minimum_tasks' => $validated['minimum_tasks'] ?? Target::DEFAULT_MINIMUM_TASKS,
                'target_percentage' => $validated['target_percentage'] ?? Target::DEFAULT_TARGET_PERCENTAGE,
                'maximum_score_per_task' => $validated['maximum_score_per_task'] ?? Target::DEFAULT_MAXIMUM_SCORE_PER_TASK,
                'created_by' => $authUser->id,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Monthly target saved successfully.',
            'data' => $this->targetScoreService->calculateForWorker(
                worker: $worker,
                month: $target->target_month,
                includeTasks: true
            ),
        ], 201);
    }

    public function show(Request $request, Target $target): JsonResponse
    {
        $authUser = $request->user();
        $target->loadMissing('worker.role');

        if (!$this->canAccessTarget($authUser, $target)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to view this target.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Monthly target fetched successfully.',
            'data' => $this->targetScoreService->calculateForWorker(
                worker: $target->worker,
                month: $target->target_month,
                includeTasks: true
            ),
        ]);
    }

    public function update(Request $request, Target $target): JsonResponse
    {
        $authUser = $request->user();

        if (!$this->canManageTargets($authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to update targets.',
            ], 403);
        }

        $validated = $request->validate($this->targetRules(requiredUser: false, requiredMonth: false));

        $target->update([
            'minimum_tasks' => $validated['minimum_tasks'] ?? $target->minimum_tasks,
            'target_percentage' => $validated['target_percentage'] ?? $target->target_percentage,
            'maximum_score_per_task' => $validated['maximum_score_per_task'] ?? $target->maximum_score_per_task,
            'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $target->notes,
        ]);

        $target->loadMissing('worker.role');

        return response()->json([
            'success' => true,
            'message' => 'Monthly target updated successfully.',
            'data' => $this->targetScoreService->calculateForWorker(
                worker: $target->worker,
                month: $target->target_month,
                includeTasks: true
            ),
        ]);
    }

    /**
     * Delete a custom target. The next score request recreates the default target.
     */
    public function destroy(Request $request, Target $target): JsonResponse
    {
        if (!$this->canManageTargets($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to delete targets.',
            ], 403);
        }

        $target->delete();

        return response()->json([
            'success' => true,
            'message' => 'Monthly target deleted. Default target will be recreated when needed.',
        ]);
    }

    private function targetRules(bool $requiredUser, bool $requiredMonth): array
    {
        return [
            'user_id' => [$requiredUser ? 'required' : 'sometimes', 'integer', 'exists:users,id'],
            'month' => [$requiredMonth ? 'required' : 'sometimes', 'date_format:Y-m'],
            'minimum_tasks' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'target_percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'maximum_score_per_task' => ['sometimes', 'numeric', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function resolveWorkers(User $authUser, ?int $requestedUserId): Collection
    {
        if ($this->isWorker($authUser)) {
            return collect([$authUser->loadMissing('role')]);
        }

        if (!$this->canManageTargets($authUser)) {
            abort(403, 'You are not allowed to view target scores.');
        }

        $query = User::with('role')
            ->where(function ($query) {
                $query
                    ->whereIn('role_id', [4, 5])
                    ->orWhereHas('role', function ($roleQuery) {
                        $roleQuery
                            ->whereIn('slug', ['employee', 'intern'])
                            ->orWhereIn('name', ['Employee', 'Intern']);
                    });
            });

        if ($requestedUserId) {
            $query->where('id', $requestedUserId);
        }

        return $query->orderBy('id')->get();
    }

    private function ensureWorker(User $user, string $field): void
    {
        if ($this->isWorker($user)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'Only employees and interns can receive monthly targets.',
        ]);
    }

    private function canAccessTarget(?User $authUser, Target $target): bool
    {
        if (!$authUser) {
            return false;
        }

        return $this->canManageTargets($authUser)
            || ($this->isWorker($authUser) && (int) $target->user_id === (int) $authUser->id);
    }

    private function canManageTargets(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return in_array($this->roleSlug($user), [
            'ceo',
            'md',
            'managing_director',
            'managingdirector',
            'chief_market',
            'chief_marketing',
            'chiefmarket',
            'admin',
            'system_admin',
            'super_admin',
        ], true) || in_array((int) $user->role_id, [1, 2, 3], true);
    }

    private function isWorker(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return in_array($this->roleSlug($user), ['employee', 'intern'], true)
            || in_array((int) $user->role_id, [4, 5], true);
    }

    private function roleSlug(User $user): string
    {
        $user->loadMissing('role');

        $slug = $this->normalizeRoleKey((string) $user->role?->slug);
        $name = $this->normalizeRoleKey((string) $user->role?->name);

        return $slug !== '' ? $slug : $name;
    }

    private function normalizeRoleKey(string $value): string
    {
        return preg_replace('/[\s-]+/', '_', strtolower(trim($value))) ?: '';
    }
}