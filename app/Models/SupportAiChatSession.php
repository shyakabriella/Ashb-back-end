<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportAiChatSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'visitor_name',
        'visitor_email',
        'visitor_hotel',
        'source',
        'status',
        'ip_address',
        'user_agent',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(SupportAiChatMessage::class);
    }
}