<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a member wants to be told the moment something happens.
 *
 * A notification already arrives over the socket; until now the only
 * sign of it was the number on the bell quietly going up, which is easy
 * to miss and impossible to turn off. The toast says what happened and
 * goes away again, and this is the switch for it.
 *
 * On for everybody who already has an account: the toast is the useful
 * half of a realtime notification, so it is what someone would expect
 * to find, and one click in the settings drawer takes it away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notification_toasts')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_toasts');
        });
    }
};
