<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pages and posts get one publishing scheme.
 *
 * `pages.published` (0/1) and `posts.status` ('draft'/'published') are replaced
 * by a single nullable, indexed `published_at` timestamp:
 *
 *   null     → draft
 *   > now()  → scheduled
 *   <= now() → published
 *
 * `pages.sign_in_only` is a different concern (who may read a published page)
 * and is deliberately left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pages', 'published_at')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->timestamp('published_at')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('posts', 'published_at')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->timestamp('published_at')->nullable()->index();
            });
        }

        // Anything that was live keeps its creation date as the publish date;
        // drafts stay null.
        if (Schema::hasColumn('pages', 'published')) {
            $this->backfill('pages', fn ($query) => $query->where('published', '>', 0));

            Schema::table('pages', function (Blueprint $table) {
                $table->dropColumn('published');
            });
        }

        if (Schema::hasColumn('posts', 'status')) {
            $this->backfill('posts', fn ($query) => $query->where('status', 'published'));

            Schema::table('posts', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('pages', 'published')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->integer('published')->default(0);
            });
        }

        if (! Schema::hasColumn('posts', 'status')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->string('status')->default('draft');
            });
        }

        // The old columns have no notion of "scheduled", so anything carrying a
        // publish date comes back as published.
        DB::table('pages')->whereNotNull('published_at')->update(['published' => 1]);
        DB::table('posts')->whereNotNull('published_at')->update(['status' => 'published']);

        // The index goes first: SQLite refuses to drop a column an index still covers.
        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex('pages_published_at_index');
            $table->dropColumn('published_at');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_published_at_index');
            $table->dropColumn('published_at');
        });
    }

    /**
     * Copy created_at (or now(), for rows without one) into published_at for
     * every row the filter selects. Query builder only, so it behaves the same
     * on MySQL and on the SQLite the tests use.
     */
    private function backfill(string $table, Closure $filter): void
    {
        $query = DB::table($table)->select('id', 'created_at');
        $filter($query);

        $query->orderBy('id')->chunkById(500, function ($rows) use ($table) {
            foreach ($rows as $row) {
                DB::table($table)
                    ->where('id', $row->id)
                    ->update(['published_at' => $row->created_at ?: now()]);
            }
        }, 'id');
    }
};
