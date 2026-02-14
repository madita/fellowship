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
        Schema::create('section_translations', function (Blueprint $table) {
            $table->id();
            // sections.id is id() (unsigned big integer)
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('title')->nullable();
            $table->unique(['section_id', 'locale']);
            $table->timestamps();
        });

        Schema::create('widget_translations', function (Blueprint $table) {
            $table->id();
            // widgets.id is id() (unsigned big integer)
            $table->foreignId('widget_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('title')->nullable();
            $table->json('content')->nullable();
            $table->unique(['widget_id', 'locale']);
            $table->timestamps();
        });

        Schema::create('homepage_menu_item_translations', function (Blueprint $table) {
            $table->id();
            // homepage_menu_items.id is id() (unsigned big integer)
            $table->foreignId('homepage_menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('label');
            $table->unique(['homepage_menu_item_id', 'locale'], 'homepage_menu_item_trans_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('section_translations');
        Schema::dropIfExists('widget_translations');
        Schema::dropIfExists('homepage_menu_item_translations');
    }
};
