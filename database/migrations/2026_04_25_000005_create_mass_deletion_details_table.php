<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mass_deletion_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mass_deletion_id')->index();
            $table->unsignedBigInteger('installment_id')->nullable()->index();
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->decimal('amount', 12, 2)->default(0);
            $table->dateTime('fecha')->nullable();
            $table->string('tipo', 5)->nullable()->comment('C=Capital, I=Interes, M=Mora');
            $table->timestamps();

            $table->foreign('mass_deletion_id')->references('id')->on('mass_deletions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mass_deletion_details');
    }
};
