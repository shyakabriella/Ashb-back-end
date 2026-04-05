<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'title',
        'description',
        'milestone',
        'start_at',
        'end_at',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_rewarded' => 'boolean',
    ];

    protected $appends = [
        'is_rewarded',
        'reward_status',
        'ranking',
        'grading',
        'grade',
        'marks_percentage',
        'percentage',
        'rewarded_at',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function workers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_user', 'task_id', 'user_id')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    public function updates(): HasMany
    {
        return $this->hasMany(TaskUpdate::class)->latest();
    }

    public function latestUpdate(): HasOne
    {
        return $this->hasOne(TaskUpdate::class)->latestOfMany();
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(TaskReward::class)->latest('created_at')->latest('id');
    }

    public function latestReward(): HasOne
    {
        return $this->hasOne(TaskReward::class)->latestOfMany('id');
    }

    public function getIsRewardedAttribute(): bool
    {
        if (array_key_exists('is_rewarded', $this->attributes) && $this->attributes['is_rewarded'] !== null) {
            return (bool) $this->attributes['is_rewarded'];
        }

        return $this->resolveLatestReward() !== null || $this->resolveRewards()->isNotEmpty();
    }

    public function getRewardStatusAttribute(): string
    {
        if (array_key_exists('reward_status', $this->attributes) && filled($this->attributes['reward_status'])) {
            return (string) $this->attributes['reward_status'];
        }

        return $this->is_rewarded ? 'graded' : 'not_rewarded';
    }

    public function getRankingAttribute(): ?string
    {
        if (array_key_exists('ranking', $this->attributes) && filled($this->attributes['ranking'])) {
            return $this->attributes['ranking'];
        }

        return $this->resolveLatestReward()?->ranking;
    }

    public function getGradingAttribute(): ?string
    {
        if (array_key_exists('grading', $this->attributes) && filled($this->attributes['grading'])) {
            return $this->attributes['grading'];
        }

        $reward = $this->resolveLatestReward();

        return $reward?->grading ?: $reward?->ranking_label ?: null;
    }

    public function getGradeAttribute(): ?string
    {
        if (array_key_exists('grade', $this->attributes) && filled($this->attributes['grade'])) {
            return $this->attributes['grade'];
        }

        return $this->grading;
    }

    public function getMarksPercentageAttribute(): ?int
    {
        if (array_key_exists('marks_percentage', $this->attributes) && $this->attributes['marks_percentage'] !== null && $this->attributes['marks_percentage'] !== '') {
            return (int) $this->attributes['marks_percentage'];
        }

        $reward = $this->resolveLatestReward();

        if (!$reward) {
            return null;
        }

        if ($reward->marks_percentage !== null && $reward->marks_percentage !== '') {
            return (int) $reward->marks_percentage;
        }

        return null;
    }

    public function getPercentageAttribute(): ?int
    {
        if (array_key_exists('percentage', $this->attributes) && $this->attributes['percentage'] !== null && $this->attributes['percentage'] !== '') {
            return (int) $this->attributes['percentage'];
        }

        return $this->marks_percentage;
    }

    public function getRewardedAtAttribute(): ?string
    {
        if (array_key_exists('rewarded_at', $this->attributes) && filled($this->attributes['rewarded_at'])) {
            return (string) $this->attributes['rewarded_at'];
        }

        $reward = $this->resolveLatestReward();

        return optional($reward?->created_at)->toDateTimeString();
    }

    protected function resolveLatestReward(): ?TaskReward
    {
        if ($this->relationLoaded('latestReward')) {
            return $this->getRelation('latestReward');
        }

        if ($this->relationLoaded('rewards')) {
            /** @var \Illuminate\Support\Collection<int, TaskReward> $rewards */
            $rewards = $this->getRelation('rewards');

            return $rewards
                ->sortByDesc(fn (TaskReward $reward) => optional($reward->created_at)->getTimestamp() ?? 0)
                ->sortByDesc('id')
                ->first();
        }

        return $this->latestReward()->first();
    }

    protected function resolveRewards(): Collection
    {
        if ($this->relationLoaded('rewards')) {
            return $this->getRelation('rewards');
        }

        return $this->rewards()->get();
    }
}