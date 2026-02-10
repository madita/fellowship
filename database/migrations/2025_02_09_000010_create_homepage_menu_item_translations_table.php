<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('homepage_menu_item_translations');
    }
};
