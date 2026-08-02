<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_merchants', function (Blueprint $table) {
            $table->integer('deposit_created_success_number')->default(0)->comment('代收提单成功数');
            $table->decimal('deposit_created_success_amount', 30, 2)->default(0)->comment('代收提单成功金额');
            $table->integer('deposit_freeze_number')->default(0)->comment('代收冻结笔数');
            $table->decimal('deposit_freeze_amount', 30, 2)->default(0)->comment('代收冻结金额');
            $table->integer('deposit_unfreeze_number')->default(0)->comment('代收解冻笔数');
            $table->decimal('deposit_unfreeze_amount', 30, 2)->default(0)->comment('代收解冻金额');

            $table->integer('transfer_created_success_number')->default(0)->comment('代付提单成功数');
            $table->decimal('transfer_created_success_amount', 30, 2)->default(0)->comment('代付提单成功金额');
            $table->integer('transfer_deduct_number')->default(0)->comment('代付扣款笔数');
            $table->decimal('transfer_deduct_amount', 30, 2)->default(0)->comment('代付扣款金额');
            $table->integer('transfer_corre_number')->default(0)->comment('代付冲正笔数');
            $table->decimal('transfer_corre_amount', 30, 2)->default(0)->comment('代付冲正金额');

            $table->integer('settlement_created_success_number')->default(0)->comment('结算提单成功数');
            $table->decimal('settlement_created_success_amount', 30, 2)->default(0)->comment('结算提单成功金额');
            $table->integer('settlement_deduct_number')->default(0)->comment('结算扣款笔数');
            $table->decimal('settlement_deduct_amount', 30, 2)->default(0)->comment('结算扣款金额');
            $table->integer('settlement_corre_number')->default(0)->comment('结算冲正笔数');
            $table->decimal('settlement_corre_amount', 30, 2)->default(0)->comment('结算冲正金额');
        });
    }

    public function down(): void
    {
        Schema::table('report_merchants', function (Blueprint $table) {
            $table->dropColumn([
                'deposit_created_success_number',
                'deposit_created_success_amount',
                'deposit_freeze_number',
                'deposit_freeze_amount',
                'deposit_unfreeze_number',
                'deposit_unfreeze_amount',
                'transfer_created_success_number',
                'transfer_created_success_amount',
                'transfer_deduct_number',
                'transfer_deduct_amount',
                'transfer_corre_number',
                'transfer_corre_amount',
                'settlement_created_success_number',
                'settlement_created_success_amount',
                'settlement_deduct_number',
                'settlement_deduct_amount',
                'settlement_corre_number',
                'settlement_corre_amount',
            ]);
        });
    }
};
