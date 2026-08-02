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
        Schema::table('channel_accounts', function (Blueprint $table) {
            $table->tinyInteger('debug_logs')->default(0)->comment('开启调试');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channel_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('channel_accounts', 'debug_logs')) {
                $table->dropColumn('debug_logs');
            }
        });
    }
};
