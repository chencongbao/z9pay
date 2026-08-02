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
        Schema::table('user_bank_action_logs', function (Blueprint $table) {
            $table->string("ip",50)->nullable()->comment("ip");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_bank_action_logs', function (Blueprint $table) {
            if (Schema::hasColumn('user_bank_action_logs', 'ip')) {
                $table->dropColumn('ip');
            }
        });
    }
};
