<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_code',
        'property_id',
        'property_name',
        'requested_by',
        'reviewed_by',
        'expense_id',
        'request_type',
        'title',
        'description',
        'amount',
        'priority',
        'status',
        'expected_date',
        'review_note',
        'reviewed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expected_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}