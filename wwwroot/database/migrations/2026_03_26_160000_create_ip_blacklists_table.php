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
        Schema::create('ip_blacklists', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->default('')->comment('IP地址');
            $table->string('type', 20)->default('all')->comment('类型:all全部 system系统端 merchant商户端 agent代理端 user金主端');
            $table->tinyInteger('status')->default(1)->comment('状态:1启用 0禁用');
            $table->unsignedInteger('hit_count')->default(0)->comment('命中次数');
            $table->string('reason', 255)->default('')->comment('封禁原因');
            $table->text('remark')->nullable()->comment('备注');
            $table->text('hit_usernames')->nullable()->comment('命中的用户名列表(JSON)');
            $table->timestamp('locked_at')->nullable()->comment('封禁时间');
            $table->timestamp('expires_at')->nullable()->comment('解封时间,为空表示永久');
            $table->timestamps();

            $table->unique(['ip', 'type'], 'uk_ip_type');
            $table->index(['status', 'type'], 'idx_status_type');
            $table->index('expires_at', 'idx_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_blacklists');
    }
};
