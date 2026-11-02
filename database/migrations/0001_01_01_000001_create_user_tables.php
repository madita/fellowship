<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who a member is, and what they are allowed to do.
 *
 * Everything else in the schema points at `users`, so this runs first.
 * The roles and permissions tables are Spatie's, written out with the
 * names the package is configured with rather than read back out of the
 * config at migrate time - they have never been anything else, and a
 * schema that depends on a config file is a schema you cannot read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();

            $table->datetime('previous_login_at')->nullable();
            $table->datetime('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();

            // How the site is shown to them
            $table->string('timezone', 64)->nullable();
            $table->string('date_format', 32)->nullable();
            $table->string('time_format', 32)->nullable();
            $table->string('theme_mode', 16)->nullable();
            $table->string('language', 8)->nullable();
            $table->json('dashboard_layout')->nullable();

            // A notification already arrives over the socket; this is
            // whether it also says so out loud. On by default, because the
            // toast is the useful half of a realtime notification.
            $table->boolean('notification_toasts')->default(true);

            $table->timestamps();
        });

        Schema::create('password_resets', function (Blueprint $table) {
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        /**
         * The rest of what a member can tell us about themselves.
         *
         * Kept apart from `users` because most of it is optional and none
         * of it is needed to sign somebody in. Split by who may see it: a
         * street address and a phone number are for the site's own records
         * and never leave it, while the rest is the member's to show or
         * keep - see the visibility column and UserProfile::SHAREABLE.
         */
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // The member's to show if they want to
            $table->text('bio')->nullable();
            $table->string('pronouns', 40)->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('website')->nullable();
            $table->date('birthday')->nullable();
            // { "discord": "...", "github": "..." }
            $table->json('socials')->nullable();

            // Never shown to anybody else, whatever the member picks
            $table->string('phone', 40)->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('state')->nullable();

            // Which of the shareable fields the member lets others see
            $table->json('visibility')->nullable();

            $table->timestamps();
        });

        // Signing in through google, discord, github or facebook
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_id');
            $table->string('provider_token', 500)->nullable();
            $table->string('provider_refresh_token', 500)->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->text('avatar')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
            $table->index('user_id');
        });

        // Sanctum
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        // A key a member makes for a script of their own, which is not the
        // same thing as the token the site's own client holds
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key', 64)->unique();
            $table->string('secret_hash');
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('request_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index('key');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        $this->createPermissionTables();
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');

        Schema::dropIfExists('notifications');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('password_resets');
        Schema::dropIfExists('users');
    }

    /**
     * Spatie's five, with the three columns this site added: a display
     * name, a description and a category, so the admin screens can group
     * permissions and call them something a person would recognise.
     */
    private function createPermissionTables(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('category', 50)->nullable();
            $table->integer('is_default')->default(0);
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->tinyInteger('is_hidden')->default(0);
            $table->integer('is_default')->default(0);
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->primary(
                ['permission_id', 'model_id', 'model_type'],
                'model_has_permissions_permission_model_type_primary'
            );
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(
                ['role_id', 'model_id', 'model_type'],
                'model_has_roles_role_model_type_primary'
            );
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });
    }
};
