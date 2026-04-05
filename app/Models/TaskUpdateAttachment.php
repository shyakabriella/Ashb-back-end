<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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
        'public_url',
    ];

    public function taskUpdate(): BelongsTo
    {
        return $this->belongsTo(TaskUpdate::class);
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(TaskReward::class, 'attachment_id')->latest('created_at')->latest('id');
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->resolvePublicUrl(
            $this->attributes['file_path'] ?? null,
            $this->attributes['disk'] ?? 'public'
        );
    }

    public function getPublicUrlAttribute(): ?string
    {
        return $this->resolvePublicUrl(
            $this->attributes['file_path'] ?? null,
            $this->attributes['disk'] ?? 'public'
        );
    }

    protected function resolvePublicUrl(?string $path, ?string $disk = 'public'): ?string
    {
        $raw = trim((string) $path);

        if ($raw === '') {
            return null;
        }

        if (Str::startsWith($raw, ['http://', 'https://', '//', 'blob:', 'data:'])) {
            return $raw;
        }

        $normalized = str_replace('\\', '/', $raw);
        $normalized = ltrim($normalized, '/');

        if (Str::startsWith($normalized, 'storage/app/public/')) {
            $normalized = 'storage/' . Str::after($normalized, 'storage/app/public/');
            return asset($normalized);
        }

        if (Str::startsWith($normalized, 'public/storage/')) {
            $normalized = 'storage/' . Str::after($normalized, 'public/storage/');
            return asset($normalized);
        }

        if ($disk === 'public') {
            if (Str::startsWith($normalized, 'storage/')) {
                return asset($normalized);
            }

            if (!Str::contains($normalized, '/')) {
                return null;
            }

            return asset('storage/' . $normalized);
        }

        return asset($normalized);
    }
}