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
        Schema::table('tickets', function (Blueprint $table) {
            // BGA-style extended statuses
            $table->string('bga_status')->default('reported')->after('status')
                ->comment('BGA workflow: reported, confirmed, investigating, planned, in_progress, completed, wontfix, duplicate');
            
            // Duplicate linking
            $table->foreignId('duplicate_of_ticket_id')->nullable()->after('bga_status')
                ->constrained('tickets')->nullOnDelete();
            
            // Public visibility flag
            $table->boolean('is_public')->default(true)->after('duplicate_of_ticket_id');
            
            // Vote count (denormalized for performance)
            $table->integer('votes_count')->default(0)->after('is_public');
            
            // Watchers count (denormalized for performance)
            $table->integer('watchers_count')->default(0)->after('votes_count');
            
            // Implementation version (when it was fixed)
            $table->string('fixed_in_version')->nullable()->after('watchers_count');
            
            $table->index('bga_status');
            $table->index('is_public');
            $table->index('votes_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['duplicate_of_ticket_id']);
            $table->dropColumn([
                'bga_status',
                'duplicate_of_ticket_id',
                'is_public',
                'votes_count',
                'watchers_count',
                'fixed_in_version',
            ]);
        });
    }
};
