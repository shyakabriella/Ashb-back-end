<?php

namespace App\Services;

use App\Models\Target;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TargetScoreService
{
    /**
     * Get an employee/intern target for a month.
     * If no row exists, create the default 30-task / 75% target automatically.
     */
    public function getOrCreateTarget(User $worker, Carbon|string|null $month = null): Target
    {
        $monthStart = $this->resolveMonthStart($month);

        return Target::query()->firstOrCreate(
            [
                'user_id' => $worker->id,
                'target_month' => $monthStart->toDateString(),
            ],
            [
                'minimum_tasks' => Target::DEFAULT_MINIMUM_TASKS,
                'target_percentage' => Target::DEFAULT_TARGET_PERCENTAGE,
                'maximum_score_per_task' => Target::DEFAULT_MAXIMUM_SCORE_PER_TASK,
            ]
        );
    }

    /**
     * Calculate one worker's monthly target score.
     *
     * Rules:
     * - Count a task only when the worker marked it completed during the month.
     * - Count one score per task per worker.
     * - Use the latest task_rewards row for that task and recipient worker.
     * - Completed but ungraded tasks contribute 0 points until they are graded.
     * - Standard score is capped at 100%, but raw score is also returned.
     * - Target is met only when both the task minimum and score target are reached.
     */
    public function calculateForWorker(
        User $worker,
        Carbon|string|null $month = null,
        bool $includeTasks = true
    ): array {
        $monthStart = $this->resolveMonthStart($month);
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();
        $target = $this->getOrCreateTarget($worker, $monthStart);

        $completedTasks = Task::query()
            ->with('property')
            ->whereHas('workers', function ($query) use ($worker) {
                $query->where('users.id', $worker->id);
            })
            ->whereHas('updates', function ($query) use ($worker, $monthStart, $monthEnd) {
                $query
                    ->where('user_id', $worker->id)
                    ->whereRaw('LOWER(status_to) = ?', ['completed'])
                    ->whereBetween('created_at', [$monthStart, $monthEnd]);
            })
            ->select(['tasks.id', 'tasks.title', 'tasks.property_id'])
            ->distinct()
            ->orderBy('tasks.id')
            ->get();

        $completedTaskIds = $completedTasks
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $latestRewardsByTask = collect();

        if (
            $completedTaskIds->isNotEmpty()
            && Schema::hasTable('task_rewards')
        ) {
            /*
             * Match TaskController behavior: latest reward is determined by
             * created_at, then id is used as a stable tie-breaker.
             */
            $latestRewardsByTask = DB::table('task_rewards')
                ->where('recipient_user_id', $worker->id)
                ->whereIn('task_id', $completedTaskIds->all())
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->unique(fn ($reward) => (int) $reward->task_id)
                ->keyBy(fn ($reward) => (int) $reward->task_id);
        }

        $minimumTasks = max(1, (int) $target->minimum_tasks);
        $maximumScorePerTask = max(1.0, (float) $target->maximum_score_per_task);
        $targetPercentage = max(0.0, min(100.0, (float) $target->target_percentage));

        $monthlyMaximumPoints = $minimumTasks * $maximumScorePerTask;
        $requiredTargetPoints = $monthlyMaximumPoints * ($targetPercentage / 100);

        $taskRows = [];
        $totalMarks = 0.0;
        $gradedTasks = 0;

        foreach ($completedTasks as $task) {
            $reward = $latestRewardsByTask->get((int) $task->id);
            $marks = 0.0;
            $isGraded = false;

            if ($reward && $reward->marks_percentage !== null) {
                $marks = max(0.0, min($maximumScorePerTask, (float) $reward->marks_percentage));
                $isGraded = true;
                $gradedTasks++;
            }

            $totalMarks += $marks;

            if ($includeTasks) {
                $taskRows[] = [
                    'task_id' => (int) $task->id,
                    'task_title' => $task->title ?: 'Untitled Task',
                    'property_id' => $task->property_id,
                    'property_name' => $task->property?->title
                        ?: $task->property?->name
                        ?: 'No property',
                    'graded' => $isGraded,
                    'ranking' => $reward->ranking_label ?? $reward->ranking ?? null,
                    'marks_percentage' => round($marks, 2),
                    'monthly_score_contribution' => round(
                        ($marks / $monthlyMaximumPoints) * 100,
                        2
                    ),
                ];
            }
        }

        $completedCount = $completedTasks->count();
        $ungradedCompletedTasks = max($completedCount - $gradedTasks, 0);

        $rawScorePercentage = $monthlyMaximumPoints > 0
            ? ($totalMarks / $monthlyMaximumPoints) * 100
            : 0.0;

        $scorePercentage = min(100.0, $rawScorePercentage);
        $quantityProgressPercentage = min(100.0, ($completedCount / $minimumTasks) * 100);
        $qualityAveragePercentage = $gradedTasks > 0
            ? $totalMarks / $gradedTasks
            : 0.0;

        $remainingTasks = max($minimumTasks - $completedCount, 0);
        $remainingPoints = max($requiredTargetPoints - $totalMarks, 0.0);
        $perfectTasksNeededForScore = (int) ceil($remainingPoints / $maximumScorePerTask);
        $minimumAdditionalTasksRequired = max($remainingTasks, $perfectTasksNeededForScore);

        $targetMet = $completedCount >= $minimumTasks
            && $scorePercentage >= $targetPercentage;

        return [
            'worker' => [
                'id' => (int) $worker->id,
                'name' => $this->userDisplayName($worker),
                'email' => $worker->email,
            ],
            'month' => $monthStart->format('Y-m'),
            'period' => [
                'from_date' => $monthStart->toDateString(),
                'to_date' => $monthEnd->toDateString(),
            ],
            'target' => [
                'id' => (int) $target->id,
                'minimum_tasks' => $minimumTasks,
                'target_percentage' => round($targetPercentage, 2),
                'maximum_score_per_task' => round($maximumScorePerTask, 2),
                'monthly_maximum_points' => round($monthlyMaximumPoints, 2),
                'required_target_points' => round($requiredTargetPoints, 2),
                'perfect_task_contribution_percentage' => round(100 / $minimumTasks, 2),
            ],
            'performance' => [
                'completed_tasks' => $completedCount,
                'graded_tasks' => $gradedTasks,
                'ungraded_completed_tasks' => $ungradedCompletedTasks,
                'total_marks_earned' => round($totalMarks, 2),
                'raw_score_percentage' => round($rawScorePercentage, 2),
                'score_percentage' => round($scorePercentage, 2),
                'quantity_progress_percentage' => round($quantityProgressPercentage, 2),
                'quality_average_percentage' => round($qualityAveragePercentage, 2),
                'remaining_tasks' => $remainingTasks,
                'remaining_points' => round($remainingPoints, 2),
                'perfect_tasks_needed_for_score' => $perfectTasksNeededForScore,
                'minimum_additional_tasks_required' => $minimumAdditionalTasksRequired,
                'target_met' => $targetMet,
                'status' => $targetMet ? 'target_met' : 'target_not_met',
            ],
            'tasks' => $includeTasks ? $taskRows : [],
        ];
    }

    private function resolveMonthStart(Carbon|string|null $month): Carbon
    {
        if ($month instanceof Carbon) {
            return $month->copy()->startOfMonth()->startOfDay();
        }

        if (is_string($month) && trim($month) !== '') {
            $value = trim($month);

            if (preg_match('/^\d{4}-\d{2}$/', $value)) {
                $value .= '-01';
            }

            return Carbon::parse($value)->startOfMonth()->startOfDay();
        }

        return now()->startOfMonth()->startOfDay();
    }

    private function userDisplayName(User $user): string
    {
        $fullName = trim(implode(' ', array_filter([
            $user->first_name ?? null,
            $user->last_name ?? null,
        ])));

        return $fullName !== ''
            ? $fullName
            : (string) ($user->name ?: $user->email ?: ('User #' . $user->id));
    }
}