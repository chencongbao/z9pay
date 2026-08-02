<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::table('channel_rates', function (Blueprint $table) {
            if (!Schema::hasColumn('channel_rates', 'rate_ranges')) {
                $table->json('rate_ranges')->nullable()->comment('区间成本费率配置')->after('fixed_rate');
            }
        });
    }

    public function down()
    {
        Schema::table('channel_rates', function (Blueprint $table) {
            if (Schema::hasColumn('channel_rates', 'rate_ranges')) {
                $table->dropColumn('rate_ranges');
            }
        });
    }
};
