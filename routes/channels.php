<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation\Conversation;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('users.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('app', function ($user) {
    return $user;
});

Broadcast::channel('chat', function ($user) {
//    return [
//        'id' => $user->id,
//        'username' => $user->username
//    ];
    return $user;
});

Broadcast::channel('conversations.{conversationId}', function ($user, $conversationId) {
    //dd($conversationId);
    $conversation = Conversation::where('uuid', $conversationId)->first();

    //return $user->isInConversation(\App\Models\Conversation\Conversation::find($conversationId));
    return $user->inConversation($conversation->id);
});
