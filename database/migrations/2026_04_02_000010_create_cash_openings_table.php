<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_openings', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->decimal('saldo_inicial', 12, 2)->default(0);
            $table->decimal('saldo_final', 12, 2)->default(0);
            $table->enum('estado', ['abierto', 'cerrado'])->default('abierto');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('headquarter_id')->nullable()->constrained('headquarters')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_openings');
    }
};
