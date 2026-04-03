<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('late_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_id')->constrained('credits')->cascadeOnDelete();
            $table->integer('dias_mora')->default(0);
            $table->decimal('monto_mora', 12, 2)->default(0);
            $table->timestamps();

            $table->index('credit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('late_fees');
    }
};
