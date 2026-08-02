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
        Schema::create('merchant_telegram_admins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mid')->default(0)->comment('商户ID');
            $table->bigInteger('telegram_group_id')->default(0)->comment('商户群ID');
            $table->bigInteger('telegram_user_id')->default(0)->comment('Telegram用户ID');
            $table->string('telegram_username', 100)->default('')->comment('Telegram用户名');
            $table->string('telegram_name', 100)->default('')->comment('Telegram昵称');
            $table->unsignedBigInteger('reviewed_by')->default(0)->comment('审核管理员ID');
            $table->bigInteger('reviewed_telegram_user_id')->default(0)->comment('确认人Telegram用户ID');
            $table->string('reviewed_telegram_name', 100)->default('')->comment('确认人Telegram昵称');
            $table->timestamps();

            $table->index(['mid', 'telegram_user_id'], 'idx_mid_telegram_user');
            $table->index('telegram_group_id', 'idx_telegram_group');
            $table->index('telegram_user_id', 'idx_telegram_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_telegram_admins');
    }
};
