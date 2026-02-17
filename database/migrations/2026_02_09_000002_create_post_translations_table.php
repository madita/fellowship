<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('post_translations', function (Blueprint $table) {
            $table->id();
            // posts.id is integer (signed, auto-increment)
            $table->integer('post_id');
            $table->string('locale')->index();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->unique(['post_id', 'locale']);
            $table->timestamps();

            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_translations');
    }
};
