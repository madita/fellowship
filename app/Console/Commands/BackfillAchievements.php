<?php

namespace App\Console\Commands;

use App\Models\Achievement;
use App\Models\AchievementProgress;
use App\Models\Page;
use App\Models\User;
use App\Services\AchievementService;
use App\Support\AchievementMetrics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Count what members have already done.
 *
 * Achievements only see what happens after they are switched on, which
 * leaves everyone's history uncounted — the forum posts, the tickets and
 * the events from before. This walks the existing records, rebuilds the
 * tallies and hands out whatever that turns out to have earned.
 *
 * Safe to run more than once: each tally is rebuilt from the records rather
 * than added to, and an achievement already held is left alone.
 */
class BackfillAchievements extends Command
{
    protected $signature = 'achievements:backfill
                            {--metric=* : Only these actions, e.g. --metric=ticket.created}
                            {--dry-run : Count and report, award nothing}';

    protected $description = 'Count what members did before achievements existed, and award what it earned';

    public function handle(AchievementService $achievements): int
    {
        $only = (array) $this->option('metric');
        $dry  = (bool) $this->option('dry-run');

        foreach ($only as $metric) {
            if ( ! AchievementMetrics::exists($metric)) {
                $this->error("No such action: {$metric}");

                return self::FAILURE;
            }
        }

        $counted = 0;

        foreach ($this->tallies() as $metric => $rows) {
            if ($only && ! in_array($metric, $only, true)) {
                continue;
            }

            $this->line("  {$metric}: " . count($rows));
            $counted += count($rows);

            if ($dry) {
                continue;
            }

            foreach ($rows as $row) {
                $this->store((int) $row->user_id, $metric, (string) ($row->scope ?? ''), (int) $row->total);
            }
        }

        $this->info($dry ? "Would record {$counted} tallies." : "Recorded {$counted} tallies.");

        if ($dry) {
            return self::SUCCESS;
        }

        return $this->awardEverythingEarned($achievements);
    }

    /**
     * Rebuild one tally from the records rather than adding to it, so a
     * second run does not double anybody's count.
     */
    private function store(int $userId, string $metric, string $scope, int $total): void
    {
        AchievementProgress::updateOrCreate(
            ['user_id' => $userId, 'metric' => $metric, 'scope' => $scope],
            ['count' => $total, 'last_at' => now()]
        );

        // A metric that can be narrowed also keeps a plain total; the
        // per-scope rows are stored separately below.
        if ($scope !== '') {
            return;
        }
    }

    /**
     * Give everyone whatever their rebuilt tallies have earned.
     */
    private function awardEverythingEarned(AchievementService $achievements): int
    {
        $achievable = Achievement::enabled()->where('trigger', 'metric')->get();
        $awarded    = 0;

        if ($achievable->isEmpty()) {
            $this->info('No achievements count an action, so nothing to award.');

            return self::SUCCESS;
        }

        $userIds = AchievementProgress::distinct('user_id')->pluck('user_id');

        $this->withProgressBar($userIds, function ($userId) use ($achievable, $achievements, &$awarded) {
            $user = User::find($userId);

            if ( ! $user) {
                return;
            }

            $held = $user->achievements()->pluck('achievements.id')->all();

            foreach ($achievable as $achievement) {
                if (in_array($achievement->id, $held, true)) {
                    continue;
                }

                $progress = $achievement->progressFor($user);

                if ($progress >= $achievement->threshold && $achievements->award($user, $achievement, null, null, $progress)) {
                    $awarded++;
                }
            }
        });

        $this->newLine(2);
        $this->info("Awarded {$awarded} achievement(s).");

        return self::SUCCESS;
    }

    /**
     * What each member has already done, per action.
     *
     * Every row is [user_id, scope, total]; `scope` is blank for the plain
     * total and carries the narrowing value for the rest.
     */
    private function tallies(): array
    {
        return array_filter([
            'forum.thread.created' => $this->count('forum_threads', 'user_id'),
            'forum.post.created'   => $this->count('forum_posts', 'user_id'),
            'forum.post.solution'  => $this->count('forum_posts', 'user_id', fn ($q) => $q->where('is_solution', true)),
            'forum.post.liked'     => $this->joinedCount(
                'forum_post_likes',
                'forum_posts',
                'forum_post_likes.post_id',
                'forum_posts.user_id'
            ),

            'wiki.page.created'  => $this->count('pages', 'user_id', fn ($q) => $q->whereIn(
                'id',
                DB::table('wikiables')->where('wikiable_type', Page::class)->select('wikiable_id')
            )),
            'wiki.page.edited' => $this->count('revisions', 'user_id', fn ($q) => $q
                ->where('revisionable_type', Page::class)
                ->where('action', '!=', 'created')),

            'ticket.created'    => $this->count('tickets', 'created_by_user_id'),
            'ticket.resolved'   => $this->count('tickets', 'created_by_user_id', fn ($q) => $q->where('status', 'resolved')),
            'ticket.comment'    => $this->count('ticket_comments', 'user_id'),
            'feedback.bug'      => $this->ticketsOfType('bug'),
            'feedback.feature'  => $this->ticketsOfType('feature'),

            'event.organised' => $this->eventCounts(),
            'event.joined'    => $this->guestCounts(),

            'timeline.post.created' => $this->count('statuses', 'user_id'),
            'timeline.comment'      => $this->count('status_comments', 'user_id'),
            'poll.voted'            => $this->count('poll_votes', 'user_id'),
        ], fn ($rows) => $rows !== null);
    }

