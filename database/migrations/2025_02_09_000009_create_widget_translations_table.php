<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_translations');
    }
};
