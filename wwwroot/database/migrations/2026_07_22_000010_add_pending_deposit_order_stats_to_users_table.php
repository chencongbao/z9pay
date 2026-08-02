<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'pending_deposit_order_amount')) {
                $table->decimal('pending_deposit_order_amount', 20, 4)->default(0)->comment('代收待付款订单总金额')->after('deposit_amount');
            }

            if (!Schema::hasColumn('users', 'pending_deposit_order_count')) {
                $table->unsignedInteger('pending_deposit_order_count')->default(0)->comment('代收待付款订单数')->after('pending_deposit_order_amount');
            }
        });

        // 上线时回填历史待付款数据，避免旧缓存存在时 users 新字段短时间为 0。
        DB::statement("
            UPDATE users u
            LEFT JOIN (
                SELECT user_id, COUNT(*) AS total_count, COALESCE(SUM(amount), 0) AS total_amount
                FROM deposit_orders
                WHERE status IN (1, 3, 7)
                GROUP BY user_id
            ) d ON d.user_id = u.id
            SET u.pending_deposit_order_amount = COALESCE(d.total_amount, 0),
                u.pending_deposit_order_count = COALESCE(d.total_count, 0)
            WHERE u.is_agent = 0
        ");
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'pending_deposit_order_count')) {
                $table->dropColumn('pending_deposit_order_count');
            }

            if (Schema::hasColumn('users', 'pending_deposit_order_amount')) {
                $table->dropColumn('pending_deposit_order_amount');
            }
        });
    }
};
