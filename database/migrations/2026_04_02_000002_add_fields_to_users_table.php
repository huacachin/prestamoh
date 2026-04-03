<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->unique()->after('name');
            $table->string('document_type', 10)->nullable()->after('email');
            $table->string('document_number', 20)->nullable()->after('document_type');
            $table->string('phone', 20)->nullable()->after('document_number');
            $table->string('nivel', 30)->nullable()->after('phone');
            $table->foreignId('headquarter_id')->nullable()->constrained('headquarters')->nullOnDelete()->after('nivel');
            $table->enum('status', ['active', 'inactive'])->default('active')->index()->after('headquarter_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['headquarter_id']);
            $table->dropColumn(['username', 'document_type', 'document_number', 'phone', 'nivel', 'headquarter_id', 'status']);
        });
    }
};
