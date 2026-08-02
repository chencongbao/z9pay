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
        Schema::table('merchant_balance_logs', function (Blueprint $table) {
            $table->integer("admin_id")->index()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_balance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_balance_logs', 'admin_id')) {
                $table->dropColumn('admin_id');
            }
        });
    }
};
