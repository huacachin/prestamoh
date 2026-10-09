<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 10/10/2026 (Antony): direcciones GPS adicionales del cliente ("tiene que haber
 * la posibilidad de agregar más direcciones"). Casa y Negocio siguen en
 * clients.latitud/longitud y latitud2/longitud2 (los reportes de GPS y el
 * resto del sistema leen de ahí); todas las demás, con el nombre que les
 * ponga el usuario (Taller, Chacra…), van en esta tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('nombre', 60);
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['client_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_ubicaciones');
    }
};
