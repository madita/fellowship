<?php

namespace App\Traits;

use App\Models\User;
use App\Models\Watch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Lets members follow this model and be told when it moves on.
 *
 * Replaces the per-feature pairs that came before — forum thread
 * subscriptions and ticket watchers — which were the same thing twice with
 * different names.
 *
 * A model that limits who may see it should also define
 * `isVisibleTo(?User $user): bool`; notifyWatchers() and the watching list
 * both honour it, so losing access to something stops the notices as well.
 */
trait Watchable
{
    /**
     * A morph has no foreign key to cascade through, so the rows are cleared
     * here. Only on a real delete: a soft-deleted thread that comes back
     * should come back with its watchers, which is how HasRelateableContent
     * treats links too.
     */
    public static function bootWatchable(): void
    {
        static::deleted(function (Model $model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->watches()->delete();
        });
    }

    public function watches(): MorphMany
    {
        return $this->morphMany(Watch::class, 'watchable');
    }

    /**
     * The members watching this, as users — so withCount('watchers') and
     * withExists() read naturally in the feature queries.
     */
    public function watchers(): MorphToMany
    {
        return $this->morphToMany(User::class, 'watchable', 'watches', null, 'user_id');
    }

    public function isWatchedBy(?User $user): bool
    {
        if ( ! $user) {
            return false;
        }

        return $this->watches()->where('user_id', $user->id)->exists();
    }

    /**
     * Start watching. Does nothing when already watching, so the
     * auto-watch on posting or commenting can be called freely.
     */
    public function watch(User $user): void
    {
        $this->watches()->firstOrCreate(['user_id' => $user->id]);
    }

    public function unwatch(User $user): void
    {
        $this->watches()->where('user_id', $user->id)->delete();
    }

    /**
     * Start or stop watching; returns whether the member watches it now.
     */
    public function toggleWatch(User $user): bool
    {
        if ($this->watches()->where('user_id', $user->id)->delete()) {
            return false;
        }

        $this->watch($user);

        return true;
    }

    /**
     * Tell the watchers something happened.
     *
     * Skips the member who caused it — nobody needs telling about their own
     * reply — and anyone who can no longer see the thing they are watching.
     */
    public function notifyWatchers(Notification $notification, int|array|null $exceptUserIds = null): void
    {
        NotificationFacade::send($this->watcherRecipients($exceptUserIds), $notification);
    }

    /**
     * Who notifyWatchers() would write to. Exposed because the forum needs
     * the same list to decide who has already been told, so a member who is
     * both mentioned and watching gets one notice rather than two.
     */
    public function watcherRecipients(int|array|null $exceptUserIds = null)
    {
        $except = array_filter((array) $exceptUserIds);

        return User::query()
            ->whereIn('id', $this->watches()
                ->when($except, fn ($query) => $query->whereNotIn('user_id', $except))
                ->select('user_id'))
            ->get()
            ->filter(fn (User $user) => $this->isWatchableBy($user));
    }

    /**
     * Whether this model is still readable by the member. Defers to the
     * model's own rule where it has one; anything that does not restrict
     * access is readable by everyone.
     */
    public function isWatchableBy(?User $user): bool
    {
        if (method_exists($this, 'isVisibleTo')) {
            return (bool) $this->isVisibleTo($user);
        }

        return true;
    }

    /**
     * How this reads on the watching list. A model with somewhere better to
     * get these from overrides them — the @mention methods answer the same
     * question, so they are used where a model already has them.
     */
    public function watchTitle(): ?string
    {
        if (method_exists($this, 'mentionTitle')) {
            return $this->mentionTitle();
        }

        return $this->title ?? null;
    }

    public function watchUrl(): ?string
    {
        if (method_exists($this, 'mentionUrl')) {
            return $this->mentionUrl();
        }

        return $this->url ?? null;
    }
}
