<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('agent_users', function (Blueprint $table) {
            $table->string("google_two_fa_secret",32)->nullable();
            $table->tinyInteger("google_two_fa_bind")->default(0);
            $table->text("login_white_ip")->nullable();
            $table->timestamp("last_login_time")->nullable();
            $table->string("last_login_ip",100)->nullable();
            $table->string("session_id",100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('agent_users', function (Blueprint $table) {
            if (Schema::hasColumn('agent_users', 'google_two_fa_secret')) {
                $table->dropColumn('google_two_fa_secret');
            }
            if (Schema::hasColumn('agent_users', 'google_two_fa_bind')) {
                $table->dropColumn('google_two_fa_bind');
            }
            if (Schema::hasColumn('agent_users', 'login_white_ip')) {
                $table->dropColumn('login_white_ip');
            }
            if (Schema::hasColumn('agent_users', 'last_login_time')) {
                $table->dropColumn('last_login_time');
            }
            if (Schema::hasColumn('agent_users', 'last_login_ip')) {
                $table->dropColumn('last_login_ip');
            }
            if (Schema::hasColumn('agent_users', 'session_id')) {
                $table->dropColumn('session_id');
            }
        });
    }
};
