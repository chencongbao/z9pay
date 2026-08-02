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
        Schema::create('uagent_user_relations', function (Blueprint $table) {
            $table->unsignedInteger('parent_id')->default(0);
            $table->unsignedInteger('child_id')->default(0);
            $table->unsignedTinyInteger('level')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('uagent_user_relations');
    }
};
