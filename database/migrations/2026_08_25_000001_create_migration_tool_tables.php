<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // External databases the migration tool can read from.
        Schema::create('migration_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('driver')->default('mysql'); // mysql, mariadb, pgsql, sqlite, sqlsrv
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('database'); // file path for sqlite
            $table->string('username')->nullable();
            $table->text('password')->nullable(); // encrypted cast
            $table->string('charset')->nullable();
            $table->timestamps();
        });

        // Column mappings from a source table onto one of our feature targets.
        Schema::create('migration_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_source_id')->constrained('migration_sources')->cascadeOnDelete();
            $table->string('name');
            $table->string('target');       // key from MigrationTargets registry
            $table->string('source_table');
            $table->json('field_map');      // targetField => {source, transform, format, default}
            $table->json('options')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_mappings');
        Schema::dropIfExists('migration_sources');
    }
};
