<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revierte los correos múltiples (05/09, pedido de Antony: "revertir todo
 * incluso la tabla" — el feature del 04/09 fue una confusión). clients.email
 * fue SIEMPRE el espejo del principal, así que no se pierde el correo
 * operativo de nadie; los secundarios agregados en el día que vivió el
 * feature se van con la tabla (respaldar client_emails antes de migrar en
 * prod, por si acaso).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('client_emails');
        // La migración de creación ya no existe en el repo: fuera su entrada.
        DB::table('migrations')->where('migration', '2026_09_04_000001_create_client_emails')->delete();
    }

    public function down(): void
    {
        // Recrear vacía no tiene sentido; el revert es definitivo.
    }
};
