<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('database_cleanup_states', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 100)->unique()->comment('清理表名');
            $table->unsignedBigInteger('last_scan_id')->default(0)->comment('上次扫描到的主键 ID');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('database_cleanup_states');
    }
};
