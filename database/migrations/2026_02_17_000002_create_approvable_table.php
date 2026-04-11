<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvables', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->timestamp('approved_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['approvable_type', 'approvable_id']);
        });

        // Backfill: auto-approve all existing wiki pages so they remain visible
        $wikis = DB::table('wikiables')->select('id')->get();
        $now = now();
        foreach ($wikis as $wiki) {
            DB::table('approvables')->insert([
                'approvable_type' => 'App\\Models\\Wiki',
                'approvable_id' => $wiki->id,
                'approved_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approvables');
    }
};
