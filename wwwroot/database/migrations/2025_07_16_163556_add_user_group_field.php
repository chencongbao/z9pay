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
        Schema::table('user_groups', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)->comment('状态');
            $table->integer('priority')->default(0)->comment('优先级，从小到大');
            $table->string("specialized_merchant_user_ids",255)->nullable()->comment("转接商户");
            $table->string("extra_user_ids",255)->nullable()->comment("金主补充");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_groups', function (Blueprint $table) {
            if (Schema::hasColumn('user_groups', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('user_groups', 'priority')) {
                $table->dropColumn('priority');
            }
            if (Schema::hasColumn('user_groups', 'specialized_merchant_user_ids')) {
                $table->dropColumn('specialized_merchant_user_ids');
            }
            if (Schema::hasColumn('user_groups', 'extra_user_ids')) {
                $table->dropColumn('extra_user_ids');
            }
        });
    }
};
