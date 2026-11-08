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
     *
     * Any member could always make an album, and still can: the `user`
     * role holds create-gallery, which is the same rule with a name on
     * it - and a name is what lets it be taken off one person.
     */
    public function create(User $user): bool
    {
        return $user->can('create-gallery');
    }

    /**
     * Determine whether the user can update the collection.
     *
     * Their own album, or somebody trusted with other people's.
     * manage-gallery satisfies each of these, so whoever looked after
     * the gallery as a whole is unaffected.
     */
    public function update(User $user, Collection $collection): bool
    {
        return $user->id === $collection->user_id || $user->can('edit-gallery');
    }

    /**
     * Determine whether the user can delete the collection.
     */
    public function delete(User $user, Collection $collection): bool
    {
        return $user->id === $collection->user_id || $user->can('delete-gallery');
    }

    /**
     * Determine whether the user can upload media to the collection.
     *
     * Adding pictures to an album is editing it.
     */
    public function uploadMedia(User $user, Collection $collection): bool
    {
        return $user->id === $collection->user_id || $user->can('edit-gallery');
    }
}
