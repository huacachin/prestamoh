<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('filename');
            $table->string('original_name')->nullable();
            $table->string('path');           // ruta relativa al disk public
            $table->string('thumb_path')->nullable();
            $table->string('mime', 80)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->string('uploaded_by')->nullable(); // username
            $table->timestamps();

            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_attachments');
    }
};
