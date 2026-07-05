<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Target extends Model
{
    use HasFactory;

    public const DEFAULT_MINIMUM_TASKS = 30;
    public const DEFAULT_TARGET_PERCENTAGE = 75.00;
    public const DEFAULT_MAXIMUM_SCORE_PER_TASK = 100.00;

    protected $fillable = [
        'user_id',
        'target_month',
        'minimum_tasks',
        'target_percentage',
        'maximum_score_per_task',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'target_month' => 'date',
        'minimum_tasks' => 'integer',
        'target_percentage' => 'decimal:2',
        'maximum_score_per_task' => 'decimal:2',
    ];

    protected $appends = [
        'monthly_maximum_points',
        'required_target_points',
        'perfect_task_contribution_percentage',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Store every target month as the first day of that month.
     */
    public function setTargetMonthAttribute(mixed $value): void
    {
        $this->attributes['target_month'] = Carbon::parse($value)
            ->startOfMonth()
            ->toDateString();
    }

    public function scopeForMonth(Builder $query, mixed $month): Builder
    {
        return $query->whereDate(
            'target_month',
            Carbon::parse($month)->startOfMonth()->toDateString()
        );
    }

    public function getMonthlyMaximumPointsAttribute(): float
    {
        return round(
            (float) $this->minimum_tasks * (float) $this->maximum_score_per_task,
            2
        );
    }

    public function getRequiredTargetPointsAttribute(): float
    {
        return round(
            $this->monthly_maximum_points * ((float) $this->target_percentage / 100),
            2
        );
    }

    /**
     * With 30 tasks, one task graded at 100% contributes 3.33 points
     * toward the monthly target score.
     */
    public function getPerfectTaskContributionPercentageAttribute(): float
    {
        if ((int) $this->minimum_tasks <= 0) {
            return 0.0;
        }

        return round(100 / (int) $this->minimum_tasks, 2);
    }
}