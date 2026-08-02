<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_day_balance_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('uid')->index()->default(0)->comment('金主ID');
            $table->date('date_add')->index()->nullable()->comment('日切日期');
            $table->decimal('balance_amount', 30, 8)->default(0)->comment('金主余额');
            $table->decimal('deposit_balance_amount', 30, 8)->default(0)->comment('代收账户余额');
            $table->decimal('transfer_balance_amount', 30, 8)->default(0)->comment('代付账户余额');
            $table->decimal('commission_balance_amount', 30, 8)->default(0)->comment('佣金账户余额');
            $table->decimal('deposit_amount', 30, 8)->default(0)->comment('保证金总额');
            $table->decimal('daifukuan_amount', 30, 8)->default(0)->comment('代收待付款金额');
            $table->decimal('zeros_balance', 30, 8)->default(0)->comment('0点剩余押金');
            $table->timestamps();
            $table->unique(['uid', 'date_add'], 'user_day_balance_logs_uid_date_add_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_day_balance_logs');
    }
};
