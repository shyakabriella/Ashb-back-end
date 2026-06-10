<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Salary;
use App\Models\User;
use App\Services\TargetScoreService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SalaryController extends Controller
{
    public function __construct(
        private readonly TargetScoreService $targetScoreService
    ) {
    }

    /**
     * List calculated monthly salaries.
     *
     * Rules:
     * - Employee/Intern sees only their own salary calculation.
     * - CEO/MD/Admin can see all workers or one selected worker.
     * - Salary calculation uses the existing monthly TargetScoreService.
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
        $monthStart = Carbon::parse($month . '-01')->startOfMonth()->startOfDay();
        $requestedUserId = isset($validated['user_id'])
            ? (int) $validated['user_id']
            : null;

        $workers = $this->resolveWorkers($authUser, $requestedUserId);
        $includeTasks = $request->boolean('include_tasks') && $workers->count() === 1;

        $calculations = $workers
            ->map(fn (User $worker) => $this->calculateForWorker(
                worker: $worker,
                monthStart: $monthStart,
                includeTasks: $includeTasks
            ))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Monthly salary calculations fetched successfully.',
            'data' => [
                'month' => $monthStart->format('Y-m'),
                'workers_count' => $calculations->count(),
                'calculations' => $calculations,
            ],
        ]);
    }

    /**
     * Create or replace one worker's salary effective from a selected month.
     *
     * A salary saved for 2026-06 applies to June 2026 and future months until
     * another salary is saved for a later month.
     */
    public function store(Request $request): JsonResponse
    {
        $authUser = $request->user();

        if (!$this->canManageSalaries($authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to create salaries.',
            ], 403);
        }

        $validated = $request->validate($this->salaryRules());

        $worker = User::with('role')->findOrFail((int) $validated['user_id']);
        $this->ensureWorker($worker, 'user_id');

        $effectiveFrom = Carbon::parse($validated['effective_from'] . '-01')
            ->startOfMonth()
            ->toDateString();

        $salary = Salary::query()->updateOrCreate(
            [
                'user_id' => $worker->id,
                'effective_from' => $effectiveFrom,
            ],
            [
                'base_salary' => $validated['base_salary'],
                'currency' => strtoupper((string) ($validated['currency'] ?? Salary::DEFAULT_CURRENCY)),
                'created_by' => $authUser->id,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Employee salary saved successfully.',
            'data' => $this->calculateForWorker(
                worker: $worker,
                monthStart: Carbon::parse($salary->effective_from)->startOfMonth(),
                includeTasks: true
            ),
        ], 201);
    }

    /**
     * Show one salary record with calculated payment for a selected month.
     *
     * Optional query param:
     * - month=2026-06
     */
    public function show(Request $request, Salary $salary): JsonResponse
    {
        $authUser = $request->user();
        $salary->loadMissing('worker.role');

        if (!$this->canAccessSalary($authUser, $salary)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to view this salary.',
            ], 403);
        }

        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'include_tasks' => ['nullable', 'boolean'],
        ]);

        $monthStart = isset($validated['month'])
            ? Carbon::parse($validated['month'] . '-01')->startOfMonth()
            : Carbon::parse($salary->effective_from)->startOfMonth();

        return response()->json([
            'success' => true,
            'message' => 'Employee salary fetched successfully.',
            'data' => $this->calculateForWorker(
                worker: $salary->worker,
                monthStart: $monthStart,
                includeTasks: $request->boolean('include_tasks', true)
            ),
        ]);
    }

    /**
     * Update one saved salary record.
     */
    public function update(Request $request, Salary $salary): JsonResponse
    {
        $authUser = $request->user();

        if (!$this->canManageSalaries($authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to update salaries.',
            ], 403);
        }

        $validated = $request->validate([
            'base_salary' => ['sometimes', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'notes' => ['nullable', 'string'],
        ]);

        $salary->update([
            'base_salary' => $validated['base_salary'] ?? $salary->base_salary,
            'currency' => isset($validated['currency'])
                ? strtoupper((string) $validated['currency'])
                : $salary->currency,
            'notes' => array_key_exists('notes', $validated)
                ? $validated['notes']
                : $salary->notes,
        ]);

        $salary->loadMissing('worker.role');

        return response()->json([
            'success' => true,
            'message' => 'Employee salary updated successfully.',
            'data' => $this->calculateForWorker(
                worker: $salary->worker,
                monthStart: Carbon::parse($salary->effective_from)->startOfMonth(),
                includeTasks: true
            ),
        ]);
    }

    /**
     * Delete one salary record.
     */
    public function destroy(Request $request, Salary $salary): JsonResponse
    {
        if (!$this->canManageSalaries($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to delete salaries.',
            ], 403);
        }

        $salary->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employee salary deleted successfully.',
        ]);
    }

    /**
     * Calculate salary earned by comparing task quantity and target score.
     *
     * Salary progress is the lower value between:
     * - quantity progress: completed tasks compared with minimum tasks
     * - score target progress: monthly score compared with target percentage
     *
     * This means full salary is earned only when both requirements are met.
     */
    private function calculateForWorker(
        User $worker,
        Carbon $monthStart,
        bool $includeTasks
    ): array {
        $targetScore = $this->targetScoreService->calculateForWorker(
            worker: $worker,
            month: $monthStart,
            includeTasks: $includeTasks
        );

        $salary = $this->resolveSalaryForMonth($worker, $monthStart);

        $quantityProgress = $this->clampPercentage(
            (float) data_get($targetScore, 'performance.quantity_progress_percentage', 0)
        );

        $scorePercentage = $this->clampPercentage(
            (float) data_get($targetScore, 'performance.score_percentage', 0)
        );

        $targetPercentage = $this->clampPercentage(
            (float) data_get($targetScore, 'target.target_percentage', 0)
        );

        $scoreTargetProgress = $targetPercentage > 0
            ? $this->clampPercentage(($scorePercentage / $targetPercentage) * 100)
            : 100.0;

        $salaryProgress = min($quantityProgress, $scoreTargetProgress);
        $targetMet = (bool) data_get($targetScore, 'performance.target_met', false);

        if ($targetMet) {
            $salaryProgress = 100.0;
        }

        $minimumTasks = max(1, (int) data_get($targetScore, 'target.minimum_tasks', 1));
        $baseSalary = $salary ? (float) $salary->base_salary : null;

        $earnedSalary = $baseSalary !== null
            ? round($baseSalary * ($salaryProgress / 100), 2)
            : null;

        $deductionAmount = $baseSalary !== null && $earnedSalary !== null
            ? round(max($baseSalary - $earnedSalary, 0), 2)
            : null;

        $salaryPerRequiredTask = $baseSalary !== null
            ? round($baseSalary / $minimumTasks, 2)
            : null;

        return [
            'worker' => $targetScore['worker'],
            'month' => $targetScore['month'],
            'period' => $targetScore['period'],
            'salary' => [
                'configured' => $salary !== null,
                'id' => $salary?->id,
                'base_salary' => $baseSalary !== null ? round($baseSalary, 2) : null,
                'currency' => $salary?->currency ?: Salary::DEFAULT_CURRENCY,
                'effective_from' => optional($salary?->effective_from)->toDateString(),
                'notes' => $salary?->notes,
            ],
            'target' => $targetScore['target'],
            'performance' => $targetScore['performance'],
            'payment' => [
                'quantity_progress_percentage' => round($quantityProgress, 2),
                'score_percentage' => round($scorePercentage, 2),
                'target_percentage' => round($targetPercentage, 2),
                'score_target_progress_percentage' => round($scoreTargetProgress, 2),
                'salary_progress_percentage' => round($salaryProgress, 2),
                'salary_per_required_task' => $salaryPerRequiredTask,
                'earned_salary' => $earnedSalary,
                'deduction_amount' => $deductionAmount,
                'full_salary_earned' => $salary !== null && $targetMet,
                'status' => $salary === null
                    ? 'salary_not_configured'
                    : ($targetMet ? 'full_salary_earned' : 'partial_salary_earned'),
            ],
            'tasks' => $targetScore['tasks'],
        ];
    }

    /**
     * Find the latest salary that is effective on or before the selected month.
     */
    private function resolveSalaryForMonth(User $worker, Carbon $monthStart): ?Salary
    {
        return Salary::query()
            ->where('user_id', $worker->id)
            ->whereDate('effective_from', '<=', $monthStart->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Validation rules for creating or replacing a salary.
     */
    private function salaryRules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'effective_from' => ['required', 'date_format:Y-m'],
            'base_salary' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Resolve workers visible to the current user.
     */
    private function resolveWorkers(User $authUser, ?int $requestedUserId): Collection
    {
        if ($this->isWorker($authUser)) {
            return collect([$authUser->loadMissing('role')]);
        }

        if (!$this->canManageSalaries($authUser)) {
            abort(403, 'You are not allowed to view salary calculations.');
        }

        if ($requestedUserId) {
            $worker = User::with('role')->findOrFail($requestedUserId);
            $this->ensureWorker($worker, 'user_id');

            return collect([$worker]);
        }

        return User::with('role')
            ->where(function ($query) {
                $query
                    ->whereIn('role_id', [4, 5])
                    ->orWhereHas('role', function ($roleQuery) {
                        $roleQuery
                            ->whereIn('slug', ['employee', 'intern'])
                            ->orWhereIn('name', ['Employee', 'Intern']);
                    });
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Only employees and interns can receive a salary record.
     */
    private function ensureWorker(User $user, string $field): void
    {
        if ($this->isWorker($user)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'Only employees and interns can receive salaries.',
        ]);
    }

    /**
     * Check whether a user can access one salary record.
     */
    private function canAccessSalary(?User $authUser, Salary $salary): bool
    {
        if (!$authUser) {
            return false;
        }

        return $this->canManageSalaries($authUser)
            || ($this->isWorker($authUser) && (int) $salary->user_id === (int) $authUser->id);
    }

    /**
     * Salary management is restricted to CEO, MD, and Admin roles.
     */
    private function canManageSalaries(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $role = $this->roleSlug($user);

        return in_array($role, [
            'ceo',
            'chief_executive_officer',
            'md',
            'managing_director',
            'managingdirector',
            'admin',
            'administrator',
            'system_admin',
            'super_admin',
        ], true) || in_array((int) $user->role_id, [1, 2], true);
    }

    /**
     * Is employee or intern.
     */
    private function isWorker(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return in_array($this->roleSlug($user), ['employee', 'intern'], true)
            || in_array((int) $user->role_id, [4, 5], true);
    }

    /**
     * Normalize role names/slugs.
     */
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

    private function clampPercentage(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }
}