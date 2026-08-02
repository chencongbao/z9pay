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
        Schema::table('bank_codes', function (Blueprint $table) {
            $table->tinyInteger('currency_id')->default(1)->comment('币种');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bank_codes', function (Blueprint $table) {
            if (Schema::hasColumn('bank_codes', 'currency_id')) {
                $table->dropColumn('currency_id');
            }
        });
    }
};
