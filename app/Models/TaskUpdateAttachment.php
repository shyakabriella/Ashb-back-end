<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TaskUpdateAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_update_id',
        'attachment_type',
        'disk',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    protected $appends = [
        'file_url',
    ];

    public function taskUpdate(): BelongsTo
    {
        return $this->belongsTo(TaskUpdate::class);
    }

    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        return Storage::disk($this->disk ?: 'public')->url($this->file_path);
    }
}