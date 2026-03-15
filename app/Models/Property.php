<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'href',
        'image',
        'price',
        'address',
        'location',
        'units',
        'occupancy',
        'status',
        'description',
        'is_favorite',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'units' => 'integer',
        'occupancy' => 'integer',
        'is_favorite' => 'boolean',
    ];
}