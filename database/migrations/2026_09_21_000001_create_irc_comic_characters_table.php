<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comic chat characters. A character is a set of part choices rather than a
 * drawing of its own, so the ones that ship with the site and anything built
 * in the character creator are stored and drawn the same way.
 */
return new class extends Migration
{
    /**
     * The eight that used to be written into the client, as part choices.
     */
    private const BUILT_IN = [
        ['key' => 'cat', 'name' => 'Cat', 'hue' => 30, 'tone' => 'normal', 'body' => 'slim', 'head' => 'round', 'ears' => 'cat', 'sideParts' => 'whiskers', 'hat' => 'none', 'snout' => 'cat', 'mask' => 'none', 'accessory' => 'none'],
        ['key' => 'dog', 'name' => 'Dog', 'hue' => 25, 'tone' => 'normal', 'body' => 'slim', 'head' => 'round', 'ears' => 'none', 'sideParts' => 'floppy', 'hat' => 'none', 'snout' => 'dog', 'mask' => 'none', 'accessory' => 'none'],
        ['key' => 'robot', 'name' => 'Robot', 'hue' => 200, 'tone' => 'metal', 'body' => 'boxy', 'head' => 'box', 'ears' => 'none', 'sideParts' => 'panels', 'hat' => 'antenna', 'snout' => 'none', 'mask' => 'none', 'accessory' => 'none'],
        ['key' => 'alien', 'name' => 'Alien', 'hue' => 120, 'tone' => 'bright', 'body' => 'slim', 'head' => 'egg', 'ears' => 'none', 'sideParts' => 'none', 'hat' => 'feelers', 'snout' => 'none', 'mask' => 'none', 'accessory' => 'none'],
        ['key' => 'wizard', 'name' => 'Wizard', 'hue' => 270, 'tone' => 'normal', 'body' => 'robe', 'head' => 'round', 'ears' => 'none', 'sideParts' => 'none', 'hat' => 'wizard', 'snout' => 'none', 'mask' => 'none', 'accessory' => 'beard'],
        ['key' => 'ninja', 'name' => 'Ninja', 'hue' => 205, 'tone' => 'dark', 'body' => 'slim', 'head' => 'round', 'ears' => 'none', 'sideParts' => 'none', 'hat' => 'none', 'snout' => 'none', 'mask' => 'ninja', 'accessory' => 'scarf'],
        ['key' => 'pirate', 'name' => 'Pirate', 'hue' => 45, 'tone' => 'normal', 'body' => 'slim', 'head' => 'round', 'ears' => 'none', 'sideParts' => 'none', 'hat' => 'bandana', 'snout' => 'none', 'mask' => 'none', 'accessory' => 'eyepatch'],
        ['key' => 'knight', 'name' => 'Knight', 'hue' => 210, 'tone' => 'metal', 'body' => 'sturdy', 'head' => 'helmet', 'ears' => 'none', 'sideParts' => 'none', 'hat' => 'plume', 'snout' => 'none', 'mask' => 'visor', 'accessory' => 'none'],
    ];

    public function up(): void
    {
        Schema::create('irc_comic_characters', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            // The part choices the character is drawn from
            $table->json('spec');
            $table->boolean('is_enabled')->default(true);
            // The ones that ship with the site cannot be deleted, so a member
            // who picked one never loses their character
            $table->boolean('is_builtin')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_enabled', 'sort_order']);
        });

        $now = now();

        DB::table('irc_comic_characters')->insert(array_map(function (array $character, int $index) use ($now) {
            $key  = $character['key'];
            $name = $character['name'];
            unset($character['key'], $character['name']);

            return [
                'key'        => $key,
                'name'       => $name,
                'spec'       => json_encode($character),
                'is_enabled' => true,
                'is_builtin' => true,
                'sort_order' => $index,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, self::BUILT_IN, array_keys(self::BUILT_IN)));
    }

    public function down(): void
    {
        Schema::dropIfExists('irc_comic_characters');
    }
};
