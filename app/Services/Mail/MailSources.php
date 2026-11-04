<?php

namespace App\Services\Mail;

use App\Models\Achievement;
use App\Models\Event\Event;
use App\Models\Mail\MailTemplate;
use App\Models\Post;
use App\Models\Rank;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * The things already on the site that a mailing can be filled in from.
 *
 * An event template asks for a title, a date and an address. Typing
 * all three in by hand is tedious, and the easiest way there is to
 * send four hundred people the wrong date. So the event itself is
 * picked instead, and the values come from the record.
 *
 * Which source a kind of mail offers is listed per category below, and a
 * template that names a source's placeholders overrides that: whoever
 * writes a template under their own category still gets the picker, as
 * long as the placeholders say what the mail is about.
 *
 * The values are put into the mailing's own wording when it is written,
 * not looked up again when it is sent. A mailing is the record of what
 * went out, and an event moved a week later must not silently rewrite
 * the mail that announced it.
 */
class MailSources
{
    public const EVENT = 'event';

    public const POST = 'post';

    public const ACHIEVEMENT = 'achievement';

    public const RANK = 'rank';

    /**
     * What each source can fill in.
     *
     * These are the names a template writes as placeholders. Everything
     * here is plain text by the time it is substituted — a description
     * arrives stripped of its markup, because it is going into somebody
     * else's wording.
     */
    private const VARIABLES = [
        self::EVENT => [
            'event_title',
            'event_date',
            'event_time',
            'event_location',
            'event_url',
            'event_description',
        ],
        self::POST => [
            'post_title',
            'post_excerpt',
            'post_url',
            'post_author',
            'post_date',
        ],
        self::ACHIEVEMENT => [
            'achievement',
            'achievement_description',
            'points',
            'achievement_url',
        ],
        self::RANK => [
            'rank',
            'rank_description',
            'points',
            'rank_url',
        ],
    ];

    /**
     * What each kind of mail is usually written about.
     *
     * A category invented by an admin has nothing here and simply gets
     * no picker, which is the honest answer - until its template names a
     * source's placeholders, and then it gets one.
     *
     * `account` is left out on purpose: a password reset is about the
     * member, not about anything on the site. Ranks are reached rather
     * than announced in bulk, so `rank` is reachable through a template
     * that asks for it and is no category's default.
     */
    private const BY_CATEGORY = [
        'events'        => self::EVENT,
        'announcements' => self::POST,
        'newsletter'    => self::POST,
        'achievements'  => self::ACHIEVEMENT,
    ];

    /**
     * Every source and the placeholders it fills, for the composer.
     *
     * @return array<string, array{variables: array<int, string>}>
     */
    public function types(): array
    {
        $types = [];

        foreach (self::VARIABLES as $type => $variables) {
            $types[$type] = ['variables' => $variables];
        }

        return $types;
    }

    /**
     * Which kind of mail offers which source, by category key.
     *
     * @return array<string, string>
     */
    public function categories(): array
    {
        return self::BY_CATEGORY;
    }

    public function has(string $type): bool
    {
        return array_key_exists($type, self::VARIABLES);
    }

    /**
     * @return array<int, string>
     */
    public function variableNames(string $type): array
    {
        return self::VARIABLES[$type] ?? [];
    }

    public function forCategory(?string $categoryKey): ?string
    {
        return $categoryKey === null ? null : (self::BY_CATEGORY[$categoryKey] ?? null);
    }

    /**
     * The source a template is written for.
     *
     * The placeholders it declares win over the category it sits in: a
     * template asking for `{{ event_title }}` is about an event wherever
     * somebody filed it.
     */
    public function forTemplate(?MailTemplate $template, ?string $categoryKey = null): ?string
    {
        $declared = $template?->variables ?: [];
        $best     = null;
        $score    = 0;

        foreach (self::VARIABLES as $type => $variables) {
            $overlap = count(array_intersect($declared, $variables));

            // `points` belongs to an achievement and to a rank alike, so
            // the closest match wins rather than the first one tried
            if ($overlap > $score) {
                $best  = $type;
                $score = $overlap;
            }
        }

        return $best ?? $this->forCategory($categoryKey ?? $template?->category?->key);
    }

