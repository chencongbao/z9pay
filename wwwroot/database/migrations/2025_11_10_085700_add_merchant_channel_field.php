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
        Schema::table('merchant_payments', function (Blueprint $table) {
            $table->text('transfer_rates')->nullable()->comment('代付费率');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_payments', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_payments', 'transfer_rates')) {
                $table->dropColumn('transfer_rates');
            }
        });
    }
};
