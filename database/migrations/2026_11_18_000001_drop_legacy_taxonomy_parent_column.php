<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop taxonomies.parent.
 *
 * The table carried two notions of a parent: parent_id, which is the one
 * with the foreign key and the one everything writes, and a legacy
 * `parent` integer that was never filled in beyond its 0 default.
 *
 * It was not merely unused, it was harmful. An Eloquent attribute wins
 * over a relation of the same name, so `$taxonomy->parent` returned the
 * number 0 rather than the parent row, and eager loading did not change
 * that. Taxonomy::getParentBreadcrumbs() and getParentSlugs() both walk
 * upwards through `$this->parent`, so both stopped at the row they
 * started on and quietly produced one-item breadcrumb trails; the forum
 * controllers each carry a paragraph of comment explaining why they have
 * to ask for the relation the long way round.
 *
 * With the column gone, `->parent` means the parent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('taxonomies', 'parent')) {
            Schema::table('taxonomies', function (Blueprint $table) {
                $table->dropColumn('parent');
            });
        }
    }

    public function down(): void
    {
        if ( ! Schema::hasColumn('taxonomies', 'parent')) {
            Schema::table('taxonomies', function (Blueprint $table) {
                $table->unsignedInteger('parent')->default(0)->after('term_id');
            });
        }
    }
};
