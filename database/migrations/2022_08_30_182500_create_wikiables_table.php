<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWikiablesTable extends Migration
{
    public function up()
    {

        Schema::create('wikiables', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->string('slug');
            $table->string('wikiable_type');
            $table->unsignedInteger('wikiable_id');
            $table->nullableTimestamps();

            $table->unique(
                ['wikiable_id', 'wikiable_type'], 'wikiables_unique'
            );
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wikiables');
    }
}
