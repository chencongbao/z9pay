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
        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('deposit_notice')->default(0)->comment('代收通知');
            $table->tinyInteger('transfer_notice')->default(0)->comment('代付通知');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_infos', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_infos', 'deposit_notice')) {
                $table->dropColumn('deposit_notice');
            }
            if (Schema::hasColumn('merchant_infos', 'transfer_notice')) {
                $table->dropColumn('transfer_notice');
            }
        });
    }
};
