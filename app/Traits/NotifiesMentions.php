<?php

namespace App\Traits;

use App\Models\User;
use App\Notifications\MentionNotification;
use App\Services\MentionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

/**
 * Notifies members written as @username in the content of a model, however
 * the content was saved. Only new mentions are notified, so editing a page
 * does not notify everyone named in it again.
 *
 * A model using this trait declares which fields carry the text and where
 * a mention can be read:
 *
 *     protected array $mentionFields = ['content'];
 *
 *     public function mentionContext(): string { return 'wiki'; }
 *     public function mentionTitle(): string   { return $this->title; }
 *     public function mentionUrl(): string     { return "/wiki/{$this->slug}"; }
 *
 * Optionally `mentionableBy(User $user): bool` keeps the notification from
 * members who could not open the content anyway (drafts, pending approval).
 */
trait NotifiesMentions
{
    /**
     * Text as it was before this save, per field.
     *
     * @var array<string,string|null>
     */
    protected array $mentionOriginals = [];

    protected static function bootNotifiesMentions(): void
    {
        // Translated text is written during the save, so the previous
        // version has to be taken before the model is written.
        static::saving(function (Model $model): void {
            $model->rememberMentionOriginals();
        });

        static::saved(function (Model $model): void {
            if ($model->shouldNotifyMentions()) {
                $model->notifyMentionedMembers();
            }
        });
    }

    public function rememberMentionOriginals(): void
    {
        $this->mentionOriginals = [];

        if ( ! $this->exists) {
            return;
        }

        foreach ($this->mentionFieldNames() as $field) {
            $this->mentionOriginals[$field] = $this->mentionOriginalValue($field);
        }
    }

    /**
     * Notify whoever was newly mentioned, in any of the fields. Called after
     * every save, and directly by content that had to wait (a wiki page).
     */
    public function notifyMentionedMembers(?User $author = null): void
    {
        $author ??= $this->mentionAuthor();

        if ( ! $author) {
            return;
        }

        $service    = app(MentionService::class);
        $recipients = collect();

        foreach ($this->mentionFieldNames() as $field) {
            $recipients = $recipients->merge(
                $service->newMentions($this->getAttribute($field), $this->mentionOriginals[$field] ?? null, $author)
            );
        }

        $recipients = $recipients->unique('id')->filter(fn (User $user) => $this->mentionableBy($user));

        // Nothing to link to means nothing worth notifying about
        if ($recipients->isEmpty() || blank($this->mentionUrl())) {
            return;
        }

        Notification::send($recipients, new MentionNotification(
            $this->mentionContext(),
            $this->mentionTitle(),
            $this->mentionUrl(),
            $author,
            (string) $this->getAttribute($this->mentionFieldNames()[0])
        ));

        $this->mentionOriginals = [];
    }

    /**
     * Who wrote the mention: whoever is signed in.
     */
    public function mentionAuthor(): ?User
    {
        return Auth::user();
    }

    /**
     * Whether the mentioned member can open the content at all.
     */
    public function mentionableBy(User $user): bool
    {
        return true;
    }

    /**
     * Whether this save is the moment to notify. Content that only gets its
     * address afterwards (a wiki page, which attaches its wiki row after the
     * text is written) says no here and notifies once it is complete.
     */
    public function shouldNotifyMentions(): bool
    {
        return true;
    }

    /**
     * @return string[]
     */
    protected function mentionFieldNames(): array
    {
        return property_exists($this, 'mentionFields') ? (array) $this->mentionFields : ['content'];
    }

    /**
     * The stored value before this save. Translated text does not live on the
     * model itself, so it is read through the accessor of the stored row.
     */
    protected function mentionOriginalValue(string $field): ?string
    {
        if (in_array($field, (array) ($this->translatedAttributes ?? []), true)) {
            return $this->getOriginalTranslation($field);
        }

        return $this->getOriginal($field);
    }

    /**
     * The translated value as it is stored right now, read fresh so an
     * unsaved change in memory does not hide it.
     */
    protected function getOriginalTranslation(string $field): ?string
    {
        $translation = $this->translations()
            ->where($this->getLocaleKey(), app()->getLocale())
            ->first();

        return $translation?->getAttribute($field);
    }
}
