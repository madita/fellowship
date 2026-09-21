<?php

namespace App\Support;

use App\Models\Event\EventType;

/**
 * The actions the site knows how to count.
 *
 * An achievement points at one of these, so an admin picks from a list of
 * things that genuinely happen rather than composing a rule that silently
 * never fires. A metric can offer a *scope* — a way of narrowing it, like
 * the type of an event — and the admin picks which of those count.
 *
 * Adding a metric here is half the work; the other half is recording it,
 * which the model or controller does through the Achievements service.
 */
class AchievementMetrics
{
    /**
     * Every metric, grouped for the picker. `scope` names the narrowing a
     * metric supports, if any; `feature` ties it to the feature toggles so
     * a metric of a switched-off feature is not offered.
     */
    public static function all(): array
    {
        return [
            // Forum
            'forum.thread.created' => ['group' => 'forum', 'feature' => 'forum'],
            'forum.post.created'   => ['group' => 'forum', 'feature' => 'forum'],
            'forum.post.solution'  => ['group' => 'forum', 'feature' => 'forum'],
            'forum.post.liked'     => ['group' => 'forum', 'feature' => 'forum'],

            // Wiki
            'wiki.page.created'  => ['group' => 'wiki', 'feature' => 'wiki'],
            'wiki.page.approved' => ['group' => 'wiki', 'feature' => 'wiki'],
            'wiki.page.edited'   => ['group' => 'wiki', 'feature' => 'wiki'],

            // Tickets and feedback
            'ticket.created'   => ['group' => 'tickets', 'feature' => 'tickets'],
            'feedback.bug'     => ['group' => 'tickets', 'feature' => 'feedback'],
            'feedback.feature' => ['group' => 'tickets', 'feature' => 'feedback'],
            'ticket.comment'   => ['group' => 'tickets', 'feature' => 'tickets'],
            'ticket.resolved'  => ['group' => 'tickets', 'feature' => 'tickets'],

            // Events — the ones that carry a type, so an achievement can ask
            // for the kinds of event that actually take effort
            'event.organised' => ['group' => 'events', 'feature' => 'events', 'scope' => 'event_type'],
            'event.joined'    => ['group' => 'events', 'feature' => 'events', 'scope' => 'event_type'],

            // Everything else
            'timeline.post.created' => ['group' => 'content', 'feature' => 'timeline'],
            'timeline.comment'      => ['group' => 'content', 'feature' => 'timeline'],
            'poll.voted'            => ['group' => 'community', 'feature' => null],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $metric): bool
    {
        return array_key_exists($metric, self::all());
    }

    /**
     * The narrowing a metric supports, or null when it counts plainly.
     */
    public static function scopeOf(string $metric): ?string
    {
        return self::all()[$metric]['scope'] ?? null;
    }

    /**
     * The metrics, with everything the admin picker needs: which group they
     * belong to, and — where a metric can be narrowed — the choices to
     * narrow it by.
     */
    public static function forPicker(): array
    {
        $scopes = self::scopeChoices();

        return collect(self::all())
            ->map(fn (array $metric, string $key) => [
                'key'     => $key,
                'group'   => $metric['group'],
                'feature' => $metric['feature'] ?? null,
                'scope'   => $metric['scope'] ?? null,
                'choices' => isset($metric['scope']) ? ($scopes[$metric['scope']] ?? []) : [],
            ])
            ->values()
            ->all();
    }

    /**
     * What each kind of narrowing can be set to. Read fresh, so a newly
     * added event type can be used straight away.
     */
    public static function scopeChoices(): array
    {
        return [
            'event_type' => EventType::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (EventType $type) => ['value' => (string) $type->id, 'label' => $type->name])
                ->all(),
        ];
    }
}
