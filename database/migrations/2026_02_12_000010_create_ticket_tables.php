<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Support, Wiki Approval, Story Submission, etc.
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // Icon identifier for UI
            $table->string('color')->nullable(); // Color code for UI
            $table->json('config')->nullable(); // Type-specific configuration
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_create')->default(false); // Auto-create on trigger
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_type_id')->constrained('ticket_types')->onDelete('cascade');

            // Polymorphic relation - what this ticket is about
            $table->morphs('ticketable'); // ticketable_type, ticketable_id

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->onDelete('set null');

            $table->string('title');
            $table->text('description')->nullable();

            $table->enum('status', ['open', 'in_progress', 'pending', 'resolved', 'closed'])->default('open');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');

            $table->timestamp('due_date')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->json('metadata')->nullable(); // Type-specific data

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'priority', 'created_at']);
            $table->index(['assigned_to_user_id', 'status']);
        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('comment');
            $table->boolean('is_internal')->default(false); // Internal notes vs public comments
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ticket_id', 'created_at']);
        });

        // Seed default ticket types
        DB::table('ticket_types')->insert([
            [
                'name' => 'Support Request',
                'slug' => 'support',
                'description' => 'General support requests and help tickets',
                'icon' => 'mdi-help-circle',
                'color' => '#2196F3',
                'is_active' => true,
                'auto_create' => false,
                'position' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Bug Report',
                'slug' => 'bug',
                'description' => 'Report bugs and technical issues',
                'icon' => 'mdi-bug',
                'color' => '#F44336',
                'is_active' => true,
                'auto_create' => false,
                'position' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Wiki Approval',
                'slug' => 'wiki_approval',
                'description' => 'Automatically created when new wiki pages are submitted',
                'icon' => 'mdi-book-open-variant',
                'color' => '#4CAF50',
                'is_active' => true,
                'auto_create' => true,
                'position' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Story Submission',
                'slug' => 'story_submission',
                'description' => 'User-submitted stories awaiting moderation',
                'icon' => 'mdi-script-text',
                'color' => '#9C27B0',
                'is_active' => true,
                'auto_create' => true,
                'position' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Feature Request',
                'slug' => 'feature',
                'description' => 'Suggestions for new features',
                'icon' => 'mdi-lightbulb',
                'color' => '#FF9800',
                'is_active' => true,
                'auto_create' => false,
                'position' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Content Moderation',
                'slug' => 'moderation',
                'description' => 'Content requiring moderator review',
                'icon' => 'mdi-shield-check',
                'color' => '#FFC107',
                'is_active' => true,
                'auto_create' => true,
                'position' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_types');
    }
};
