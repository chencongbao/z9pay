<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->bigInteger('telegram_user_id')->default(0)->comment('Telegram用户ID');
            $table->tinyInteger('telegram_role')->default(0)->comment('飞机权限:0无 1命令管理员 2超级管理员');

            $table->index(['telegram_user_id', 'telegram_role'], 'idx_telegram_user_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropIndex('idx_telegram_user_role');

            if (Schema::hasColumn('admin_users', 'telegram_user_id')) {
                $table->dropColumn('telegram_user_id');
            }
            if (Schema::hasColumn('admin_users', 'telegram_role')) {
                $table->dropColumn('telegram_role');
            }
        });
    }
};
