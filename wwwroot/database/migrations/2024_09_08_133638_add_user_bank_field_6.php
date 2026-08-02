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
        Schema::table('user_banks', function (Blueprint $table) {
            $table->string('payment_qrcode_url',255)->nullable()->comment('1开启，0=不开启');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_banks', function (Blueprint $table) {
            if (Schema::hasColumn('user_banks', 'payment_qrcode_url')) {
                $table->dropColumn('payment_qrcode_url');
            }
        });
    }
};
