<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fecha/hora en que se registró (o se editó por última vez) el compromiso de pago. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_notifications', function (Blueprint $table) {
            $table->timestamp('compromiso_registrado_at')->nullable()->after('compromiso_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('client_notifications', function (Blueprint $table) {
            $table->dropColumn('compromiso_registrado_at');
        });
    }
};
