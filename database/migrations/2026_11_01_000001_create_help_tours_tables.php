<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Help that an admin writes, shown on the page it is about.
 *
 * A tour is a page and an ordered list of steps. Each step points at an
 * element — a CSS selector, or the `data-help` name a component carries —
 * and says something about it. In `tour` mode the steps are walked through
 * one at a time behind a dimmed page; in `hints` mode each one becomes a
 * small marker sitting beside its element, read in any order. Same rows,
 * two ways of showing them.
 *
 * `route_pattern` is matched against the path with Str::is, so `/wiki/*`
 * covers every page of the wiki and `/forum` only the one.
 *
 * The wording lives in help_tour_translations and
 * help_tour_step_translations, so a tour reads in the member's own
 * language like everything else. help_tour_views remembers who has
 * already been shown one, which is what stops a tour that starts by
 * itself from starting again every visit; guests are remembered in their
 * own browser instead, since there is nobody to write a row for.
 */
return new class extends Migration
{
    /**
     * One tour to arrive with, so the feature is not an empty list and the
     * shape of a step is visible. It points at the `data-help` names the
     * toolbar carries rather than at classes, which is the anchoring that
     * survives a redesign. Admins edit or delete it freely.
     */
    private const WELCOME = [
        'key'           => 'welcome',
        'route_pattern' => '/dashboard',
        'mode'          => 'tour',
        'icon'          => 'mdi-hand-wave-outline',
        'color'         => 'primary',
        'audience'      => 'members',
        'auto_start'    => true,
        'en'            => ['Welcome', 'A quick look around, the first time you arrive.'],
        'de'            => ['Willkommen', 'Ein kurzer Rundgang, wenn du das erste Mal hier bist.'],
        'steps'         => [
            [
                'target'    => '[data-help="toolbar-notifications"]',
                'placement' => 'bottom',
                'en'        => ['Your notifications', 'Anything that happens for you turns up here, and a toast says so as it arrives.'],
                'de'        => ['Deine Benachrichtigungen', 'Alles, was dich betrifft, landet hier — und eine Kurzmeldung sagt es im Moment, in dem es passiert.'],
            ],
            [
                'target'    => '[data-help="toolbar-settings"]',
                'placement' => 'bottom',
                'en'        => ['Your settings', 'Theme, language, timezone, and whether those toasts appear at all.'],
                'de'        => ['Deine Einstellungen', 'Darstellung, Sprache, Zeitzone — und ob die Kurzmeldungen überhaupt erscheinen.'],
            ],
            [
                'target'    => '[data-help="toolbar-help"]',
                'placement' => 'bottom',
                'en'        => ['Help, whenever you want it', 'This button brings back whatever help has been written for the page you are on.'],
                'de'        => ['Hilfe, wann du willst', 'Dieser Knopf holt die Hilfe zurück, die für die aktuelle Seite geschrieben wurde.'],
            ],
        ],
    ];

    public function up(): void
    {
        Schema::create('help_tours', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();

            // Which pages it belongs to. A plain path, or a pattern with *.
            $table->string('route_pattern');

            // 'tour' walks the steps; 'hints' puts each one beside its
            // element as a marker to click
            $table->string('mode')->default('tour');

            $table->string('icon')->default('mdi-help-circle-outline');
            $table->string('color')->default('primary');

            // Who it is for: 'everyone', 'members' or 'guests'. Narrowed
            // further by roles when one is named.
            $table->string('audience')->default('everyone');
            $table->json('roles')->nullable();

            // Starts by itself the first time somebody lands on the page
            $table->boolean('auto_start')->default(false);
            // Offered by the help button in the toolbar
            $table->boolean('show_launcher')->default(true);

            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_enabled', 'route_pattern']);
        });

        Schema::create('help_tour_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_tour_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['help_tour_id', 'locale']);
        });

        Schema::create('help_tour_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_tour_id')->constrained()->cascadeOnDelete();

            // Null means a step about the page as a whole: it is shown in
            // the middle with nothing singled out
            $table->string('target')->nullable();

            // Where the card sits relative to the element: auto, top,
            // bottom, left or right
            $table->string('placement')->default('auto');

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['help_tour_id', 'sort_order']);
        });

        Schema::create('help_tour_step_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_tour_step_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('title');
            $table->text('content')->nullable();
            $table->timestamps();

            $table->unique(['help_tour_step_id', 'locale'], 'help_step_translations_step_locale_unique');
        });

        /**
         * Who has already been shown which tour. `completed_at` separates
         * someone who walked it to the end from someone who closed it on
         * the first step — both stop it starting by itself again, but only
         * one of them means the help was read.
         */
        Schema::create('help_tour_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_tour_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('seen_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['help_tour_id', 'user_id']);
        });

        $this->seedWelcomeTour();
    }

    private function seedWelcomeTour(): void
    {
        $now = now();

        $tourId = DB::table('help_tours')->insertGetId([
            'key'           => self::WELCOME['key'],
            'route_pattern' => self::WELCOME['route_pattern'],
            'mode'          => self::WELCOME['mode'],
            'icon'          => self::WELCOME['icon'],
            'color'         => self::WELCOME['color'],
            'audience'      => self::WELCOME['audience'],
            'roles'         => null,
            'auto_start'    => self::WELCOME['auto_start'],
            'show_launcher' => true,
            'is_enabled'    => true,
            'sort_order'    => 0,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        foreach (['en', 'de'] as $locale) {
            DB::table('help_tour_translations')->insert([
                'help_tour_id' => $tourId,
                'locale'       => $locale,
                'title'        => self::WELCOME[$locale][0],
                'description'  => self::WELCOME[$locale][1],
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        foreach (self::WELCOME['steps'] as $index => $step) {
            $stepId = DB::table('help_tour_steps')->insertGetId([
                'help_tour_id' => $tourId,
                'target'       => $step['target'],
                'placement'    => $step['placement'],
                'sort_order'   => $index,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);

            foreach (['en', 'de'] as $locale) {
                DB::table('help_tour_step_translations')->insert([
                    'help_tour_step_id' => $stepId,
                    'locale'            => $locale,
                    'title'             => $step[$locale][0],
                    'content'           => $step[$locale][1],
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('help_tour_views');
        Schema::dropIfExists('help_tour_step_translations');
        Schema::dropIfExists('help_tour_steps');
        Schema::dropIfExists('help_tour_translations');
        Schema::dropIfExists('help_tours');
    }
};
