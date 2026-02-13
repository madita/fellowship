<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wiki_translations', function (Blueprint $table) {
            $table->id();
            // wikiables.id is increments (unsigned integer)
            $table->unsignedInteger('wiki_id');
            $table->string('locale')->index();
            $table->string('title');
            $table->unique(['wiki_id', 'locale']);
            $table->timestamps();

            $table->foreign('wiki_id')->references('id')->on('wikiables')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wiki_translations');
    }
};
