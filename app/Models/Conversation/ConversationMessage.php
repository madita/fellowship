<?php

namespace App\Models\Conversation;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ConversationMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'body',
        'created_at',
        'updated_at',
    ];

    public function isOwn()
    {
        return $this->user_id === auth()->id();
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
