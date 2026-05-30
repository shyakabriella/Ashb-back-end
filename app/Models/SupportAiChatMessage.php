<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportAiChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_ai_chat_session_id',
        'sender',
        'message',
        'matched_knowledge_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function session()
    {
        return $this->belongsTo(SupportAiChatSession::class, 'support_ai_chat_session_id');
    }

    public function matchedKnowledge()
    {
        return $this->belongsTo(SupportAiKnowledge::class, 'matched_knowledge_id');
    }
}