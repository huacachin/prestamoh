<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('income_id')->constrained('incomes')->cascadeOnDelete();
            $table->string('filename');
            $table->string('original_name')->nullable();
            $table->string('path');
            $table->string('thumb_path')->nullable();
            $table->string('mime', 80)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->string('uploaded_by')->nullable();
            $table->timestamps();

            $table->index('income_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_attachments');
    }
};
