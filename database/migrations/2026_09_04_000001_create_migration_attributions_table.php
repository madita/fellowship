<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which legacy (pre-migration) username each imported record
 * belonged to, so old content can later be assigned to the matching
 * registered user (via the admin Legacy Users tab / claim tickets).
 */
return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_attributions');
    }
};
