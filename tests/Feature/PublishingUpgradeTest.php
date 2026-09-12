<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The publishing migration has to upgrade databases that already hold rows,
 * not only build a fresh schema. Each test puts the old columns back, fills
 * them the way a real database looks, then runs the migration.
 */
class PublishingUpgradeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Recreate the pre-migration shape: published/status present, no published_at.
     * The index goes first, because SQLite keeps it behind when the column drops.
     */
    private function rollbackToOldShape(): void
    {
        Schema::table('pages', function ($table) {
            $table->dropIndex('pages_published_at_index');
            $table->dropColumn('published_at');
            $table->integer('published')->default(0);
        });

        Schema::table('posts', function ($table) {
            $table->dropIndex('posts_published_at_index');
            $table->dropColumn('published_at');
            $table->string('status')->default('draft');
        });
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_12_000000_align_page_and_post_publishing.php');
    }

    public function test_it_upgrades_a_database_that_already_holds_rows(): void
    {
        $this->rollbackToOldShape();

        $created = '2024-03-04 10:00:00';
        DB::table('pages')->insert([
            ['slug' => 'live-page', 'published' => 1, 'user_id' => 1, 'created_at' => $created, 'updated_at' => $created],
            ['slug' => 'draft-page', 'published' => 0, 'user_id' => 1, 'created_at' => $created, 'updated_at' => $created],
        ]);
        DB::table('posts')->insert([
            ['slug' => 'live-post', 'status' => 'published', 'user_id' => 1, 'created_at' => $created, 'updated_at' => $created],
            ['slug' => 'draft-post', 'status' => 'draft', 'user_id' => 1, 'created_at' => $created, 'updated_at' => $created],
        ]);

        $this->migration()->up();

        $this->assertTrue(Schema::hasColumn('pages', 'published_at'));
        $this->assertTrue(Schema::hasColumn('posts', 'published_at'));
        $this->assertFalse(Schema::hasColumn('pages', 'published'));
        $this->assertFalse(Schema::hasColumn('posts', 'status'));

        // What was live keeps its creation date; drafts stay empty
        $this->assertSame($created, (string) DB::table('pages')->where('slug', 'live-page')->value('published_at'));
        $this->assertNull(DB::table('pages')->where('slug', 'draft-page')->value('published_at'));
        $this->assertSame($created, (string) DB::table('posts')->where('slug', 'live-post')->value('published_at'));
        $this->assertNull(DB::table('posts')->where('slug', 'draft-post')->value('published_at'));
    }

    public function test_it_reverses_back_to_the_old_columns(): void
    {
        $this->rollbackToOldShape();

        DB::table('pages')->insert(['slug' => 'p', 'published' => 1, 'user_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('posts')->insert(['slug' => 'q', 'status' => 'draft', 'user_id' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $migration = $this->migration();
        $migration->up();
        $migration->down();

        $this->assertTrue(Schema::hasColumn('pages', 'published'));
        $this->assertTrue(Schema::hasColumn('posts', 'status'));
        $this->assertFalse(Schema::hasColumn('pages', 'published_at'));
        $this->assertSame(1, (int) DB::table('pages')->where('slug', 'p')->value('published'));
        $this->assertSame('draft', DB::table('posts')->where('slug', 'q')->value('status'));
    }

    public function test_running_it_again_on_an_upgraded_database_changes_nothing(): void
    {
        // The schema already carries published_at from the normal migration run
        $this->migration()->up();

        $this->assertTrue(Schema::hasColumn('pages', 'published_at'));
        $this->assertFalse(Schema::hasColumn('pages', 'published'));
        $this->assertTrue(Schema::hasColumn('posts', 'published_at'));
        $this->assertFalse(Schema::hasColumn('posts', 'status'));
    }
}
