<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;

/**
 * Pone tipo_documento = RUC a los clientes cuyo número ES un RUC (11 dígitos
 * que empiezan en 10 ó 20) pero quedaron como DNI.
 *
 * La migración del legacy (MigrateLegacyData) puso 'DNI' a todos los
 * clientes; las empresas migradas quedaron como personas naturales y el
 * wizard de contratos no activaba el modelo a.4 (deudor empresa) ni pedía
 * los datos del gerente general (02/10/2026). Idempotente: una segunda
 * corrida no encuentra nada. Cada cambio queda en la auditoría automática
 * del modelo (Auditable).
 */
class ClientesNormalizarRuc extends Command
{
    protected $signature = 'clientes:normalizar-ruc {--dry-run : Solo lista, no cambia nada}';

    protected $description = 'Marca como RUC a los clientes con número de RUC registrados como DNI (empresas migradas del legacy).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $clientes = Client::query()
            ->where('tipo_documento', '!=', 'RUC')
            ->whereRaw("TRIM(documento) REGEXP '^(10|20)[0-9]{9}$'")
            ->orderBy('id')
            ->get();

        if ($clientes->isEmpty()) {
            $this->info('No hay clientes con número de RUC registrados con otro tipo de documento.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Expediente', 'Razón social', 'Documento', 'Tipo actual'],
            $clientes->map(fn (Client $c) => [$c->id, $c->expediente, $c->fullName(), $c->documento, $c->tipo_documento])->all()
        );

        if ($dryRun) {
            $this->warn("Modo --dry-run: {$clientes->count()} cliente(s) pasarían a RUC. Nada cambió.");

            return self::SUCCESS;
        }

        foreach ($clientes as $c) {
            $c->update(['tipo_documento' => 'RUC']);
        }

        $this->info("{$clientes->count()} cliente(s) marcados como RUC.");

        return self::SUCCESS;
    }
}
