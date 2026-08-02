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
            $table->decimal('min_limit_amount',20,2)->change();
            $table->decimal('max_limit_amount',20,2)->change();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('collection_limit_min',20,2)->change();
            $table->decimal('collection_limit_max',20,2)->change();
            $table->decimal('pay_limit_min',20,2)->change();
            $table->decimal('pay_limit_max',20,2)->change();
        });
        Schema::table('channel_accounts', function (Blueprint $table) {
            $table->decimal('pay_min_amount',20,2)->change();
            $table->decimal('pay_max_amount',20,2)->change();
            $table->decimal('pay_total_amount',20,2)->change();
            $table->decimal('collection_min_amount',20,2)->change();
            $table->decimal('collection_min_amount',20,2)->change();
            $table->decimal('collection_max_amount',20,2)->change();
            $table->decimal('collection_total_amount',20,2)->change();
            $table->decimal('balance_amount',20,2)->change();
        });
        Schema::table('merchant_channels', function (Blueprint $table) {
            $table->decimal('pay_min_amount',20,2)->change();
            $table->decimal('pay_max_amount',20,2)->change();
            $table->decimal('collection_min_amount',20,2)->change();
            $table->decimal('collection_max_amount',20,2)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
