<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'body',
    ];

    protected $appends = [
        'selfOwned',
    ];

    public function getSelfOwnedAttribute()
    {
        /** @var User $user */
        $user = auth()->user();

        return $this->user_id === $user->id;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
