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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SalaryController extends Controller
{
    public function __construct(
        private readonly TargetScoreService $targetScoreService
    ) {
    }

    /**
     * List calculated monthly payroll salaries.
     *
     * Rules:
     * - Employee/Intern sees only their own salary calculation.
     * - CEO/MD/Admin sees all active workers or one selected worker.
     * - Salary is earned from completed AND rewarded tasks only.
     * - A completed task without reward does not increase payroll salary.
     *
     * Query params:
     * - from_date=2026-07-01
     * - to_date=2026-07-31
     * - month=2026-07 (fallback/backward compatibility)
     * - user_id=5
     * - include_tasks=1
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
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'include_tasks' => ['nullable', 'boolean'],
        ]);

        [$fromDate, $toDate] = $this->resolvePayrollDateRange($validated);
        $requestedUserId = isset($validated['user_id'])
            ? (int) $validated['user_id']
            : null;

        $workers = $this->resolveWorkers($authUser, $requestedUserId);
        $includeTasks = $request->boolean('include_tasks') && $workers->count() === 1;

        $calculations = $workers
            ->map(fn (User $worker) => $this->calculateForWorker(
                worker: $worker,
                fromDate: $fromDate,
                toDate: $toDate,
                includeTasks: $includeTasks
            ))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Monthly salary calculations fetched successfully.',
            'data' => [
                'month' => $fromDate->format('Y-m'),
                'from_date' => $fromDate->toDateString(),
                'to_date' => $toDate->toDateString(),
                'period' => [
                    'from' => $fromDate->toDateString(),
                    'to' => $toDate->toDateString(),
                    'label' => $fromDate->format('M d, Y') . ' - ' . $toDate->format('M d, Y'),
                ],
                'workers_count' => $calculations->count(),
                'summary' => $this->buildPayrollSummary($calculations),
                'calculations' => $calculations,
            ],
        ]);
    }

    /**
     * Create or replace one worker's salary effective from a selected month.
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
        $this->ensureActiveWorker($worker, 'user_id');

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
                fromDate: Carbon::parse($salary->effective_from)->startOfMonth(),
                toDate: Carbon::parse($salary->effective_from)->endOfMonth(),
                includeTasks: true
            ),
        ], 201);
    }

    /**
     * Show one salary record with calculated payment for a selected month.
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
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'include_tasks' => ['nullable', 'boolean'],
        ]);

        [$fromDate, $toDate] = $this->resolvePayrollDateRange(
            $validated,
            Carbon::parse($salary->effective_from)->startOfMonth()
        );

        return response()->json([
            'success' => true,
            'message' => 'Employee salary fetched successfully.',
            'data' => $this->calculateForWorker(
                worker: $salary->worker,
                fromDate: $fromDate,
                toDate: $toDate,
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
                fromDate: Carbon::parse($salary->effective_from)->startOfMonth(),
                toDate: Carbon::parse($salary->effective_from)->endOfMonth(),
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
     * Calculate salary earned from completed AND rewarded tasks.
     *
     * Salary progress uses the lower value between:
     * - rewarded task quantity progress: rewarded completed tasks / minimum tasks
     * - reward score progress: average reward marks / target percentage
     */
    private function calculateForWorker(
        User $worker,
        Carbon $fromDate,
        Carbon $toDate,
        bool $includeTasks
    ): array {
        $fromDate = $fromDate->copy()->startOfDay();
        $toDate = $toDate->copy()->endOfDay();
        $monthStart = $fromDate->copy()->startOfMonth()->startOfDay();

        $targetScore = $this->targetScoreService->calculateForWorker(
            worker: $worker,
            month: $monthStart,
            includeTasks: $includeTasks
        );

        $salary = $this->resolveSalaryForMonth($worker, $monthStart);

        $minimumTasks = max(1, (int) data_get($targetScore, 'target.minimum_tasks', 1));
        $targetPercentage = $this->clampPercentage(
            (float) data_get($targetScore, 'target.target_percentage', 0)
        );

        $completedStats = $this->completedTaskStats(
            worker: $worker,
            fromDate: $fromDate,
            toDate: $toDate,
            includeTasks: $includeTasks
        );

        $rewardStats = $this->rewardedCompletedTaskStats(
            worker: $worker,
            fromDate: $fromDate,
            toDate: $toDate,
            includeTasks: $includeTasks
        );

        $completedTasksCount = (int) $completedStats['completed_tasks_count'];

        $rewardedCompletedTasks = (int) $rewardStats['rewarded_completed_tasks_count'];
        $rewardAverageMarks = $this->clampPercentage((float) $rewardStats['reward_average_marks_percentage']);

        $rewardedQuantityProgress = $this->clampPercentage(
            ($rewardedCompletedTasks / $minimumTasks) * 100
        );

        $rewardScoreTargetProgress = $targetPercentage > 0
            ? $this->clampPercentage(($rewardAverageMarks / $targetPercentage) * 100)
            : ($rewardedCompletedTasks > 0 ? 100.0 : 0.0);

        $salaryProgress = min($rewardedQuantityProgress, $rewardScoreTargetProgress);

        $fullSalaryEarned = $salary !== null
            && $rewardedCompletedTasks >= $minimumTasks
            && ($targetPercentage <= 0 || $rewardAverageMarks >= $targetPercentage);

        if ($fullSalaryEarned) {
            $salaryProgress = 100.0;
        }

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

        $performance = is_array($targetScore['performance'] ?? null)
            ? $targetScore['performance']
            : [];

        $performance['completed_tasks_count'] = $completedTasksCount;
        $performance['rewarded_completed_tasks_count'] = $rewardedCompletedTasks;
        $performance['unrewarded_completed_tasks_count'] = max($completedTasksCount - $rewardedCompletedTasks, 0);
        $performance['reward_average_marks_percentage'] = round($rewardAverageMarks, 2);
        $performance['rewarded_quantity_progress_percentage'] = round($rewardedQuantityProgress, 2);
        $performance['reward_score_target_progress_percentage'] = round($rewardScoreTargetProgress, 2);
        $performance['salary_rule'] = 'completed_and_rewarded_tasks_only';
        $performance['target_met'] = $fullSalaryEarned;

        return [
            'worker' => $targetScore['worker'],
            'month' => $fromDate->format('Y-m'),
            'period' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
                'label' => $fromDate->format('M d, Y') . ' - ' . $toDate->format('M d, Y'),
            ],
            'salary' => [
                'configured' => $salary !== null,
                'id' => $salary?->id,
                'base_salary' => $baseSalary !== null ? round($baseSalary, 2) : null,
                'currency' => $salary?->currency ?: Salary::DEFAULT_CURRENCY,
                'effective_from' => $salary?->effective_from
                    ? Carbon::parse($salary->effective_from)->toDateString()
                    : null,
                'notes' => $salary?->notes,
            ],
            'target' => $targetScore['target'],
            'performance' => $performance,
            'payment' => [
                'quantity_progress_percentage' => round($rewardedQuantityProgress, 2),
                'score_percentage' => round($rewardAverageMarks, 2),
                'target_percentage' => round($targetPercentage, 2),
                'score_target_progress_percentage' => round($rewardScoreTargetProgress, 2),
                'salary_progress_percentage' => round($salaryProgress, 2),
                'salary_per_required_task' => $salaryPerRequiredTask,
                'earned_salary' => $earnedSalary,
                'deduction_amount' => $deductionAmount,
                'full_salary_earned' => $fullSalaryEarned,
                'status' => $salary === null
                    ? 'salary_not_configured'
                    : ($fullSalaryEarned ? 'full_salary_earned' : 'partial_salary_earned'),
                'rule' => 'Salary is calculated from tasks that are both completed and rewarded within the selected date range.',
            ],
            'tasks' => $includeTasks ? $completedStats['tasks'] : [],
            'rewarded_tasks' => $includeTasks ? $rewardStats['tasks'] : [],
        ];
    }

    /**
     * Get completed tasks assigned to the selected worker for the selected date range.
     */
    private function completedTaskStats(
        User $worker,
        Carbon $fromDate,
        Carbon $toDate,
        bool $includeTasks
    ): array {
        if (!Schema::hasTable('tasks') || !Schema::hasTable('task_user')) {
            return [
                'completed_tasks_count' => 0,
                'tasks' => [],
            ];
        }

        $rows = DB::table('tasks')
            ->join('task_user', 'task_user.task_id', '=', 'tasks.id')
            ->where('task_user.user_id', $worker->id)
            ->whereRaw('LOWER(tasks.status) = ?', ['completed'])
            ->where(function ($dateQuery) use ($fromDate, $toDate) {
                $dateQuery
                    ->whereBetween('tasks.end_at', [
                        $fromDate->toDateTimeString(),
                        $toDate->toDateTimeString(),
                    ])
                    ->orWhere(function ($fallbackQuery) use ($fromDate, $toDate) {
                        $fallbackQuery
                            ->whereNull('tasks.end_at')
                            ->whereBetween('tasks.updated_at', [
                                $fromDate->toDateTimeString(),
                                $toDate->toDateTimeString(),
                            ]);
                    });
            })
            ->select([
                'tasks.id as task_id',
                'tasks.title as task_title',
                'tasks.start_at',
                'tasks.end_at',
                'tasks.status',
            ])
            ->groupBy('tasks.id', 'tasks.title', 'tasks.start_at', 'tasks.end_at', 'tasks.status')
            ->orderBy('tasks.end_at')
            ->get();

        return [
            'completed_tasks_count' => $rows->count(),
            'tasks' => $includeTasks
                ? $rows->map(fn ($row) => [
                    'task_id' => (int) $row->task_id,
                    'title' => $row->task_title,
                    'start_at' => $row->start_at,
                    'end_at' => $row->end_at,
                    'status' => $row->status,
                ])->values()->all()
                : [],
        ];
    }

    /**
     * Get completed tasks that also have a saved reward for the selected worker.
     */
    private function rewardedCompletedTaskStats(
        User $worker,
        Carbon $fromDate,
        Carbon $toDate,
        bool $includeTasks
    ): array {
        if (!Schema::hasTable('task_rewards') || !Schema::hasTable('tasks')) {
            return [
                'rewarded_completed_tasks_count' => 0,
                'reward_average_marks_percentage' => 0.0,
                'tasks' => [],
            ];
        }

        $query = DB::table('task_rewards')
            ->join('tasks', 'tasks.id', '=', 'task_rewards.task_id')
            ->where('task_rewards.recipient_user_id', $worker->id)
            ->whereRaw('LOWER(tasks.status) = ?', ['completed'])
            ->where(function ($dateQuery) use ($fromDate, $toDate) {
                $dateQuery
                    ->whereBetween('tasks.end_at', [
                        $fromDate->toDateTimeString(),
                        $toDate->toDateTimeString(),
                    ])
                    ->orWhere(function ($fallbackQuery) use ($fromDate, $toDate) {
                        $fallbackQuery
                            ->whereNull('tasks.end_at')
                            ->whereBetween('tasks.updated_at', [
                                $fromDate->toDateTimeString(),
                                $toDate->toDateTimeString(),
                            ]);
                    });
            })
            ->select([
                'tasks.id as task_id',
                'tasks.title as task_title',
                'tasks.start_at',
                'tasks.end_at',
                DB::raw('MAX(task_rewards.marks_percentage) as marks_percentage'),
                DB::raw('MAX(COALESCE(task_rewards.ranking_label, task_rewards.grading, task_rewards.ranking)) as ranking_label'),
            ])
            ->groupBy('tasks.id', 'tasks.title', 'tasks.start_at', 'tasks.end_at')
            ->orderBy('tasks.end_at');

        $rows = $query->get();

        $count = $rows->count();
        $averageMarks = $count > 0
            ? round((float) $rows->avg(fn ($row) => (float) ($row->marks_percentage ?? 0)), 2)
            : 0.0;

        return [
            'rewarded_completed_tasks_count' => $count,
            'reward_average_marks_percentage' => $averageMarks,
            'tasks' => $includeTasks
                ? $rows->map(fn ($row) => [
                    'task_id' => (int) $row->task_id,
                    'title' => $row->task_title,
                    'start_at' => $row->start_at,
                    'end_at' => $row->end_at,
                    'marks_percentage' => round((float) ($row->marks_percentage ?? 0), 2),
                    'ranking_label' => $row->ranking_label,
                ])->values()->all()
                : [],
        ];
    }

    /**
     * Resolve payroll filter range.
     * from_date/to_date are preferred; month remains for backward compatibility.
     */
    private function resolvePayrollDateRange(array $validated, ?Carbon $fallbackMonth = null): array
    {
        $fromInput = $validated['from_date'] ?? $validated['date_from'] ?? null;
        $toInput = $validated['to_date'] ?? $validated['date_to'] ?? null;

        if (filled($fromInput) || filled($toInput)) {
            $fromDate = filled($fromInput)
                ? Carbon::parse((string) $fromInput)->startOfDay()
                : Carbon::parse((string) $toInput)->startOfDay();

            $toDate = filled($toInput)
                ? Carbon::parse((string) $toInput)->endOfDay()
                : Carbon::parse((string) $fromInput)->endOfDay();

            if ($toDate->lt($fromDate)) {
                throw ValidationException::withMessages([
                    'to_date' => 'To date must be after or equal to From date.',
                ]);
            }

            return [$fromDate, $toDate];
        }

        $month = $validated['month'] ?? null;
        $monthStart = filled($month)
            ? Carbon::parse($month . '-01')->startOfMonth()->startOfDay()
            : ($fallbackMonth ? $fallbackMonth->copy()->startOfMonth()->startOfDay() : now()->startOfMonth()->startOfDay());

        return [$monthStart, $monthStart->copy()->endOfMonth()->endOfDay()];
    }

    /**
     * Find the latest salary effective on or before selected month.
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
     * Summary for payroll page cards and PDF report.
     */
    private function buildPayrollSummary(Collection $calculations): array
    {
        return [
            'workers_count' => $calculations->count(),
            'salary_configured_count' => $calculations
                ->filter(fn ($row) => (bool) data_get($row, 'salary.configured'))
                ->count(),
            'total_base_salary' => round((float) $calculations->sum(fn ($row) => (float) data_get($row, 'salary.base_salary', 0)), 2),
            'total_earned_salary' => round((float) $calculations->sum(fn ($row) => (float) data_get($row, 'payment.earned_salary', 0)), 2),
            'total_deduction_amount' => round((float) $calculations->sum(fn ($row) => (float) data_get($row, 'payment.deduction_amount', 0)), 2),
            'completed_tasks_count' => (int) $calculations->sum(fn ($row) => (int) data_get($row, 'performance.completed_tasks_count', 0)),
            'rewarded_completed_tasks_count' => (int) $calculations->sum(fn ($row) => (int) data_get($row, 'performance.rewarded_completed_tasks_count', 0)),
            'full_salary_count' => $calculations
                ->filter(fn ($row) => (bool) data_get($row, 'payment.full_salary_earned'))
                ->count(),
            'partial_salary_count' => $calculations
                ->filter(fn ($row) => data_get($row, 'payment.status') === 'partial_salary_earned')
                ->count(),
            'salary_not_configured_count' => $calculations
                ->filter(fn ($row) => data_get($row, 'payment.status') === 'salary_not_configured')
                ->count(),
            'salary_rule' => 'Salary is calculated from tasks that are completed and rewarded within the selected date range.',
        ];
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
     * Resolve active workers visible to current user.
     */
    private function resolveWorkers(User $authUser, ?int $requestedUserId): Collection
    {
        if ($this->isWorker($authUser)) {
            $this->ensureActiveWorker($authUser, 'user_id');

            return collect([$authUser->loadMissing('role')]);
        }

        if (!$this->canManageSalaries($authUser)) {
            abort(403, 'You are not allowed to view salary calculations.');
        }

        if ($requestedUserId) {
            $worker = User::with('role')->findOrFail($requestedUserId);
            $this->ensureWorker($worker, 'user_id');
            $this->ensureActiveWorker($worker, 'user_id');

            return collect([$worker]);
        }

        return User::with('role')
            ->where('is_active', true)
            ->where(function ($query) {
                $query
                    ->whereIn('role_id', [4, 5])
                    ->orWhereHas('role', function ($roleQuery) {
                        $roleQuery
                            ->whereIn('slug', ['employee', 'intern'])
                            ->orWhereIn('name', ['Employee', 'Intern']);
                    });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Only employees and interns can receive salary payroll calculation.
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
     * Payroll should include active workers only.
     */
    private function ensureActiveWorker(User $user, string $field): void
    {
        if ((bool) ($user->is_active ?? true)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'Only active workers can be included in payroll.',
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