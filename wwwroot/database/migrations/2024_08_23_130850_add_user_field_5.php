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
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('deposit_balance_amount',20,6)->default(0)->comment('代收余额');
            $table->decimal('transfer_balance_amount',20,6)->default(0)->comment('代付余额');
            $table->decimal('commission_balance_amount',20,6)->default(0)->comment('佣金余额');
            $table->decimal('deposit_amount',20,6)->default(0)->comment('押金');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'deposit_balance_amount')) {
                $table->dropColumn('deposit_balance_amount');
            }
            if (Schema::hasColumn('users', 'transfer_balance_amount')) {
                $table->dropColumn('transfer_balance_amount');
            }
            if (Schema::hasColumn('users', 'commission_balance_amount')) {
                $table->dropColumn('commission_balance_amount');
            }
            if (Schema::hasColumn('users', 'deposit_amount')) {
                $table->dropColumn('deposit_amount');
            }
        });
    }
};
