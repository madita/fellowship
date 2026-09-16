<?php

namespace App\Policies;

use App\Models\Irc\IrcConnection;
use App\Models\User;

class IrcConnectionPolicy
{
    public function view(User $user, IrcConnection $connection): bool
    {
        return $user->id === $connection->user_id;
    }

    public function update(User $user, IrcConnection $connection): bool
    {
        return $user->id === $connection->user_id;
    }

    public function delete(User $user, IrcConnection $connection): bool
    {
        return $user->id === $connection->user_id;
    }
}
