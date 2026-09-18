<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Public feedback: bug reports and feature requests are ordinary tickets
 * (types `bug` / `feature`) that members can see, vote on, watch and tag.
 * They use the normal ticket status — there is no separate workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Off by default: only tickets filed through the feedback pages are public,
            // support requests, approvals and account claims stay private.
            $table->boolean('is_public')->default(false)->after('priority')->index();
            $table->foreignId('duplicate_of_ticket_id')->nullable()->after('is_public')
                ->constrained('tickets')->nullOnDelete();
        });

        Schema::table('ticket_comments', function (Blueprint $table) {
            // Written by an admin: shown as the team's answer
            $table->boolean('is_official')->default(false)->after('is_internal');
        });

        Schema::create('ticket_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ticket_id', 'user_id']);
        });

        Schema::create('ticket_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ticket_id', 'user_id']);
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

        $tags = [
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

        DB::table('ticket_tags')->insert(array_map(fn (array $tag) => [
            'name'       => $tag[0],
            'slug'       => $tag[1],
            'color'      => $tag[2],
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $tags));
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_ticket_tag');
        Schema::dropIfExists('ticket_tags');
        Schema::dropIfExists('ticket_watchers');
        Schema::dropIfExists('ticket_votes');

        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropColumn('is_official');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('duplicate_of_ticket_id');
            $table->dropIndex(['is_public']);
            $table->dropColumn('is_public');
        });
    }
};