    /**
     * The records of one kind, as a picker wants them: enough on each
     * line to tell two events with the same name apart.
     *
     * @return array<int, array{id: int, label: string, hint: string, icon: ?string}>
     */
    public function options(string $type, ?string $search = null, int $limit = 25, ?string $locale = null): array
    {
        $locale = $locale ?: app()->getLocale();
        $search = filled($search) ? trim((string) $search) : null;

        return match ($type) {
            self::EVENT       => $this->eventOptions($search, $limit, $locale),
            self::POST        => $this->postOptions($search, $limit, $locale),
            self::ACHIEVEMENT => $this->achievementOptions($search, $limit, $locale),
            self::RANK        => $this->rankOptions($search, $limit, $locale),
            default           => [],
        };
    }

    /**
     * What one record fills in.
     *
     * Throws if it is gone, which is the right answer: filling a mailing
     * with blanks because somebody deleted the event is worse than
     * saying so.
     *
     * @return array<string, string>
     */
    public function variables(string $type, int $id, ?string $locale = null): array
    {
        $locale = $locale ?: app()->getLocale();

        return match ($type) {
            self::EVENT       => $this->eventVariables($id, $locale),
            self::POST        => $this->postVariables($id, $locale),
            self::ACHIEVEMENT => $this->achievementVariables($id, $locale),
            self::RANK        => $this->rankVariables($id, $locale),
            default           => [],
        };
    }

    /**
     * The one line that names what was picked, so the composer can say
     * what it just filled in from.
     *
     * Takes the values rather than the record, because whoever is asking
     * has just looked them up.
     *
     * @param  array<string, string>  $variables
     */
    public function describe(array $variables): string
    {
        return (string) ($variables['event_title']
            ?? $variables['post_title']
            ?? $variables['achievement']
            ?? $variables['rank']
            ?? '');
    }

    /**
     * @return array<int, array{id: int, label: string, hint: string, icon: ?string}>
     */
    private function eventOptions(?string $search, int $limit, string $locale): array
    {
        $query = Event::with(['translations', 'details'])
            // Descending puts what is still to come above what has been,
            // which is what a mailing is nearly always about
            ->orderByRaw('startDate IS NULL, startDate DESC')
            ->orderBy('id', 'desc');

        if ($search !== null) {
            $query->whereTranslationLike('title', '%' . $search . '%');
        }

        return $query->limit($limit)->get()->map(function (Event $event) use ($locale) {
            $date  = $this->date($event->startDate, $locale);
            $place = $this->place($event);

            return [
                'id'    => $event->id,
                'label' => (string) ($event->translateOrDefault($locale)?->title ?: $event->slug),
                'hint'  => trim(implode(' · ', array_filter([$date, $place]))),
                'icon'  => 'mdi-calendar',
            ];
        })->all();
    }

    /**
     * @return array<string, string>
     */
    private function eventVariables(int $id, string $locale): array
    {
        $event       = Event::with(['translations', 'details'])->findOrFail($id);
        $translation = $event->translateOrDefault($locale);

        // An all-day event has no time worth printing, and a start time
        // of midnight on one is the database's answer, not the truth
        $time = $event->allDay || blank($event->startTime)
            ? ''
            : Carbon::parse($event->startTime)->format('H:i');

        return [
            'event_title'       => (string) ($translation?->title ?: $event->slug),
            'event_date'        => $this->date($event->startDate, $locale),
            'event_time'        => $time,
            'event_location'    => $this->place($event),
            'event_url'         => $this->siteUrl('/events/' . $event->id),
            'event_description' => $this->plain($translation?->description),
        ];
    }

    /**
     * @return array<int, array{id: int, label: string, hint: string, icon: ?string}>
     */
    private function postOptions(?string $search, int $limit, string $locale): array
    {
        $query = Post::with(['translations', 'user:id,username,name'])
            ->orderByRaw('COALESCE(published_at, created_at) DESC');

        if ($search !== null) {
            $query->whereTranslationLike('title', '%' . $search . '%');
        }

        return $query->limit($limit)->get()->map(function (Post $post) use ($locale) {
            $date = $this->date($post->published_at ?: $post->created_at, $locale);

            return [
                'id'    => $post->id,
                'label' => (string) ($post->translateOrDefault($locale)?->title ?: $post->slug),
                // A draft can be written about in advance, but whoever
                // picks one should know the link will not work yet
                'hint' => $post->published_at
                    ? $date
                    : trim($date . ' · ' . __('messages.mailings.source_draft')),
                'icon' => 'mdi-newspaper-variant-outline',
            ];
        })->all();
    }

