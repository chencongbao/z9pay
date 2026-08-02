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
        Schema::table('user_balance_logs', function (Blueprint $table) {
            $table->tinyInteger('is_agent')->default(0)->comment('是否代理');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_balance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('user_balance_logs', 'is_agent')) {
                $table->dropColumn('is_agent');
            }
        });
    }
};
