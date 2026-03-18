<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'property_id',
        'property_name',
        'booking_number',
        'guest_name',
        'guest_email',
        'location',
        'preferred_language',
        'check_in',
        'check_out',
        'nights',
        'total_guests',
        'total_units',
        'currency',
        'total_price',
        'commissionable_amount',
        'commission',
        'arrival_time',
        'pdf_original_name',
        'stored_pdf_path',
        'raw_payload',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'raw_payload' => 'array',
        'total_price' => 'decimal:2',
        'commissionable_amount' => 'decimal:2',
        'commission' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function customers()
    {
        return $this->hasMany(ReservationCustomer::class, 'client_reservation_id');
    }
}