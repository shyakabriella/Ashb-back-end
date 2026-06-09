<?php

namespace App\Models\home_pages;

use Illuminate\Database\Eloquent\Model;

class Section3 extends Model
{
    protected $table = 'section3';

    protected $fillable = [
        'left_title',
        'left_description',
        'left_image_url',
        'right_medium_image_url',
        'right_items',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'right_items' => 'array', // This automatically casts JSON to array
    ];
}