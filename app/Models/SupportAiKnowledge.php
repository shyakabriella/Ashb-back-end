<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportAiKnowledge extends Model
{
    use HasFactory;

    protected $table = 'support_ai_knowledge';

    protected $fillable = [
        'title',
        'question',
        'answer',
        'keywords',
        'category',
        'is_active',
        'priority',
        'created_by',
    ];

    protected $casts = [
        'keywords' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];
}