<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // External databases the migration tool can read from.
        Schema::create('migration_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('driver')->default('mysql'); // mysql, mariadb, pgsql, sqlite, sqlsrv
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('database'); // file path for sqlite
            $table->string('username')->nullable();
            $table->text('password')->nullable(); // encrypted cast
            $table->string('charset')->nullable();
            $table->timestamps();
        });

        // Column mappings from a source table onto one of our feature targets.
        Schema::create('migration_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_source_id')->constrained('migration_sources')->cascadeOnDelete();
            $table->string('name');
            $table->string('target');       // key from MigrationTargets registry
            $table->string('source_table');
            $table->json('field_map');      // targetField => {source, transform, format, default}
            $table->json('options')->nullable();
            $table->timestamps();
        });

        /**
         * Records which legacy (pre-migration) username each imported record
         * belonged to, so old content can later be assigned to the matching
         * registered user (via the admin Legacy Users tab / claim tickets).
         */

        Schema::create('migration_attributions', function (Blueprint $table) {
            $table->id();
            $table->morphs('attributable');
            // Which legacy system the username belongs to (e.g. "wiki",
            // "forum") — the same name in different systems can be
            // different people, so identity is (source, username).
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

        Schema::create('migration_id_maps', function (Blueprint $table) {
            $table->id();
            $table->string('context'); // e.g. "forum_topic", "forum_post"
            $table->string('legacy_id');
            $table->morphs('mappable');
            $table->timestamps();

            $table->unique(['context', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_id_maps');
        Schema::dropIfExists('migration_legacy_users');
        Schema::dropIfExists('migration_attributions');
        Schema::dropIfExists('migration_mappings');
        Schema::dropIfExists('migration_sources');
    }
};
