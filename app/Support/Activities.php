<?php

namespace App\Support;

use App\Models\Collection as Album;
use App\Models\Event\Event;
use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumThread;
use App\Models\Poll\Poll;
use App\Models\Sandbox\Sandbox;
use App\Models\Status\Status;
use App\Models\Ticket\Ticket;
use App\Models\User;
use App\Models\Wiki;
use Illuminate\Database\Eloquent\Model;

/**
 * Where the site records what happened.
 *
 * Every entry is written through record() so two things are always true:
 * it belongs to one of the log names below, and its properties carry a
 * `title` and a `url`. That second part is what lets one feed component
 * render every feature — before this, the forum wrote `thread_title` and
 * no url, so the frontend had a per-event switch just to build a link.
 *
 * Recording never throws. An audit trail that can break the thing it is
 * recording is worse than no audit trail, which is the same bargain
 * AchievementService makes.
 */
class Activities
{
    public const FORUM = 'forum';

    public const WIKI = 'wiki';

    public const TICKETS = 'tickets';

    public const SANDBOX = 'sandbox';

    public const EVENTS = 'events';

    public const TIMELINE = 'timeline';

    public const GALLERY = 'gallery';

    public const POLLS = 'polls';

    public const MEMBERS = 'members';

    /**
     * The log names a caller may ask for. Anything else is refused rather
     * than quietly returning an empty feed.
     */
    public static function logs(): array
    {
        return [
            self::FORUM,
            self::WIKI,
            self::TICKETS,
            self::SANDBOX,
            self::EVENTS,
            self::TIMELINE,
            self::GALLERY,
            self::POLLS,
            self::MEMBERS,
        ];
    }

    /**
     * The subjects a caller may narrow the feed to, by short name.
     *
     * A class name off the request is never trusted anywhere in this
     * codebase — the relateable, watchable and commentable registries all
     * work the same way — and keeping the mapping here also means no Vue
     * template has to spell out a PHP namespace to ask for one page's
     * history.
     *
     * @return array<string, class-string>
     */
    public static function subjects(): array
    {
        return [
            'thread'  => ForumThread::class,
            'post'    => ForumPost::class,
            'wiki'    => Wiki::class,
            'ticket'  => Ticket::class,
            'sandbox' => Sandbox::class,
            'event'   => Event::class,
            'status'  => Status::class,
            'poll'    => Poll::class,
            'album'   => Album::class,
        ];
    }

    /**
     * The model behind one of those short names, or null if unknown.
     */
    public static function subjectClass(?string $subject): ?string
    {
        return self::subjects()[$subject] ?? null;
    }

    /**
     * Record something that happened.
     *
     * @param  string  $log  one of the constants above
     * @param  string  $event  what happened, e.g. 'thread_created' — the
     *                         string the frontend turns into a sentence
     * @param  array  $properties  anything extra worth keeping; title and
     *                             url are filled in from the subject
     */
    public static function record(
        string $log,
        string $event,
        ?Model $subject,
        ?User $causer = null,
        array $properties = [],
        ?string $description = null
    ): void {
        try {
            if ( ! $subject) {
                return;
            }

            activity($log)
                ->performedOn($subject)
                ->causedBy($causer ?? auth()->user())
                ->withProperties(array_merge(self::describe($subject), $properties))
                ->event($event)
                ->log($description ?? str_replace('_', ' ', $event));
        } catch (\Throwable $e) {
            // Nothing the member did should fail because the audit trail
            // could not be written
            report($e);
        }
    }

    /**
     * What a reader needs to recognise the subject and open it.
     *
     * Models describe themselves in several ways across this codebase —
     * for @mentions, for the watching list — so the ones already there are
     * used before falling back to plain attributes.
     */
    public static function describe(Model $subject): array
    {
        return array_filter([
            'title' => self::titleOf($subject),
            'url'   => self::urlOf($subject),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private static function titleOf(Model $subject): ?string
    {
        foreach (['mentionTitle', 'watchTitle'] as $method) {
            if (method_exists($subject, $method)) {
                return (string) $subject->{$method}();
            }
        }

        return $subject->title ?? $subject->name ?? null;
    }

    private static function urlOf(Model $subject): ?string
    {
        foreach (['mentionUrl', 'watchUrl', 'commentUrl'] as $method) {
            if (method_exists($subject, $method)) {
                return (string) $subject->{$method}();
            }
        }

        return $subject->url ?? null;
    }
}
