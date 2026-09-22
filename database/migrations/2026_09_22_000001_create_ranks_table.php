<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ranks.
 *
 * A member's rank is read from the points their achievements add up to:
 * the highest rank they have reached the threshold for. Nothing is stored
 * on the member, so editing a rank's threshold re-ranks everyone at once
 * rather than leaving stale titles behind.
 *
 * The names live in rank_translations, created with the other translation
 * tables.
 */
return new class extends Migration
{
    /**
     * Something to start with, spread so the early ones come quickly and
     * the last is a long haul. Admins edit or delete them freely.
     */
    private const RANKS = [
        ['newcomer', 0, 'mdi-sprout-outline', 'blue-grey', ['Newcomer', 'Just arrived.'], ['Neuling', 'Gerade angekommen.']],
        ['member', 50, 'mdi-account-outline', 'teal', ['Member', 'Part of the furniture.'], ['Mitglied', 'Gehört dazu.']],
        ['regular', 150, 'mdi-account-star-outline', 'blue', ['Regular', 'Around often, and it shows.'], ['Stammgast', 'Oft da — und man merkt es.']],
        ['veteran', 300, 'mdi-shield-star-outline', 'deep-purple', ['Veteran', 'Has been here a while, and done a lot.'], ['Veteran', 'Schon lange dabei und viel bewegt.']],
        ['elder', 600, 'mdi-crown-outline', 'amber', ['Elder', 'One of the people who built this place.'], ['Ältester', 'Einer von denen, die das hier aufgebaut haben.']],
        ['legend', 1000, 'mdi-trophy-variant-outline', 'orange', ['Legend', 'There is a story about you.'], ['Legende', 'Über dich gibt es Geschichten.']],
    ];

    public function up(): void
    {
        Schema::create('ranks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            // What the member's achievement points have to reach
            $table->unsignedInteger('points_required')->default(0);
            $table->string('icon')->default('mdi-shield-outline');
            // A rank can wear a picture instead of the icon, like a badge
            $table->string('image_path')->nullable();
            $table->string('color')->default('blue-grey');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['is_enabled', 'points_required']);
        });

        // The wording table belongs with the other translation tables, but
        // that migration has already run on an existing install and will
        // not run again — so create it here when it is missing.
        if (! Schema::hasTable('rank_translations')) {
            Schema::create('rank_translations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rank_id');
                $table->string('locale')->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unique(['rank_id', 'locale']);
                $table->timestamps();
            });
        }

        Schema::table('rank_translations', function (Blueprint $table) {
            $table->foreign('rank_id')->references('id')->on('ranks')->cascadeOnDelete();
        });

        $this->seedRanks();
    }

    private function seedRanks(): void
    {
        $now = now();

        foreach (self::RANKS as [$key, $points, $icon, $color, $en, $de]) {
            $id = DB::table('ranks')->insertGetId([
                'key'             => $key,
                'points_required' => $points,
                'icon'            => $icon,
                'color'           => $color,
                'is_enabled'      => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            foreach (['en' => $en, 'de' => $de] as $locale => $wording) {
                DB::table('rank_translations')->insert([
                    'rank_id'     => $id,
                    'locale'      => $locale,
                    'name'        => $wording[0],
                    'description' => $wording[1],
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // The wording table outlives this migration, so only its link goes
        if (Schema::hasTable('rank_translations')) {
            Schema::table('rank_translations', function (Blueprint $table) {
                $table->dropForeign(['rank_id']);
            });
        }

        Schema::dropIfExists('ranks');
    }
};
