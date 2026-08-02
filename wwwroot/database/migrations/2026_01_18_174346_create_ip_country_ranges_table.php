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
        Schema::create('ip_country_ranges', function (Blueprint $table) {
            $table->id();
            $table->integer('currency_id')->index()->default(0);

            // 可选：保留原始 begin/end（如果源里是点分IP）
            $table->string('begin_ip', 45)->nullable();
            $table->string('end_ip', 45)->nullable();

            // 统一用 long 做随机
            $table->unsignedBigInteger('begin_long')->index();
            $table->unsignedBigInteger('end_long')->index();

            // 本段数量
            $table->unsignedBigInteger('total_count');

            // 累计权重（用于按 IP 数量等概率随机）
            $table->unsignedBigInteger('cdf_end')->index();

            $table->timestamps();

            $table->unique(['currency_id', 'begin_long', 'end_long'], 'uniq_country_range');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ip_country_ranges');
    }
};
