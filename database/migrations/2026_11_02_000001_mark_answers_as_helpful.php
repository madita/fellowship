<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marking the answer that helped, on a forum reply and now on a comment.
 *
 * The forum had `forum_posts.is_solution`: a boolean, with nothing saying
 * who decided or when, and nothing like it anywhere else. Comments could
 * not be marked at all, although a wiki page's discussion is exactly where
 * somebody answers a question.
 *
 * Both tables get the same two columns, and `helpful_at` replaces the
 * boolean outright rather than sitting beside it - a flag and a timestamp
 * that have to agree about the same fact eventually stop agreeing.
 *
 * The marks already made are carried over. Nobody recorded who made them,
 * so `helpful_by_id` stays empty for those and `updated_at` stands in for
 * when, being the closest thing the row remembers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->timestamp('helpful_at')->nullable()->after('is_solution');
            $table->foreignId('helpful_by_id')->nullable()->after('helpful_at')
                ->constrained('users')->nullOnDelete();

            // The forum index filters threads down to the answered ones
            $table->index('helpful_at');
        });

        DB::table('forum_posts')
            ->where('is_solution', true)
            ->update(['helpful_at' => DB::raw('updated_at')]);

        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropColumn('is_solution');
        });

        Schema::table('commentable', function (Blueprint $table) {
            $table->timestamp('helpful_at')->nullable()->after('content');
            $table->foreignId('helpful_by_id')->nullable()->after('helpful_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->boolean('is_solution')->default(false)->after('meta');
        });

        DB::table('forum_posts')
            ->whereNotNull('helpful_at')
            ->update(['is_solution' => true]);

        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('helpful_by_id');
            $table->dropIndex(['helpful_at']);
            $table->dropColumn('helpful_at');
        });

        Schema::table('commentable', function (Blueprint $table) {
            $table->dropConstrainedForeignId('helpful_by_id');
            $table->dropColumn('helpful_at');
        });
    }
};
