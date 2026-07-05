<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyPlanPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_key',

        'hero_kicker',
        'hero_title',
        'hero_subtitle',

        'tiers',

        'banner_image',
        'banner_title',
        'banner_subtitle',

        'compare_title',
        'comparison_rows',

        'faq_title',
        'faqs',

        'is_active',
    ];

    protected $casts = [
        'tiers' => 'array',
        'comparison_rows' => 'array',
        'faqs' => 'array',
        'is_active' => 'boolean',
    ];
}