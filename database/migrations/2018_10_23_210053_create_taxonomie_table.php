<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTaxonomieTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('terms', function(Blueprint $table)
        {
            $table->increments('id');

            $table->string('name')->nullable()->unique();
            $table->string('slug')->nullable()->unique();
            $table->smallInteger('sort')->unsigned()->default(0);
            $table->string('desc')->nullable();
            $table->boolean('archived')->default(false);
            $table->string('color', 7)->default('#000000');

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('taxonomies', function(Blueprint $table)
        {
            $table->increments('id');

            $table->integer('term_id')
                ->nullable()
                ->unsigned()
                ->references('id')
                ->on('terms')
                ->onDelete('cascade');

            $table->string('taxonomy')->default('default');
            $table->string('desc')->nullable();
            $table->boolean('archived')->default(false);
            $table->string('color', 7)->default('#000000');

            $table->integer('parent_id')->unsigned()->default(0);
            $table->smallInteger('weight')->unsigned()->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['term_id', 'taxonomy']);
        });

        Schema::create('taxables', function(Blueprint $table)
        {
            $table->integer('taxonomy_id')
                ->nullable()
                ->unsigned()
                ->references('id')
                ->on('taxonomies');

            $table->nullableMorphs('taxable');
            $table->bigInteger('term_order')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('taxables');
        Schema::dropIfExists('taxonomies');
        Schema::dropIfExists('terms');
    }
}
