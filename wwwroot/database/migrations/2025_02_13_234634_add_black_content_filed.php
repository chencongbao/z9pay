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
        Schema::table('black_contents', function (Blueprint $table) {
            $table->integer('mid')->default(0)->index()->comment('商户ID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('black_contents', function (Blueprint $table) {
            if (Schema::hasColumn('black_contents', 'mid')) {
                $table->dropColumn('mid');
            }
        });
    }
};
