<?php

namespace App\Models\home_pages\homepage_section_1_core_solution;

use Illuminate\Database\Eloquent\Model;

class Section1_WebsiteBookingEngine extends Model
{
    protected $table = 'section1_website_booking_engine';
    
    protected $fillable = [
        'icon_image',
        'title',
        'subtitle',
        'description'
    ];
}