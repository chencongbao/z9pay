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
        Schema::table('user_deposit_details', function (Blueprint $table) {
            $table->decimal('balance_amount',10,2)->default(0)->comment('余额');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_deposit_details', function (Blueprint $table) {
            if (Schema::hasColumn('user_deposit_details', 'balance_amount')) {
                $table->dropColumn('balance_amount');
            }
        });
    }
};
