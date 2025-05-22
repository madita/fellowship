<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class CreateRevisionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('revisions', function ($table) {
//            $table->increments('id');
//            $table->string('revisionable_type');
//            $table->integer('revisionable_id');
//            $table->integer('user_id')->nullable();
//            $table->string('key');
//            $table->text('old_value')->nullable();
//            $table->text('new_value')->nullable();
//            $table->timestamps();
//
//            $table->index(array('revisionable_id', 'revisionable_type'));

            $table->increments('id');
            $table->string('action');
            $table->string('revisionable_type');
            $table->integer('revisionable_id');
            $table->longtext('old_value')->nullable();
            $table->longtext('new_value')->nullable();
            $table->integer('user_id')->nullable();
            $table->string('ip')->nullable();
            $table->string('ip_forwarded')->nullable();
            $table->timestamps();

            $table->index('action');
            $table->index(['revisionable_id', 'revisionable_type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('revisions');
    }
}
