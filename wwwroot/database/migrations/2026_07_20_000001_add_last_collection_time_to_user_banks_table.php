<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_banks', function (Blueprint $table) {
            $table->timestamp('last_collection_time')->nullable()->after('collection_status')->comment('最近一笔成功入款时间');
        });
    }

    public function down(): void
    {
        Schema::table('user_banks', function (Blueprint $table) {
            if (Schema::hasColumn('user_banks', 'last_collection_time')) {
                $table->dropColumn('last_collection_time');
            }
        });
    }
};
