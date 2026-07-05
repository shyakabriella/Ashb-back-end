<?php

namespace App\Models\home_pages;

use Illuminate\Database\Eloquent\Model;

class OTASDistributionPage extends Model
{
    protected $table = 'otas_distribution_page';

    protected $fillable = [
        'hero_title',
        'hero_subtitle',
        'hero_description',
        'hero_button1_text',
        'hero_button2_text',
        'hero_image',
        'platforms_section_title',
        'platforms',
        'why_choose_section_title',
        'why_choose_section_description',
        'why_choose_items',
        'cta_title',
        'cta_description',
        'cta_button_text',
        'is_active',
    ];

    protected $casts = [
        'platforms' => 'array',
        'why_choose_items' => 'array',
        'is_active' => 'boolean',
    ];
}