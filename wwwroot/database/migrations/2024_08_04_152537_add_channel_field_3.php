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
        Schema::table('channels', function (Blueprint $table) {
            $table->tinyInteger('is_json_return')->default(0)->comment('1=支持，0=不支持');
            $table->tinyInteger('is_real_name')->default(0)->comment('1=需要实名，0=不需要实名');
            $table->text('coder')->nullable()->comment('编码设置');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channels', function (Blueprint $table) {
            if (Schema::hasColumn('channels', 'is_json_return')) {
                $table->dropColumn('is_json_return');
            }
            if (Schema::hasColumn('channels', 'is_real_name')) {
                $table->dropColumn('is_real_name');
            }
            if (Schema::hasColumn('channels', 'coder')) {
                $table->dropColumn('coder');
            }
        });
    }
};
