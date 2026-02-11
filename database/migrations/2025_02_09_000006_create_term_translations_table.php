<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term_translations', function (Blueprint $table) {
            $table->id();
            // terms.id is increments (unsigned integer)
            $table->unsignedInteger('term_id');
            $table->string('locale')->index();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->text('lead')->nullable();
            $table->unique(['term_id', 'locale']);
            $table->timestamps();

            $table->foreign('term_id')->references('id')->on('terms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_translations');
    }
};
