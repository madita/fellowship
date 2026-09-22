<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Achievements.
 *
 * An achievement is a name, a look, and a rule: which tracked action earns
 * it, how many times, and optionally narrowed to certain kinds of that
 * action — organising three events, but only of the types that take real
 * effort. Anything the site cannot count for itself is handed out by hand,
 * which is how the real-world ones work.
 *
 * The wording lives in achievement_translations (created with the other
 * translation tables) and the kind is a taxonomy, so both read in the
 * member's own language.
 */
return new class extends Migration
{
    /**
     * The kinds an achievement can be, and what to call them. A taxonomy
     * rather than a column, so an admin can add one without a deploy.
     */
    private const TYPES = [
        'community' => ['en' => 'Community', 'de' => 'Community'],
        'content'   => ['en' => 'Content', 'de' => 'Inhalte'],
        'events'    => ['en' => 'Events', 'de' => 'Events'],
        'support'   => ['en' => 'Support', 'de' => 'Support'],
        'special'   => ['en' => 'Special', 'de' => 'Besonderes'],
    ];

    /**
     * A few to start with, so the feature arrives with something in it and
     * the shape of a rule is visible. Admins edit or delete them freely.
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
            'en' => ['Marked Helpful', 'One of your replies was marked as the solution.'],
            'de' => ['Als hilfreich markiert', 'Eine deiner Antworten wurde als Lösung markiert.'],
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

    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            // What kind of achievement it is — a taxonomy, so its name is
            // translated and admins can add a kind without a deploy.
            // taxonomies.id is an unsigned integer, not a bigint.
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

        // The wording table is created with the other translation tables,
        // before this one exists, so its constraint belongs here
        Schema::table('achievement_translations', function (Blueprint $table) {
            $table->foreign('achievement_id')->references('id')->on('achievements')->cascadeOnDelete();
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
         * `scope` holds the narrowing value — an event type id, say — with
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

        $this->seedPermission();
        $this->seedStarterAchievements($this->seedTypes());
    }

    /**
     * Handing out an achievement for something that happened away from the
     * site can be delegated, so it gets a permission of its own rather than
     * belonging to admins alone.
     */
    private function seedPermission(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

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
     * it a kind of achievement — the shape the forum uses for categories.
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

    public function down(): void
    {
        Schema::dropIfExists('achievement_progress');
        Schema::dropIfExists('achievement_user');

        // The wording table outlives this migration, so only its link goes
        if (Schema::hasTable('achievement_translations')) {
            Schema::table('achievement_translations', function (Blueprint $table) {
                $table->dropForeign(['achievement_id']);
            });
        }

        Schema::dropIfExists('achievements');
    }
};
