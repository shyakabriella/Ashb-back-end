<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskReportCache extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_type',
        'cache_key',
        'scope',
        'user_id',
        'from_date',
        'to_date',
        'payload',
        'workers_count',
        'tasks_count',
        'generated_at',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'payload' => 'array',
        'workers_count' => 'integer',
        'tasks_count' => 'integer',
        'generated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}