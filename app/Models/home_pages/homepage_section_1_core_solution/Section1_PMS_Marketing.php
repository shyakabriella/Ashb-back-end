<?php

namespace App\Models\home_pages\homepage_section_1_core_solution;

use Illuminate\Database\Eloquent\Model;

class Section1_PMS_Marketing extends Model
{
    protected $table = 'section1_pms_marketing';
    
    protected $fillable = [
        'icon_image',
        'title',
        'subtitle',
        'description'
    ];
}