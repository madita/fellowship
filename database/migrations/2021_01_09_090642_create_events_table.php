<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->unsigned();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->time('startTime')->nullable();
            $table->time('endTime')->nullable();
            $table->date('startDate')->nullable();
            $table->date('endDate')->nullable();
            $table->tinyInteger('allDay')->nullable();
            $table->integer('type_id')->unsigned();
//            $table->string('type')->nullable(); //gathering, meetup, rpg...story
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_type', function (Blueprint $table) {
            $table->id();
            $table->integer('event_id')->unsigned();
            $table->string('name', 255)->nullable();
            $table->string('color', 45)->nullable();
            $table->text('options')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_details', function (Blueprint $table) {
            $table->id();
            $table->integer('event_id')->unsigned();
            $table->string('lat', 45)->nullable();
            $table->string('lng', 45)->nullable();
            $table->text('city')->nullable();
            $table->text('country')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_guests', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->unsigned();
            $table->integer('event_id')->unsigned();
            $table->string('type')->nullable(); //yes no maybe? // master, player, guest
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('event_guests');
        Schema::dropIfExists('event_details');
        Schema::dropIfExists('event_type');
        Schema::dropIfExists('events');
    }
}
