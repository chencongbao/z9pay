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
            $table->decimal('agent3_rate')->default('0');
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
            if (Schema::hasColumn('merchant_payments', 'agent3_rate')) {
                $table->dropColumn('agent3_rate');
            }
        });
    }
};
