<?php

use App\Models\Conversation\Conversation;
use Illuminate\Support\Facades\Broadcast;

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

// User private channel (singular - used by frontend)
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// User private channel (plural - legacy support)
Broadcast::channel('users.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('app', function ($user) {
    return $user;
});

Broadcast::channel('chat', function ($user) {
    // Return a plain array to ensure presence member data is serialized correctly
    return [
        'id'       => $user->id,
        'username' => $user->username ?? ($user->name ?? ''),
        'avatar'   => method_exists($user, 'getAttribute') ? $user->getAttribute('avatar') : ($user->avatar ?? null),
    ];
});

Broadcast::channel('conversations.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::where('uuid', $conversationId)->first();

    return $conversation && $user->inConversation($conversation->id);
});

// Sandbox collaboration presence channel
Broadcast::channel('sandbox.{sandboxId}', function ($user, $sandboxId) {
    $sandbox = \App\Models\Sandbox\Sandbox::find($sandboxId);

    if (!$sandbox || !$sandbox->canView($user)) {
        return false;
    }

    return [
        'id' => $user->id,
        'username' => $user->username,
        'role' => $sandbox->getUserRole($user),
        'canEdit' => $sandbox->canEdit($user),
    ];
});
