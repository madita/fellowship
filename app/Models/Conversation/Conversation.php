<?php

namespace App\Models\Conversation;

use App\Models\User;
use App\Models\Conversation\ConversationMessage;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = [
        'last_message_at',
        'uuid'
    ];

    protected $dates = [
        'last_message_at'
    ];

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('read_at')
            ->withTimestamps()
            ->oldest();
    }

    public function others()
    {
        return $this->users()->where('user_id', '!=', auth()->id());
    }

    public function messages()
    {
//        return $this->hasMany(ConversationMessage::class)
//            ->latest();
        return $this->hasMany(ConversationMessage::class)
            ->offset(0)->limit(20)->latest();
    }


    /*public function touchLastMessageAt()
    {
        $this->last_message_at = \Carbon\Carbon::now();
        $this->save();
    }*/

   /* public function isReply()
    {
        return $this->parent_id !== null;
    }*/

   /* public function scopeLatestFirst($query)
    {
        return $query->orderBy('created_at', 'desc');
    }*/
}
