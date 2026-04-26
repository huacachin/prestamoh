<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache_advisor_monthly', function (Blueprint $table) {
            $table->id();
            $table->string('mes', 20);
            $table->decimal('xcn', 14, 2)->default(0);
            $table->decimal('xrc', 14, 2)->default(0);
            $table->decimal('canc', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('capital', 14, 2)->default(0);
            $table->decimal('impacobra', 14, 2)->default(0);
            $table->decimal('cobcnt', 14, 2)->default(0);
            $table->decimal('cobimp', 14, 2)->default(0);
            $table->decimal('nocobcnt', 14, 2)->default(0);
            $table->decimal('nocobimp', 14, 2)->default(0);
            $table->year('ano');
            $table->string('mesmes', 2);
            $table->timestamps();

            $table->unique(['ano', 'mesmes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_advisor_monthly');
    }
};
