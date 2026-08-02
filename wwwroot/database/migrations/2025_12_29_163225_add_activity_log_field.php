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
        Schema::table('activity_log', function (Blueprint $table) {
            $table->string('ip', 45)->nullable()->index()->comment('请求IP');
            $table->string('method', 10)->nullable()->index()->comment('HTTP Method');
            $table->string('path', 255)->nullable()->index()->comment('请求路径');
            $table->text('user_agent')->nullable()->comment('User-Agent');
            $table->json('request_input')->nullable()->comment('请求参数(脱敏后)');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropColumn(['ip','method','path','user_agent','request_input']);
        });
    }
};
