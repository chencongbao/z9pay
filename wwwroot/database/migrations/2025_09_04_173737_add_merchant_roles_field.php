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
        Schema::table('merchant_roles', function (Blueprint $table) {
            $table->integer('mid')->index()->default(0)->comment('mid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_roles', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_roles', 'mid')) {
                $table->dropColumn('mid');
            }
        });
    }
};