    /**
     * How many rows each member owns in one table.
     */
    private function count(string $table, string $userColumn, ?callable $filter = null): ?array
    {
        if ( ! $this->tableExists($table)) {
            return null;
        }

        $query = DB::table($table)
            ->whereNotNull($userColumn)
            ->groupBy($userColumn)
            ->select([$userColumn . ' as user_id', DB::raw("'' as scope"), DB::raw('COUNT(*) as total')]);

        if ($filter) {
            $filter($query);
        }

        if ($this->hasSoftDeletes($table)) {
            $query->whereNull('deleted_at');
        }

        return $query->get()->all();
    }

    /**
     * Rows credited to the owner of something else — a like counts for
     * whoever wrote the post.
     */
    private function joinedCount(string $table, string $ownerTable, string $foreignKey, string $ownerColumn): ?array
    {
        if ( ! $this->tableExists($table) || ! $this->tableExists($ownerTable)) {
            return null;
        }

        return DB::table($table)
            ->join($ownerTable, $ownerTable . '.id', '=', $foreignKey)
            ->whereNotNull($ownerColumn)
            ->groupBy($ownerColumn)
            ->select([$ownerColumn . ' as user_id', DB::raw("'' as scope"), DB::raw('COUNT(*) as total')])
            ->get()
            ->all();
    }

    private function ticketsOfType(string $slug): ?array
    {
        if ( ! $this->tableExists('tickets') || ! $this->tableExists('ticket_types')) {
            return null;
        }

        return DB::table('tickets')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->where('ticket_types.slug', $slug)
            ->whereNull('tickets.deleted_at')
            ->whereNotNull('tickets.created_by_user_id')
            ->groupBy('tickets.created_by_user_id')
            ->select([
                'tickets.created_by_user_id as user_id',
                DB::raw("'' as scope"),
                DB::raw('COUNT(*) as total'),
            ])
            ->get()
            ->all();
    }

    /**
     * Events carry their type, so both the plain total and a tally per type
     * are rebuilt — an achievement may ask for either.
     */
    private function eventCounts(): ?array
    {
        if ( ! $this->tableExists('events')) {
            return null;
        }

        $plain = DB::table('events')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->select(['user_id', DB::raw("'' as scope"), DB::raw('COUNT(*) as total')])
            ->get()
            ->all();

        $perType = DB::table('events')
            ->whereNotNull('user_id')
            ->whereNotNull('event_type_id')
            ->groupBy('user_id', 'event_type_id')
            ->select(['user_id', 'event_type_id as scope', DB::raw('COUNT(*) as total')])
            ->get()
            ->all();

        return array_merge($plain, $perType);
    }

    private function guestCounts(): ?array
    {
        if ( ! $this->tableExists('event_guests') || ! $this->tableExists('events')) {
            return null;
        }

        $base = fn () => DB::table('event_guests')
            ->join('events', 'events.id', '=', 'event_guests.event_id')
            ->whereNull('event_guests.deleted_at')
            ->whereNotNull('event_guests.user_id');

        $plain = $base()
            ->groupBy('event_guests.user_id')
            ->select(['event_guests.user_id as user_id', DB::raw("'' as scope"), DB::raw('COUNT(*) as total')])
            ->get()
            ->all();

        $perType = $base()
            ->whereNotNull('events.event_type_id')
            ->groupBy('event_guests.user_id', 'events.event_type_id')
            ->select([
                'event_guests.user_id as user_id',
                'events.event_type_id as scope',
                DB::raw('COUNT(*) as total'),
            ])
            ->get()
            ->all();

        return array_merge($plain, $perType);
    }

    private function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }

    private function hasSoftDeletes(string $table): bool
    {
        return DB::getSchemaBuilder()->hasColumn($table, 'deleted_at');
    }
}
