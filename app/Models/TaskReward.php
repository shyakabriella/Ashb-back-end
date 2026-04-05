<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskReward extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'recipient_user_id',
        'graded_by_user_id',
        'task_update_id',
        'attachment_id',
        'attachment_type',
        'attachment_file_name',
        'attachment_file_path',
        'ranking',
        'ranking_label',
        'marks_percentage',
        'grading',
        'advice',
        'comment',
    ];

    protected $casts = [
        'marks_percentage' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by_user_id');
    }

    public function taskUpdate(): BelongsTo
    {
        return $this->belongsTo(TaskUpdate::class, 'task_update_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(TaskUpdateAttachment::class, 'attachment_id');
    }
}