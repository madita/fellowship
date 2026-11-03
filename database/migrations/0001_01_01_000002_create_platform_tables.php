<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tables that belong to no feature: the queue's failures, the site's
 * own settings, uploaded files, the revision history any model can keep,
 * the activity log and the Discord webhooks that announce things.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        // Everything an admin can change about the site, one row per key.
        // `type` says how to read `value` back: string, boolean, integer,
        // json, file.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->timestamps();
        });

        // spatie/laravel-medialibrary. The four payload columns are text
        // rather than json because they were written before this database
        // could be relied on for json.
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->uuid('uuid')->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->text('manipulations');
            $table->text('custom_properties');
            $table->text('generated_conversions');
            $table->text('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();
            $table->nullableTimestamps();
        });

        // A named shelf to file uploads under, in the media manager
        Schema::create('media_libraries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // What a model looked like before somebody changed it; see the
        // Revisionable trait for which models keep one
        Schema::create('revisions', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            $table->string('revisionable_type');
            $table->integer('revisionable_id');
            $table->longText('old_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->integer('user_id')->nullable();
            $table->string('ip')->nullable();
            $table->string('ip_forwarded')->nullable();
            $table->timestamps();

            $table->index('action');
            $table->index(['revisionable_id', 'revisionable_type']);
        });

        // spatie/laravel-activitylog
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();

            $table->index('log_name');
        });

        /**
         * Discord webhooks: an admin pastes the webhook address of a
         * channel and picks which events are announced there.
         */
        Schema::create('discord_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Encrypted: the address is the credential, anyone holding it can post
            $table->text('url');
            $table->json('events');
            $table->string('locale', 10)->nullable();
            $table->boolean('is_active')->default(true);

            // What happened the last time this webhook was used
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->string('last_error')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_webhooks');
        Schema::dropIfExists('activity_log');
        Schema::dropIfExists('revisions');
        Schema::dropIfExists('media_libraries');
        Schema::dropIfExists('media');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('failed_jobs');
    }
};
