<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tickets: one queue for everything that needs somebody to look at it.
 *
 * A support request, a wiki page waiting for approval, a bug report and a
 * feature request are all tickets, told apart by their type. A type with
 * `auto_create` set is opened by the site itself - that is how an
 * approval arrives - and HasTickets looks the type up by slug, so the
 * types are reference data rather than example content and are written
 * here with the tables.
 *
 * Public feedback (types `bug` and `feature`) is the same ticket seen
 * from the front: members can read it, vote on it, watch it and tag it,
 * using the shared voteable and watches tables. There is no second
 * workflow and no second status.
 */
return new class extends Migration
{
    /**
     * The types the site itself asks for by slug. Admins add their own.
     */
    private const TYPES = [
        ['Support Request', 'support', 'General support requests and help tickets', 'mdi-help-circle', '#2196F3', false],
        ['Bug Report', 'bug', 'Report bugs and technical issues', 'mdi-bug', '#F44336', false],
        ['Wiki Approval', 'wiki_approval', 'Automatically created when new wiki pages are submitted', 'mdi-book-open-variant', '#4CAF50', true],
        ['Story Submission', 'story_submission', 'User-submitted stories awaiting moderation', 'mdi-script-text', '#9C27B0', true],
        ['Feature Request', 'feature', 'Suggestions for new features', 'mdi-lightbulb', '#FF9800', false],
        ['Content Moderation', 'moderation', 'Content requiring moderator review', 'mdi-shield-check', '#FFC107', true],
    ];

    /**
     * Tags for the feedback board, so it arrives sortable.
     */
    private const TAGS = [
        ['Critical', 'critical', '#D32F2F'],
        ['UI/UX', 'ui-ux', '#7B1FA2'],
        ['Performance', 'performance', '#F57C00'],
        ['Mobile', 'mobile', '#0288D1'],
        ['Security', 'security', '#C62828'],
        ['Enhancement', 'enhancement', '#388E3C'],
        ['New Feature', 'new-feature', '#1976D2'],
        ['Integration', 'integration', '#5E35B1'],
        ['Accessibility', 'accessibility', '#00897B'],
        ['Documentation', 'documentation', '#6D4C41'],
    ];

    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            // Opened by the site rather than by a member
            $table->boolean('auto_create')->default(false);
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_type_id')->constrained('ticket_types')->cascadeOnDelete();

            // What the ticket is about, when it is about something
            $table->nullableMorphs('ticketable');

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->enum('status', ['open', 'in_progress', 'pending', 'resolved', 'closed'])->default('open');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');

            // Off by default: only tickets filed through the feedback
            // pages are public. Support requests, approvals and account
            // claims stay private.
            $table->boolean('is_public')->default(false)->index();
            $table->foreignId('duplicate_of_ticket_id')->nullable()->constrained('tickets')->nullOnDelete();

            $table->timestamp('due_date')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            // Whatever this type of ticket needs to remember
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'priority', 'created_at']);
            $table->index(['assigned_to_user_id', 'status']);
        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('comment');
            // A note between moderators, not shown to the member
            $table->boolean('is_internal')->default(false);
            // Written by an admin: shown as the team's answer
            $table->boolean('is_official')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('ticket_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color')->default('#9E9E9E');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ticket_ticket_tag', function (Blueprint $table) {
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['ticket_id', 'ticket_tag_id']);
        });

        $now = now();

        DB::table('ticket_types')->insert(array_map(fn (array $type, int $index) => [
            'name'        => $type[0],
            'slug'        => $type[1],
            'description' => $type[2],
            'icon'        => $type[3],
            'color'       => $type[4],
            'is_active'   => true,
            'auto_create' => $type[5],
            'position'    => $index + 1,
            'created_at'  => $now,
            'updated_at'  => $now,
        ], self::TYPES, array_keys(self::TYPES)));

        DB::table('ticket_tags')->insert(array_map(fn (array $tag) => [
            'name'       => $tag[0],
            'slug'       => $tag[1],
            'color'      => $tag[2],
            'is_active'  => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::TAGS));
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_ticket_tag');
        Schema::dropIfExists('ticket_tags');
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_types');
    }
};
