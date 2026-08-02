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
        Schema::table('channel_rates', function (Blueprint $table) {
            $table->tinyInteger('type')->default(0)->comment('类型');
            $table->decimal("fixed_rate",10,2)->default(0)->comment("固定费率");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channel_rates', function (Blueprint $table) {
            if (Schema::hasColumn('channel_rates', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('channel_rates', 'fixed_rate')) {
                $table->dropColumn('fixed_rate');
            }
        });
    }
};
