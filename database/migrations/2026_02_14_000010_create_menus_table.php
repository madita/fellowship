<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
