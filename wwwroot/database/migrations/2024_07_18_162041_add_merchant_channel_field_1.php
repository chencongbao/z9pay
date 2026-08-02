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
        Schema::table('merchant_channels', function (Blueprint $table) {
            $table->decimal('fee',10,2)->default(0)->comment('手续费');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_channels', function (Blueprint $table) {
            if (Schema::hasColumn('channels', 'fee')) {
                $table->dropColumn('fee');
            }
        });
    }
};
