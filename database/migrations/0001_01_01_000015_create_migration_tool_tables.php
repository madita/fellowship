<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The import tool: bringing an old community's content in.
 *
 * A source is a database somewhere else; a mapping says which of its
 * columns fill which of our fields, for one of the targets registered in
 * MigrationTargets. The rest is memory of what was imported - an id map
 * so a second run updates rather than duplicates, and the legacy
 * usernames so content written years ago can later be handed to the
 * member who registered under the same name. And the links: one row
 * per distinct address the imported content points at, so a link used
 * on forty pages is checked once and fixed in one place.
 */
return new class extends Migration
{
    public function up(): void
    {
        // External databases the tool can read from
        Schema::create('migration_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // mysql, mariadb, pgsql, sqlite, sqlsrv
            $table->string('driver')->default('mysql');
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            // A file path for sqlite
            $table->string('database');
            $table->string('username')->nullable();
            // Encrypted cast
            $table->text('password')->nullable();
            $table->string('charset')->nullable();
            $table->timestamps();
        });

        // Column mappings from a source table onto one of our targets
        Schema::create('migration_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_source_id')->constrained('migration_sources')->cascadeOnDelete();
            $table->string('name');
            // A key from the MigrationTargets registry
            $table->string('target');
            $table->string('source_table');
            // targetField => {source, transform, format, default}
            $table->json('field_map');
            $table->json('options')->nullable();
            $table->timestamps();
        });

        /**
         * Which legacy username each imported record belonged to, so old
         * content can later be assigned to the matching registered member
         * - through the admin Legacy Users tab, or a claim ticket.
         */
        Schema::create('migration_attributions', function (Blueprint $table) {
            $table->id();
            $table->morphs('attributable');
            // Which legacy system the username belongs to (e.g. "wiki",
            // "forum") - the same name in different systems can be
            // different people, so identity is (source, username)
            $table->string('legacy_source')->default('legacy');
            $table->string('legacy_username');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->index(['legacy_source', 'legacy_username']);
            $table->unique(['attributable_type', 'attributable_id', 'legacy_source', 'legacy_username'], 'migration_attribution_unique');
        });

        Schema::create('migration_legacy_users', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_source');
            $table->string('username');
            $table->string('email')->nullable()->index();
            $table->string('legacy_user_id')->nullable();
            $table->string('real_name')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['legacy_source', 'username']);
        });

        // What the old id became, so a re-run updates rather than doubles
        Schema::create('migration_id_maps', function (Blueprint $table) {
            $table->id();
            // e.g. "forum_topic", "forum_post"
            $table->string('context');
            $table->string('legacy_id');
            $table->morphs('mappable');
            $table->timestamps();

            $table->unique(['context', 'legacy_id']);
        });

        // Progress of a run, so a long import can be watched and resumed
        Schema::create('migration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id')->index();
            $table->string('migration_key');
            $table->string('migration_name');
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->integer('total_items')->default(0);
            $table->integer('processed_items')->default(0);
            $table->integer('error_count')->default(0);
            $table->text('current_item')->nullable();
            $table->text('last_error')->nullable();
            $table->json('logs')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Every link the imported content points at, and whether it
        // still leads anywhere. Fifteen-year-old content links to a web
        // that has mostly gone: forums that closed, image hosts that
        // shut down, and the old site's own domain, which nobody will
        // renew once this one is live.
        Schema::create('migration_links', function (Blueprint $table) {
            $table->id();
            // A URL can be longer than an index key, so identity is the
            // hash of it - the column it is looked up and deduped by
            $table->string('url_hash', 64)->unique();
            $table->text('url');
            $table->string('host')->nullable()->index();
            // internal (this site), legacy (the site being replaced) or
            // external (somewhere else entirely)
            $table->string('scope', 20)->index();
            // link or image: a dead image leaves a hole in the page, a
            // dead link only disappoints whoever clicks it
            $table->string('kind', 20)->default('link');
            // pending, ok, broken, unsure, skipped
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            // Where it ended up, when that is somewhere else than asked
            $table->text('redirect_url')->nullable();
            // Not a string: a cURL error names the host, the URL it was
            // asked for and a page to read about it, which is longer
            // than a varchar in this schema holds
            $table->text('reason')->nullable();
            // [{place, id, title, url, text, kind}] - capped, because a
            // navigation link copied onto every page needs no full list
            $table->json('occurrences')->nullable();
            $table->unsignedInteger('occurrence_count')->default(0);
            // Which scan last found it: a rescan drops what is gone
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_links');
        Schema::dropIfExists('migration_logs');
        Schema::dropIfExists('migration_id_maps');
        Schema::dropIfExists('migration_legacy_users');
        Schema::dropIfExists('migration_attributions');
        Schema::dropIfExists('migration_mappings');
        Schema::dropIfExists('migration_sources');
    }
};
