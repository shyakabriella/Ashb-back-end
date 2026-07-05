<?php

namespace App\Models\home_pages;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HeroSection extends Model
{
    use HasFactory;

    protected $table = 'home_pages_hero_sections';

    protected $fillable = [
        'title',
        'subtitle',
        'description',
        'button_text',
        'media_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Accessor for full media URL
    public function getMediaUrlAttribute($value)
    {
        if (!$value) {
            return null;
        }
        
        // If it's already a full URL, return as is
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        
        // Otherwise, prepend asset URL
        return asset($value);
    }
}