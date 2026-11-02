<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How the site is put together, as rows rather than as markup.
 *
 * A section is a band across a page; the widgets inside it are what it
 * shows. The homepage and the footer are both built this way, told apart
 * by `location`, which is why there is no homepage table. `menus` and
 * `menu_items` are the navigation, from the header bar to the footer
 * links.
 *
 * The wording of all of it is translated, so each table is followed by
 * its own translation table. The rows themselves are seeded - see
 * HomepageSeeder, FooterSeeder and MenuSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            // home, footer, page …
            $table->string('location')->default('home');
            $table->string('anchor_id')->nullable();
            // 1-col, 2-col, 3-col, 4-col, 2-1-col (66/33), 1-2-col (33/66)
            $table->string('layout')->default('1-col');
            $table->boolean('enabled')->default(true);
            $table->integer('order')->default(0);
            // Background colour, padding and the like
            $table->json('config')->nullable();
            $table->timestamps();

            $table->index(['location', 'enabled', 'order']);
        });

        Schema::create('section_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('title')->nullable();
            $table->timestamps();

            $table->unique(['section_id', 'locale']);
        });

        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('location')->default('home');
            // No constraint: a widget can be parked without a section
            $table->foreignId('section_id')->nullable();
            // hero, stats, partners, feature_grid, feature_showcase, cta,
            // testimonials, pricing, faq, gallery, quicklinks, contact,
            // newsletter, social, text
            $table->string('type');
            $table->boolean('enabled')->default(true);
            $table->integer('order')->default(0);
            $table->integer('column')->default(1);
            // Whatever this type of widget needs to know
            $table->json('config')->nullable();
            // For navigation
            $table->string('anchor_id')->nullable();
            $table->timestamps();

            $table->index(['location', 'enabled', 'order']);
        });

        Schema::create('widget_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('widget_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('title')->nullable();
            $table->json('content')->nullable();
            $table->timestamps();

            $table->unique(['widget_id', 'locale']);
        });

        // The anchor links along the top of the homepage
        Schema::create('homepage_menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('anchor_target');
            $table->integer('order')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['enabled', 'order']);
        });

        Schema::create('homepage_menu_item_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homepage_menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('label');
            $table->timestamps();

            $table->unique(['homepage_menu_item_id', 'locale'], 'homepage_menu_item_trans_unique');
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('location')->nullable()->comment('Where this menu appears: header, footer, mobile, sidebar');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('label');
            $table->string('type')->default('custom')->comment('custom, page, route, external');
            $table->string('url')->nullable()->comment('For custom/external links');
            $table->string('route')->nullable()->comment('For internal routes');
            $table->string('icon')->nullable()->comment('Material Design Icon name');
            $table->string('target')->default('_self')->comment('_self, _blank');
            $table->string('permission')->nullable()->comment('Required permission to view');
            $table->string('role')->nullable()->comment('Required role to view');
            $table->boolean('auth_required')->default(false);
            $table->boolean('guest_only')->default(false);
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable()->comment('Extra data (CSS class, badge, etc.)');
            $table->timestamps();

            $table->index('menu_id');
            $table->index('parent_id');
            $table->index('order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('homepage_menu_item_translations');
        Schema::dropIfExists('homepage_menu_items');
        Schema::dropIfExists('widget_translations');
        Schema::dropIfExists('widgets');
        Schema::dropIfExists('section_translations');
        Schema::dropIfExists('sections');
    }
};
