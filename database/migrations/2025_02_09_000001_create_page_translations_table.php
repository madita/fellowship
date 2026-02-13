<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('page_translations', function (Blueprint $table) {
            $table->id();
            // pages.id is integer (signed, auto-increment)
            $table->integer('page_id');
            $table->string('locale')->index();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->unique(['page_id', 'locale']);
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_translations');
    }
};
