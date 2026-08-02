<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_callback_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('type')->default(0)->comment('订单类型：1代收 2代付');
            $table->unsignedBigInteger('order_id')->default(0)->comment('订单ID');
            $table->string('notify_url', 500)->default('')->comment('回调地址');
            $table->json('request_data')->nullable()->comment('请求参数');
            $table->longText('response_body')->nullable()->comment('响应内容');
            $table->unsignedSmallInteger('response_status')->nullable()->comment('响应状态码');
            $table->unsignedInteger('duration_ms')->default(0)->comment('耗时毫秒');
            $table->boolean('is_success')->default(false)->comment('是否成功');
            $table->text('error_message')->nullable()->comment('异常信息');
            $table->timestamps();

            $table->index(['type', 'order_id'], 'idx_type_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_callback_logs');
    }
};