    /**
     * @return array<string, string>
     */
    private function postVariables(int $id, string $locale): array
    {
        $post        = Post::with(['translations', 'user:id,username,name'])->findOrFail($id);
        $translation = $post->translateOrDefault($locale);

        return [
            'post_title'   => (string) ($translation?->title ?: $post->slug),
            'post_excerpt' => Str::limit($this->plain($translation?->body), 300),
            'post_url'     => $this->siteUrl('/blog/' . $post->slug),
            'post_author'  => (string) ($post->user?->name ?: $post->user?->username),
            'post_date'    => $this->date($post->published_at ?: $post->created_at, $locale),
        ];
    }

    /**
     * @return array<int, array{id: int, label: string, hint: string, icon: ?string}>
     */
    private function achievementOptions(?string $search, int $limit, string $locale): array
    {
        $query = Achievement::with('translations')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($search !== null) {
            $query->whereTranslationLike('name', '%' . $search . '%');
        }

        return $query->limit($limit)->get()->map(fn (Achievement $achievement) => [
            'id'    => $achievement->id,
            'label' => (string) ($achievement->translateOrDefault($locale)?->name ?: $achievement->key),
            'hint'  => __('messages.mailings.source_points', ['points' => (int) $achievement->points]),
            'icon'  => $achievement->icon ?: 'mdi-trophy-outline',
        ])->all();
    }

    /**
     * @return array<string, string>
     */
    private function achievementVariables(int $id, string $locale): array
    {
        $achievement = Achievement::with('translations')->findOrFail($id);
        $translation = $achievement->translateOrDefault($locale);

        return [
            'achievement'             => (string) ($translation?->name ?: $achievement->key),
            'achievement_description' => $this->plain($translation?->description),
            'points'                  => (string) (int) $achievement->points,
            'achievement_url'         => $this->siteUrl('/achievements'),
        ];
    }

    /**
     * @return array<int, array{id: int, label: string, hint: string, icon: ?string}>
     */
    private function rankOptions(?string $search, int $limit, string $locale): array
    {
        $query = Rank::with('translations')->orderBy('points_required');

        if ($search !== null) {
            $query->whereTranslationLike('name', '%' . $search . '%');
        }

        return $query->limit($limit)->get()->map(fn (Rank $rank) => [
            'id'    => $rank->id,
            'label' => (string) ($rank->translateOrDefault($locale)?->name ?: $rank->key),
            'hint'  => __('messages.mailings.source_points', ['points' => (int) $rank->points_required]),
            'icon'  => $rank->icon ?: 'mdi-shield-star-outline',
        ])->all();
    }

    /**
     * @return array<string, string>
     */
    private function rankVariables(int $id, string $locale): array
    {
        $rank        = Rank::with('translations')->findOrFail($id);
        $translation = $rank->translateOrDefault($locale);

        return [
            'rank'             => (string) ($translation?->name ?: $rank->key),
            'rank_description' => $this->plain($translation?->description),
            'points'           => (string) $rank->points_required,
            'rank_url'         => $this->siteUrl('/achievements'),
        ];
    }

    /**
     * Where an event is, as much of it as has been filled in.
     */
    private function place(Event $event): string
    {
        return implode(', ', array_filter([
            trim((string) $event->details?->city),
            trim((string) $event->details?->country),
        ]));
    }

    /**
     * A date as the recipient's language writes it - "4. November 2026",
     * not "2026-11-04", because this goes into a sentence.
     */
    private function date(mixed $value, string $locale): string
    {
        if (blank($value)) {
            return '';
        }

        try {
            return Carbon::parse($value)->locale($locale)->isoFormat('LL');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Text out of a rich-text field, for dropping into somebody else's
     * wording. The markup is not wanted and the entities would show.
     */
    private function plain(?string $value): string
    {
        if (blank($value)) {
            return '';
        }

        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function siteUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/') . $path;
    }
}
