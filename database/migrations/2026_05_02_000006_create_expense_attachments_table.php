<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->string('filename');
            $table->string('original_name')->nullable();
            $table->string('path');
            $table->string('thumb_path')->nullable();
            $table->string('mime', 80)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->string('uploaded_by')->nullable();
            $table->timestamps();

            $table->index('expense_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_attachments');
    }
};
