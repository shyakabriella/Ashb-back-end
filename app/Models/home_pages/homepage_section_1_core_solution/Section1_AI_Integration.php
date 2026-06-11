<?php

namespace App\Models\home_pages\homepage_section_1_core_solution;

use Illuminate\Database\Eloquent\Model;

class Section1_AI_Integration extends Model
{
    protected $table = 'section1_ai_integration';
    
    protected $fillable = [
        'icon_image',
        'title',
        'subtitle',
        'description'
    ];
}