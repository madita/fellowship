<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Podcasts: a show, its episodes, and the people who make it.
 *
 * Everything a podcast directory asks for in a channel lives on the show
 * row, because the RSS feed is built straight off it.
 *
 * A show somebody makes is looked at before the world hears it, the same
 * way a wiki page is: creating one opens a ticket of its own type, and
 * the show stays out of the public listing and out of its feed until
 * somebody approves it - while its author can always see it, so there is
 * something to work on. Roles named in `auto_approve_roles_podcast` skip
 * the ticket; admins are in that list, because an admin making a show has
 * nobody to ask.
 *
 * Running a show is not an admin's job, so it has a permission of its
 * own: the people who record a show are rarely the people who administer
 * the site, and sending them through the admin panel to publish an
 * episode means handing them the admin panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('podcasts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('language', 10)->default('de');
            $table->boolean('explicit')->default(false);
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_published');
        });

        Schema::create('podcast_episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('podcast_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('summary')->nullable();
            // The show notes, rich text like a forum post
            $table->longText('show_notes')->nullable();
            $table->string('cover_image')->nullable();
            // Either a file on our own disk or an episode hosted
            // elsewhere - audio_path holds the first, audio_url the second
            $table->string('audio_path')->nullable();
            $table->string('audio_url')->nullable();
            $table->string('audio_mime')->nullable();
            $table->unsignedBigInteger('audio_size')->nullable();
            // Seconds
            $table->unsignedInteger('duration')->nullable();
            $table->unsignedInteger('episode_number')->nullable();
            $table->unsignedInteger('season_number')->nullable();
            $table->unsignedBigInteger('plays')->default(0);
            $table->boolean('explicit')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // A slug only has to be unique inside its own show
            $table->unique(['podcast_id', 'slug']);
            $table->index(['podcast_id', 'is_published', 'published_at']);
        });

        Schema::create('podcast_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('podcast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // One row per person per show; the order they were added is
            // the order the feed names them
            $table->unique(['podcast_id', 'user_id']);
        });

        $this->seedApprovalTicketType();
        $this->seedAutoApproveRoles();
        $this->seedManagePermission();
    }

    public function down(): void
    {
        DB::table('ticket_types')->where('slug', 'podcast_approval')->delete();
        DB::table('settings')->where('key', 'auto_approve_roles_podcast')->delete();

        Schema::dropIfExists('podcast_authors');
        Schema::dropIfExists('podcast_episodes');
        Schema::dropIfExists('podcasts');
    }

    private function seedApprovalTicketType(): void
    {
        if (DB::table('ticket_types')->where('slug', 'podcast_approval')->exists()) {
            return;
        }

        DB::table('ticket_types')->insert([
            'name'        => 'Podcast Approval',
            'slug'        => 'podcast_approval',
            'description' => 'Automatically created when a new podcast show is submitted',
            'icon'        => 'mdi-podcast',
            'color'       => '#00ACC1',
            'is_active'   => true,
            // Without this, HasTickets::autoCreateTicket finds nothing and
            // the submission would quietly go unreviewed
            'auto_create' => true,
            'position'    => 7,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    private function seedAutoApproveRoles(): void
    {
        if (DB::table('settings')->where('key', 'auto_approve_roles_podcast')->exists()) {
            return;
        }

        DB::table('settings')->insert([
            'key'        => 'auto_approve_roles_podcast',
            'value'      => json_encode(['admin']),
            'type'       => 'json',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Admins pass without holding it; see EnsureUserHasPermission.
     * PermissionsTableSeeder lists it too, and RolesTableSeeder gives the
     * admin role every permission there is each time it runs, so an admin
     * is offered the pages this guards rather than bounced off them.
     */
    private function seedManagePermission(): void
    {
        if (DB::table('permissions')->where('name', 'manage-podcast')->exists()) {
            return;
        }

        DB::table('permissions')->insert([
            'name'         => 'manage-podcast',
            'guard_name'   => 'api',
            'display_name' => 'Manage Podcast',
            'description'  => 'Create shows, write episodes and upload their audio, without the admin panel',
            'category'     => 'podcast',
            'is_default'   => 0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }
};
