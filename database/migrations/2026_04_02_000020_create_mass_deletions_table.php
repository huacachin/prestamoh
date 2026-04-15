<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mass_deletions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('credit_id')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('date');
            $table->time('time');
            $table->string('user')->nullable();
            $table->string('advisor')->nullable();
            $table->string('performed_by')->nullable();
            $table->timestamps();
            $table->foreign('credit_id')->references('id')->on('credits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mass_deletions');
    }
};
