<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Directory of user accounts from the legacy systems, imported via the
 * mapping tool (target "legacy_users"). Gives the Legacy Users tab the
 * full roster — including people without imported content — and the
 * legacy e-mail, which is used to verify claims and suggest matches
 * with registered users.
 */
return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_legacy_users');
    }
};
