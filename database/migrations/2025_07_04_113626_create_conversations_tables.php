<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('creator_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('last_message_at');
            $table->timestamps();
        });

        /*Schema::create('conversations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('parent_id')->unsigned()->nullable();
            $table->integer('user_id')->unsigned();
            $table->text('body');
            $table->timestamp('last_reply')->nullable();
            $table->timestamps();

            //$table->foreignId('conversation_id')->constrained()->onDelete('cascade');
           // $table->foreignId('user_id')->constrained()->onDelete('cascade');
        });*/

        Schema::create('conversation_user', function (Blueprint $table) {
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->timestamps();
            $table->timestamp('read_at')->nullable();

            $table->primary(['conversation_id', 'user_id']);
        });

        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('body');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversation_user');
        Schema::dropIfExists('conversations');
    }
};
