<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, boolean, integer, file, etc.
            $table->timestamps();
        });

        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('location')->default('home'); // home, footer, page, etc.
            $table->foreignId('section_id')->nullable();
            $table->string('type'); // hero, stats, partners, feature_grid, feature_showcase, cta, testimonials, pricing, faq, gallery, quicklinks, contact, newsletter, social, text
            // $table->string('title')->nullable(); // Moved to widget_translations
            $table->boolean('enabled')->default(true);
            $table->integer('order')->default(0);
            $table->integer('column')->default(1);
            // $table->json('content')->nullable(); // Moved to widget_translations
            $table->json('config')->nullable(); // Widget-specific configuration
            $table->string('anchor_id')->nullable(); // For navigation
            $table->timestamps();

            $table->index(['location', 'enabled', 'order']);
        });

        Schema::create('homepage_menu_items', function (Blueprint $table) {
            $table->id();
            // $table->string('label'); // Moved to homepage_menu_item_translations
            $table->string('anchor_target');
            $table->integer('order')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['enabled', 'order']);
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('location')->default('home'); // home, footer, page, etc.
            // $table->string('title')->nullable(); // Moved to section_translations
            $table->string('anchor_id')->nullable();
            $table->string('layout')->default('1-col'); // 1-col, 2-col, 3-col, 4-col, 2-1-col (66/33), 1-2-col (33/66)
            $table->boolean('enabled')->default(true);
            $table->integer('order')->default(0);
            $table->json('config')->nullable(); // Background color, padding, etc.
            $table->timestamps();

            $table->index(['location', 'enabled', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('widget_translations');
        Schema::dropIfExists('homepage_menu_item_translations');
        Schema::dropIfExists('section_translations');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('homepage_menu_items');
        Schema::dropIfExists('widgets');
        Schema::dropIfExists('settings');
    }
};
