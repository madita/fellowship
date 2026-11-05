<?php

namespace App\Policies;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Who may do what with an album.
 *
 * Whoever made it, and whoever looks after the gallery. The second half
 * used to name "manage-posts", a permission that has never existed -
 * the real one is singular, and the gallery has its own now, so the
 * check was false for everybody and an album could only ever be touched
 * by the member who made it.
 */
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
        // Whoever made it, or whoever looks after the gallery
        return $user->id === $collection->user_id || $user->can('manage-gallery');
    }

    /**
     * Determine whether the user can delete the collection.
     */
    public function delete(User $user, Collection $collection): bool
    {
        return $user->id === $collection->user_id || $user->can('manage-gallery');
    }

    /**
     * Determine whether the user can upload media to the collection.
     */
    public function uploadMedia(User $user, Collection $collection): bool
    {
        return $user->id === $collection->user_id || $user->can('manage-gallery');
    }
}
