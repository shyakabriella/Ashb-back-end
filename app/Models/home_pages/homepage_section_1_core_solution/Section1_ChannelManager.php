<?php

namespace App\Models\home_pages\homepage_section_1_core_solution;

use Illuminate\Database\Eloquent\Model;

class Section1_ChannelManager extends Model
{
    protected $table = 'section1_channel_manager';
    
    protected $fillable = [
        'icon_image',
        'title',
        'subtitle',
        'description'
    ];
}