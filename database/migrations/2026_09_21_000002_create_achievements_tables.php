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
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->default('mdi-trophy-outline');
            $table->string('image_path')->nullable();
            $table->string('color')->default('amber');
            // Grouping for the browsable list: community, content, events …
            $table->string('category')->default('community');
            $table->unsignedInteger('points')->default(10);

            // How it is earned. 'manual' has no metric: an admin awards it.
            $table->string('trigger')->default('manual');
            $table->string('metric')->nullable();
            $table->unsignedInteger('threshold')->default(1);
            // Narrows the metric, e.g. {"event_type_id": [2, 5]}
            $table->json('filters')->nullable();

            $table->boolean('is_enabled')->default(true);
            // Hidden from the list until earned
            $table->boolean('is_secret')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_enabled', 'category']);
            $table->index(['trigger', 'metric']);
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
        $this->seedStarterAchievements();
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

        $exists = DB::table('permissions')->where('name', 'award-achievements')->exists();

        if ($exists) {
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
     * A few to start with, so the feature arrives with something in it and
     * the shape of a rule is visible. Admins edit or delete them freely.
     */
    private function seedStarterAchievements(): void
    {
        $now = now();

        $starters = [
            ['first-feature-request', 'First Feature Request', 'You asked for something to be built.', 'mdi-lightbulb-on-outline', 'amber', 'support', 10, 'metric', 'feedback.feature', 1],
            ['first-bug-report', 'First Bug Report', 'You told us something was broken.', 'mdi-bug-outline', 'red', 'support', 10, 'metric', 'feedback.bug', 1],
            ['ten-replies', 'Ten Replies', 'You replied in the forum ten times.', 'mdi-reply-all-outline', 'blue', 'community', 20, 'metric', 'forum.post.created', 10],
            ['marked-helpful', 'Marked Helpful', 'One of your replies was marked as the solution.', 'mdi-check-decagram-outline', 'green', 'community', 30, 'metric', 'forum.post.solution', 1],
            ['wiki-writer', 'Wiki Writer', 'Five of your wiki pages were approved.', 'mdi-book-edit-outline', 'deep-purple', 'content', 40, 'metric', 'wiki.page.approved', 5],
            ['host', 'Host', 'You organised three events.', 'mdi-party-popper', 'pink', 'events', 30, 'metric', 'event.organised', 3],
            ['joined-in', 'Joined In', 'You came to five events.', 'mdi-account-group-outline', 'teal', 'events', 20, 'metric', 'event.joined', 5],
            // The shape of a real-world one: nothing on the site can see it,
            // so somebody has to say it happened
            ['cook', 'Cook', 'You cooked for the others. Awarded by hand.', 'mdi-chef-hat', 'orange', 'special', 50, 'manual', null, 1],
        ];

        DB::table('achievements')->insert(array_map(
            fn (array $row, int $index) => [
                'key'         => $row[0],
                'name'        => $row[1],
                'description' => $row[2],
                'icon'        => $row[3],
                'color'       => $row[4],
                'category'    => $row[5],
                'points'      => $row[6],
                'trigger'     => $row[7],
                'metric'      => $row[8],
                'threshold'   => $row[9],
                'filters'     => null,
                'is_enabled'  => true,
                'is_secret'   => false,
                'sort_order'  => $index,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            $starters,
            array_keys($starters)
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('achievement_progress');
        Schema::dropIfExists('achievement_user');
        Schema::dropIfExists('achievements');
    }
};
