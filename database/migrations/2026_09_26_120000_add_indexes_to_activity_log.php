<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 25/09: el visor filtra por fecha y por evento; la tabla solo tenía índice por log_name y subject. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index(['log_name', 'created_at'], 'activity_log_log_name_created_at_index');
            $table->index('event', 'activity_log_event_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('activity_log_log_name_created_at_index');
            $table->dropIndex('activity_log_event_index');
        });
    }
};
