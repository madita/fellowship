<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_translations', function (Blueprint $table) {
            $table->id();
            // taxonomies.id is increments (unsigned integer)
            $table->unsignedInteger('taxonomy_id');
            $table->string('locale')->index();
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            $table->text('lead')->nullable();
            $table->text('meta_desc')->nullable();
            $table->unique(['taxonomy_id', 'locale']);
            $table->timestamps();

            $table->foreign('taxonomy_id')->references('id')->on('taxonomies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_translations');
    }
};
