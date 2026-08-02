<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLogTypeToActivityLogTable extends Migration
{
    public function up()
    {
        Schema::connection(config('activitylog.database_connection'))
            ->table(config('activitylog.table_name'), function (Blueprint $table) {
                $table->string('log_type', 20)->default('operation');
                $table->index(['log_name', 'log_type', 'created_at'], 'idx_activity_log_name_type_time');
            });
    }

    public function down()
    {
        Schema::connection(config('activitylog.database_connection'))
            ->table(config('activitylog.table_name'), function (Blueprint $table) {
                $table->dropIndex('idx_activity_log_name_type_time');
                $table->dropColumn('log_type');
            });
    }
}
