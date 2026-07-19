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
        'manager_name',
        'manager_email',
        'property_email',
        'payment_due_day',
        'auto_invoice_enabled',
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
        'payment_due_day' => 'integer',
        'auto_invoice_enabled' => 'boolean',
        'is_favorite' => 'boolean',
    ];

    protected $attributes = [
        'occupancy' => 0,
        'status' => 'available',
        'payment_due_day' => null,
        'auto_invoice_enabled' => true,
        'is_favorite' => false,
    ];

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}