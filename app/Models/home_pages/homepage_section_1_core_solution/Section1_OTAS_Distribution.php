<?php

namespace App\Models\home_pages\homepage_section_1_core_solution;

use Illuminate\Database\Eloquent\Model;

class Section1_OTAS_Distribution extends Model
{
    protected $table = 'section1_otas_distribution';
    
    protected $fillable = [
        'icon_image',
        'title',
        'subtitle',
        'description'
    ];
}