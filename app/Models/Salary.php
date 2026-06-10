<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Salary extends Model
{
    use HasFactory;

    public const DEFAULT_CURRENCY = 'RWF';

    protected $fillable = [
        'user_id',
        'effective_from',
        'base_salary',
        'currency',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'base_salary' => 'decimal:2',
    ];

    /**
     * Employee or intern receiving this salary.
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Admin, CEO, or MD who saved this salary.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Store the effective month as the first day of that month.
     */
    public function setEffectiveFromAttribute(mixed $value): void
    {
        $this->attributes['effective_from'] = Carbon::parse($value)
            ->startOfMonth()
            ->toDateString();
    }

    /**
     * Salary records that can apply to the selected month.
     */
    public function scopeEffectiveForMonth(Builder $query, mixed $month): Builder
    {
        $monthStart = Carbon::parse($month)->startOfMonth()->toDateString();

        return $query->whereDate('effective_from', '<=', $monthStart);
    }
}