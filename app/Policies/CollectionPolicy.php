<?php

namespace App\Policies;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CollectionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any collections.
     */
    public function viewAny(?User $user): bool
    {
        return true; // Collections are publicly viewable
    }

    /**
     * Determine whether the user can view the collection.
     */
    public function view(?User $user, Collection $collection): bool
    {
        return true; // Collections are publicly viewable
    }

    /**
     * Determine whether the user can create collections.
     */
    public function create(User $user): bool
    {
        return true; // Any authenticated user can create collections
    }

    /**
     * Determine whether the user can update the collection.
     */
    public function update(User $user, Collection $collection): bool
    {
        // Owner or admin can update
        return $user->id === $collection->user_id || $user->can('manage-posts');
    }

    /**
     * Determine whether the user can delete the collection.
     */
    public function delete(User $user, Collection $collection): bool
    {
        // Owner or admin can delete
        return $user->id === $collection->user_id || $user->can('manage-posts');
    }

    /**
     * Determine whether the user can upload media to the collection.
     */
    public function uploadMedia(User $user, Collection $collection): bool
    {
        // Owner or admin can upload media
        return $user->id === $collection->user_id || $user->can('manage-posts');
    }
}
