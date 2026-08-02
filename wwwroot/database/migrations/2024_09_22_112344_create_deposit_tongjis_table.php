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
        Schema::create('deposit_tongjis', function (Blueprint $table) {
            $table->id();
            $table->date('date_add')->nullable()->index()->comment("日期");
            $table->integer("total_count")->default(0);
            $table->integer("status1_count")->default(0);
            $table->integer("status2_count")->default(0);
            $table->integer("status3_count")->default(0);
            $table->integer("status4_count")->default(0);
            $table->integer("status5_count")->default(0);
            $table->integer("status6_count")->default(0);
            $table->decimal("total_amount",20,2)->default(0)->comment("总跑量");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('deposit_tongjis');
    }
};
