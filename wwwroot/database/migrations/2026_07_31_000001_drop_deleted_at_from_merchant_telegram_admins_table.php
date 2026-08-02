<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('merchant_telegram_admins') || !Schema::hasColumn('merchant_telegram_admins', 'deleted_at')) {
            return;
        }

        // 切换为物理删除前，先清理历史软删除记录，避免移除 deleted_at 后旧授权重新生效。
        DB::table('merchant_telegram_admins')->whereNotNull('deleted_at')->delete();

        Schema::table('merchant_telegram_admins', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('merchant_telegram_admins') || Schema::hasColumn('merchant_telegram_admins', 'deleted_at')) {
            return;
        }

        Schema::table('merchant_telegram_admins', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
