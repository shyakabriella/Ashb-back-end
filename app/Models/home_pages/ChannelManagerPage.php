<?php

namespace App\Models\home_pages;

use Illuminate\Database\Eloquent\Model;

class ChannelManagerPage extends Model
{
    protected $table = 'channel_manager_page';

    protected $fillable = [
        // Hero Section
        'hero_title',
        'hero_subtitle',
        'hero_description',
        'hero_button_text',
        
        // Dashboard Stats
        'total_bookings',
        'total_bookings_percentage',
        'revenue',
        'revenue_percentage',
        'ota_status',
        'trust_count',
        'trust_text',
        
        // Section 1: Sync Cards
        'sync_cards',
        
        // Section 2: Zero Errors
        'zero_errors_title',
        'zero_errors_subtitle',
        'zero_errors_description',
        'zero_errors_cards',
        
        // Section 3: Stats Items
        'stats_items',
        
        // Section 4: Sync Engine
        'sync_engine_title',
        'sync_engine_subtitle',
        'sync_engine_description',
        'sync_engine_steps',
        
        // Footer CTA
        'footer_title',
        'footer_description',
        'footer_button_text',
        'footer_icon',  // Added icon field for footer
        
        'is_active',
    ];

    protected $casts = [
        'ota_status' => 'array',
        'sync_cards' => 'array',
        'zero_errors_cards' => 'array',
        'stats_items' => 'array',
        'sync_engine_steps' => 'array',
        'is_active' => 'boolean',
    ];
}