<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationCustomer extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_reservation_id',
        'guest_name',
        'room_label',
        'room_type',
        'occupancy',
        'meal_plan',
        'check_in',
        'check_out',
        'currency',
        'price_per_night',
        'rate_name',
        'source',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'price_per_night' => 'decimal:2',
    ];

    public function reservation()
    {
        return $this->belongsTo(ClientReservation::class, 'client_reservation_id');
    }
}