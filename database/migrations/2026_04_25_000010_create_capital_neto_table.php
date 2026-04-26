<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('capital_neto')) {
            Schema::create('capital_neto', function (Blueprint $table) {
                $table->id();
                $table->date('fecha')->unique();
                $table->decimal('importe', 14, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_neto');
    }
};
