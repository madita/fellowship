<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Achievements and the ranks they add up to.
 *
 * An achievement is a name, a look, and a rule: which tracked action
 * earns it, how many times, and optionally narrowed to certain kinds of
 * that action - organising three events, but only of the types that take
 * real effort. Anything the site cannot count for itself is handed out by
 * hand, which is how the real-world ones work.
 *
 * A member's rank is read from the points their achievements add up to:
 * the highest rank they have reached the threshold for. Nothing is stored
 * on the member, so editing a rank's threshold re-ranks everyone at once
 * rather than leaving stale titles behind.
 *
 * The kind of an achievement is a taxonomy rather than a column, so an
 * admin can add one without a deploy and its name is translated like
 * everything else. The starters and the ranks are written here because
 * the feature is nothing without something in it, and because
 * AchievementServiceProvider looks its metrics up by key.
 */
return new class extends Migration
{
    /**
     * The kinds an achievement can be, and what to call them.
     */
    private const TYPES = [
        'community' => ['en' => 'Community', 'de' => 'Community'],
        'content'   => ['en' => 'Content', 'de' => 'Inhalte'],
        'events'    => ['en' => 'Events', 'de' => 'Events'],
        'support'   => ['en' => 'Support', 'de' => 'Support'],
        'special'   => ['en' => 'Special', 'de' => 'Besonderes'],
    ];

    /**
     * A few to start with, so the shape of a rule is visible. Admins edit
     * or delete them freely.
     */
    private const STARTERS = [
        [
            'key' => 'first-feature-request', 'type' => 'support', 'icon' => 'mdi-lightbulb-on-outline',
            'color' => 'amber', 'points' => 10, 'trigger' => 'metric', 'metric' => 'feedback.feature', 'threshold' => 1,
            'en' => ['First Feature Request', 'You asked for something to be built.'],
            'de' => ['Erster Feature-Vorschlag', 'Du hast dir etwas gewünscht.'],
        ],
        [
            'key' => 'first-bug-report', 'type' => 'support', 'icon' => 'mdi-bug-outline',
            'color' => 'red', 'points' => 10, 'trigger' => 'metric', 'metric' => 'feedback.bug', 'threshold' => 1,
            'en' => ['First Bug Report', 'You told us something was broken.'],
            'de' => ['Erster Fehlerbericht', 'Du hast uns gesagt, dass etwas kaputt ist.'],
        ],
        [
            'key' => 'ten-replies', 'type' => 'community', 'icon' => 'mdi-reply-all-outline',
            'color' => 'blue', 'points' => 20, 'trigger' => 'metric', 'metric' => 'forum.post.created', 'threshold' => 10,
            'en' => ['Ten Replies', 'You replied in the forum ten times.'],
            'de' => ['Zehn Antworten', 'Du hast zehnmal im Forum geantwortet.'],
        ],
        [
            'key' => 'marked-helpful', 'type' => 'community', 'icon' => 'mdi-check-decagram-outline',
            'color' => 'green', 'points' => 30, 'trigger' => 'metric', 'metric' => 'forum.post.solution', 'threshold' => 1,
            'en' => ['Marked Helpful', 'One of your replies was marked as the helpful one.'],
            'de' => ['Als hilfreich markiert', 'Eine deiner Antworten wurde als hilfreich markiert.'],
        ],
        [
            'key' => 'wiki-writer', 'type' => 'content', 'icon' => 'mdi-book-edit-outline',
            'color' => 'deep-purple', 'points' => 40, 'trigger' => 'metric', 'metric' => 'wiki.page.approved', 'threshold' => 5,
            'en' => ['Wiki Writer', 'Five of your wiki pages were approved.'],
            'de' => ['Wiki-Autor', 'Fünf deiner Wiki-Seiten wurden freigegeben.'],
        ],
        [
            'key' => 'host', 'type' => 'events', 'icon' => 'mdi-party-popper',
            'color' => 'pink', 'points' => 30, 'trigger' => 'metric', 'metric' => 'event.organised', 'threshold' => 3,
            'en' => ['Host', 'You organised three events.'],
            'de' => ['Gastgeber', 'Du hast drei Events organisiert.'],
        ],
        [
            'key' => 'joined-in', 'type' => 'events', 'icon' => 'mdi-account-group-outline',
            'color' => 'teal', 'points' => 20, 'trigger' => 'metric', 'metric' => 'event.joined', 'threshold' => 5,
            'en' => ['Joined In', 'You came to five events.'],
            'de' => ['Dabei gewesen', 'Du warst bei fünf Events.'],
        ],
        // The shape of a real-world one: nothing on the site can see it,
        // so somebody has to say it happened
        [
            'key' => 'cook', 'type' => 'special', 'icon' => 'mdi-chef-hat',
            'color' => 'orange', 'points' => 50, 'trigger' => 'manual', 'metric' => null, 'threshold' => 1,
            'en' => ['Cook', 'You cooked for the others. Awarded by hand.'],
            'de' => ['Koch', 'Du hast für die anderen gekocht. Wird von Hand vergeben.'],
        ],
    ];

    /**
     * Spread so the early ones come quickly and the last is a long haul.
     */
    private const RANKS = [
        ['newcomer', 0, 'mdi-sprout-outline', 'blue-grey', ['Newcomer', 'Just arrived.'], ['Neuling', 'Gerade angekommen.']],
        ['member', 50, 'mdi-account-outline', 'teal', ['Member', 'Part of the furniture.'], ['Mitglied', 'Gehört dazu.']],
        ['regular', 150, 'mdi-account-star-outline', 'blue', ['Regular', 'Around often, and it shows.'], ['Stammgast', 'Oft da — und man merkt es.']],
        ['veteran', 300, 'mdi-shield-star-outline', 'deep-purple', ['Veteran', 'Has been here a while, and done a lot.'], ['Veteran', 'Schon lange dabei und viel bewegt.']],
        ['elder', 600, 'mdi-crown-outline', 'amber', ['Elder', 'One of the people who built this place.'], ['Ältester', 'Einer von denen, die das hier aufgebaut haben.']],
        ['legend', 1000, 'mdi-trophy-variant-outline', 'orange', ['Legend', 'There is a story about you.'], ['Legende', 'Über dich gibt es Geschichten.']],
    ];

    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            // What kind it is. taxonomies.id is an unsigned integer, not a
            // bigint.
            $table->unsignedInteger('taxonomy_id')->nullable();
            $table->string('icon')->default('mdi-trophy-outline');
            // A badge can wear a picture instead of the icon
            $table->string('image_path')->nullable();
            $table->string('color')->default('amber');
            $table->unsignedInteger('points')->default(10);

            // How it is earned. 'manual' has no metric: an admin awards it.
            $table->string('trigger')->default('manual');
            $table->string('metric')->nullable();
            $table->unsignedInteger('threshold')->default(1);
            // Narrows the metric, e.g. {"values": ["2", "5"]}
            $table->json('filters')->nullable();

            $table->boolean('is_enabled')->default(true);
            // Hidden from the list until earned
            $table->boolean('is_secret')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_enabled', 'taxonomy_id']);
            $table->index(['trigger', 'metric']);

            $table->foreign('taxonomy_id')->references('id')->on('taxonomies')->nullOnDelete();
        });

        Schema::create('achievement_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['achievement_id', 'locale']);
        });

        Schema::create('achievement_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Null when the site awarded it by counting
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            // What the count stood at, so the award can be explained later
            $table->unsignedInteger('count_at_award')->default(0);
            $table->timestamp('awarded_at');
            $table->timestamps();

            $table->unique(['achievement_id', 'user_id']);
            $table->index(['user_id', 'awarded_at']);
        });

        /**
         * One row per member per tracked action, so progress toward an
         * achievement is a lookup rather than a scan over the whole site.
         * `scope` holds the narrowing value - an event type id, say - with
         * an empty string for the plain, unnarrowed count.
         */
        Schema::create('achievement_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('metric');
            $table->string('scope')->default('');
            $table->unsignedInteger('count')->default(0);
            $table->timestamp('last_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'metric', 'scope']);
            $table->index(['metric', 'scope']);
        });

        Schema::create('ranks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            // What the member's achievement points have to reach
            $table->unsignedInteger('points_required')->default(0);
            $table->string('icon')->default('mdi-shield-outline');
            // A rank can wear a picture instead of the icon, like a badge
            $table->string('image_path')->nullable();
            $table->string('color')->default('blue-grey');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['is_enabled', 'points_required']);
        });

        Schema::create('rank_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rank_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['rank_id', 'locale']);
        });

        $this->seedAwardPermission();
        $this->seedStarterAchievements($this->seedTypes());
        $this->seedRanks();
    }

    public function down(): void
    {
        Schema::dropIfExists('rank_translations');
        Schema::dropIfExists('ranks');
        Schema::dropIfExists('achievement_progress');
        Schema::dropIfExists('achievement_user');
        Schema::dropIfExists('achievement_translations');
        Schema::dropIfExists('achievements');
    }

    /**
     * Handing out an achievement for something that happened away from
     * the site can be delegated, so it gets a permission of its own
     * rather than belonging to admins alone. PermissionsTableSeeder lists
     * it too, for an install that is seeded rather than migrated.
     */
    private function seedAwardPermission(): void
    {
        if (DB::table('permissions')->where('name', 'award-achievements')->exists()) {
            return;
        }

        DB::table('permissions')->insert([
            'name'         => 'award-achievements',
            'guard_name'   => 'api',
            'display_name' => 'Award Achievements',
            'description'  => 'Hand achievements to members for things that happened away from the site',
            'category'     => 'achievement',
            'is_default'   => 0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    /**
     * The kinds, each a term for the name and the taxonomy row that makes
     * it a kind of achievement - the shape the forum uses for categories.
     *
     * @return array<string, int> kind => taxonomy id
     */
    private function seedTypes(): array
    {
        $now   = now();
        $types = [];

        foreach (self::TYPES as $slug => $titles) {
            $termId = DB::table('terms')->insertGetId([
                'slug'       => 'achievement-type-' . $slug,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($titles as $locale => $title) {
                DB::table('term_translations')->insert([
                    'term_id'    => $termId,
                    'locale'     => $locale,
                    'title'      => $title,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $types[$slug] = DB::table('taxonomies')->insertGetId([
                'term_id'    => $termId,
                'taxonomy'   => 'achievement_type',
                'sort'       => 0,
                'visible'    => 1,
                'searchable' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $types;
    }

    /**
     * @param  array<string, int>  $types
     */
    private function seedStarterAchievements(array $types): void
    {
        $now = now();

        foreach (self::STARTERS as $index => $starter) {
            $id = DB::table('achievements')->insertGetId([
                'key'         => $starter['key'],
                'taxonomy_id' => $types[$starter['type']] ?? null,
                'icon'        => $starter['icon'],
                'color'       => $starter['color'],
                'points'      => $starter['points'],
                'trigger'     => $starter['trigger'],
                'metric'      => $starter['metric'],
                'threshold'   => $starter['threshold'],
                'filters'     => null,
                'is_enabled'  => true,
                'is_secret'   => false,
                'sort_order'  => $index,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            foreach (['en', 'de'] as $locale) {
                DB::table('achievement_translations')->insert([
                    'achievement_id' => $id,
                    'locale'         => $locale,
                    'name'           => $starter[$locale][0],
                    'description'    => $starter[$locale][1],
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }
        }
    }

    private function seedRanks(): void
    {
        $now = now();

        foreach (self::RANKS as [$key, $points, $icon, $color, $en, $de]) {
            $id = DB::table('ranks')->insertGetId([
                'key'             => $key,
                'points_required' => $points,
                'icon'            => $icon,
                'color'           => $color,
                'is_enabled'      => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            foreach (['en' => $en, 'de' => $de] as $locale => $wording) {
                DB::table('rank_translations')->insert([
                    'rank_id'     => $id,
                    'locale'      => $locale,
                    'name'        => $wording[0],
                    'description' => $wording[1],
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
    }
};
