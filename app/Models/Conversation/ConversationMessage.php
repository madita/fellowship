<?php

namespace App\Models\Conversation;

use App\Models\User;
use App\Models\Conversation\Conversation;
use Illuminate\Database\Eloquent\Model;

class ConversationMessage extends Model
{
    protected $fillable = [
        'user_id',
        'body'
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
