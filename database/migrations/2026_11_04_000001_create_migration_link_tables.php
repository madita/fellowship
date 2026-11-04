<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every link the imported content points at, and whether it still leads
 * anywhere.
 *
 * Content written fifteen years ago links to a web that has mostly gone:
 * forums that closed, image hosts that shut down, and the old site's own
 * domain, which nobody will renew once this one is live. One row per
 * distinct address, with the places it appears kept alongside it, so a
 * link used on forty pages is checked once and fixed in one place.
 */
return new class extends Migration
{
    public function up(): void
    {
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
    }
};
