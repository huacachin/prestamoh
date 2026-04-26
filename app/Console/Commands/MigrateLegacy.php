<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MigrateLegacy extends Command
{
    protected $signature = 'migrate:legacy {--fresh : Limpia tablas destino antes de migrar}';
    protected $description = 'Migra datos desde la BD legacy (huacachi_prestamo) al nuevo schema';

    private function legacy()
    {
        return DB::connection('legacy');
    }

    public function handle(): int
    {
        $this->info('=== Migración Legacy → Laravel Prestamos ===');
        $this->newLine();

        if ($this->option('fresh')) {
            $this->warn('Limpiando tablas destino...');
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            CreditInstallment::truncate();
            Credit::truncate();
            Client::truncate();
            DB::table('headquarters')->where('id', '>', 0)->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->migrateHeadquarters();
        $this->migrateUsers();
        $this->migrateClients();
        $this->migrateCredits();
        $this->migrateInstallments();

        $this->newLine();
        $this->info('=== Migración completada ===');
        return 0;
    }

    private function migrateHeadquarters(): void
    {
        $this->info('1/5 Migrando sucursales...');
        $rows = $this->legacy()->table('sucursal')->get();
        $count = 0;

        foreach ($rows as $row) {
            Headquarter::updateOrCreate(
                ['id' => $row->id],
                [
                    'name'        => $row->nombre ?: $row->cod,
                    'empresa'     => $row->empresa,
                    'ruc'         => $row->ruc,
                    'slogan'      => $row->slogan,
                    'direccion'   => $row->direccion,
                    'telefono'    => $row->telefono,
                    'email'       => $row->email,
                    'responsable' => $row->responsable,
                    'sort_order'  => $row->id,
                    'status'      => strtolower($row->estado ?? '') === 'activo' ? 'active' : 'inactive',
                ]
            );
            $count++;
        }

        $this->line("   → {$count} sucursales migradas");
    }

    private function migrateUsers(): void
    {
        $this->info('2/5 Migrando usuarios...');
        $rows = $this->legacy()->table('clientes')->get();
        $count = 0;

        $roleMap = [
            'superusuario' => 'superusuario',
            'administrador' => 'administrador',
            'director' => 'director',
            'asesor' => 'asesor',
            'cobranza' => 'cobranza',
            'web' => 'web',
        ];

        foreach ($rows as $row) {
            if (empty($row->user)) continue;

            $user = User::updateOrCreate(
                ['username' => $row->user],
                [
                    'name'            => $row->nombre,
                    'email'           => $row->email ?: ($row->user . '@prestamos.local'),
                    'password'        => Hash::make('password123'),
                    'document_type'   => 'DNI',
                    'document_number' => $row->ruc ?: '00000000',
                    'phone'           => $row->movistar ?: ($row->fijo ?: ''),
                    'headquarter_id'  => $row->sucursal ?: null,
                    'status'          => strtolower($row->estado ?? '') === 'activo' ? 'active' : 'inactive',
                    'nivel'           => null,
                ]
            );

            $nivel = strtolower(trim($row->nivel ?? ''));
            $roleName = $roleMap[$nivel] ?? 'web';
            $user->syncRoles([$roleName]);
            $count++;
        }

        $this->line("   → {$count} usuarios migrados");
    }

    private function migrateClients(): void
    {
        $this->info('3/5 Migrando clientes (personas)...');
        $rows = $this->legacy()->table('persona')->get();
        $count = 0;
        $errors = 0;

        foreach ($rows as $row) {
            try {
                $nombre = trim($row->nombre ?? '');
                $apellidoPat = trim($row->apellidopat ?? '');
                $apellidoMat = trim($row->apellidomat ?? '');

                // Si no hay nombre separado, intentar parsear cnompersona
                if (empty($nombre) && !empty($row->cnompersona)) {
                    $parts = explode(' ', trim($row->cnompersona));
                    if (count($parts) >= 3) {
                        $apellidoPat = $parts[0] ?? '';
                        $apellidoMat = $parts[1] ?? '';
                        $nombre = implode(' ', array_slice($parts, 2));
                    } else {
                        $nombre = $row->cnompersona;
                    }
                }

                $doc = trim($row->cdnipersona ?? '');
                if (empty($doc)) continue;

                // Evitar duplicados por documento
                if (Client::where('documento', $doc)->exists()) {
                    continue;
                }

                $fechaNac = ($row->dnacpersona && $row->dnacpersona !== '0000-00-00')
                    ? $row->dnacpersona : null;

                Client::create([
                    'expediente'    => 'EXP-' . str_pad($row->id, 6, '0', STR_PAD_LEFT),
                    'nombre'        => $nombre,
                    'apellido_pat'  => $apellidoPat,
                    'apellido_mat'  => $apellidoMat,
                    'tipo_documento' => 'DNI',
                    'documento'     => $doc,
                    'fecha_nacimiento' => $fechaNac,
                    'sexo'          => strtoupper($row->csexpersona ?? 'M') ?: 'M',
                    'email'         => ($row->cemapersona && $row->cemapersona !== 'g@huacachin.com') ? $row->cemapersona : null,
                    'giro'          => $row->ntelefono ?: null,
                    'celular1'      => $row->nmovil ?: null,
                    'celular2'      => $row->nmovil2 ?: null,
                    'direccion'     => $row->cdireccion ?: null,
                    'referencia'    => $row->creferencia ?: null,
                    'zona'          => $row->cnomzona ?: null,
                    'banco_haberes' => $row->bancohaberes ?: null,
                    'cuenta_haberes' => $row->cuentabanhabe ?: null,
                    'banco_cts'     => $row->bancocts ?: null,
                    'cuenta_cts'    => $row->cuentacts ?: null,
                    'afp'           => $row->codigoafp ?: null,
                    'cussp'         => $row->cussp ?: null,
                    'latitud'       => $row->latitud ?: null,
                    'longitud'      => $row->longitud ?: null,
                    'observaciones' => null,
                    'asesor_id'     => null,
                    'headquarter_id' => null,
                    'status'        => ($row->cestpersona === '0' || $row->cestpersona === '1') ? 'active' : 'inactive',
                ]);
                $count++;
            } catch (\Throwable $e) {
                $errors++;
                $this->warn("   ! Error persona ID {$row->id}: {$e->getMessage()}");
            }
        }

        $this->line("   → {$count} clientes migrados ({$errors} errores)");
    }

    private function migrateCredits(): void
    {
        $this->info('4/5 Migrando créditos...');
        $rows = $this->legacy()->table('cab_cuentacorriente')->get();
        $count = 0;
        $errors = 0;

        // Crear mapa persona_id → client_id
        $personaToClient = [];
        $personas = $this->legacy()->table('persona')->get(['id', 'cdnipersona']);
        foreach ($personas as $p) {
            $doc = trim($p->cdnipersona ?? '');
            if ($doc) {
                $client = Client::where('documento', $doc)->first();
                if ($client) {
                    $personaToClient[$p->id] = $client->id;
                }
            }
        }

        foreach ($rows as $row) {
            try {
                $clientId = $personaToClient[$row->ccodpersona] ?? null;
                if (!$clientId) continue;

                $fechaPrestamo = ($row->fechaprestamo && $row->fechaprestamo !== '0000-00-00')
                    ? $row->fechaprestamo : null;
                $fechaFin = ($row->fechafin && $row->fechafin !== '0000-00-00')
                    ? $row->fechafin : null;
                $fechaCan = ($row->fechacan && $row->fechacan !== '0000-00-00')
                    ? $row->fechacan : null;

                $situacion = match (trim($row->situacion ?? '')) {
                    'Cancelado' => 'Cancelado',
                    'Refinanciado' => 'Refinanciado',
                    'Eliminado' => 'Eliminado',
                    default => 'Activo',
                };

                Credit::create([
                    'id'                => $row->id,
                    'client_id'         => $clientId,
                    'fecha_prestamo'    => $fechaPrestamo,
                    'importe'           => $row->importe ?? 0,
                    'cuotas'            => (int) ($row->cuotas ?? 1),
                    'tipo_planilla'     => (int) ($row->tipoplani ?? 3),
                    'interes'           => $row->interes ?? 0,
                    'interes_total'     => $row->interestot ?? 0,
                    'mora'              => $row->mora ?? 0,
                    'moneda'            => ($row->idmoneda === 'S' || $row->idmoneda === 'PEN') ? 'PEN' : 'USD',
                    'documento'         => $row->documento ?: null,
                    'glosa'             => $row->glosa ?: null,
                    'situacion'         => $situacion,
                    'estado'            => (int) ($row->estado ?? 1),
                    'refinanciado'      => (bool) $row->refi,
                    'fecha_vencimiento' => $fechaFin,
                    'fecha_cancelacion' => $fechaCan,
                    'asesor'            => $row->nomasesor ?: null,
                    'user_id'           => null,
                    'headquarter_id'    => null,
                ]);
                $count++;
            } catch (\Throwable $e) {
                $errors++;
                if ($errors <= 5) {
                    $this->warn("   ! Error crédito ID {$row->id}: {$e->getMessage()}");
                }
            }
        }

        $this->line("   → {$count} créditos migrados ({$errors} errores)");
    }

    private function migrateInstallments(): void
    {
        $this->info('5/5 Migrando cuotas...');
        $creditIds = Credit::pluck('id')->toArray();

        if (empty($creditIds)) {
            $this->line('   → Sin créditos para migrar cuotas');
            return;
        }

        $count = 0;
        $errors = 0;

        // Procesar en chunks para no agotar memoria
        $this->legacy()->table('det_cuentacorriente')
            ->whereIn('idcab', $creditIds)
            ->orderBy('idcab')
            ->orderBy('num_cuot')
            ->chunk(1000, function ($rows) use (&$count, &$errors) {
                foreach ($rows as $row) {
                    try {
                        $fechaPago = ($row->fechapago && $row->fechapago !== '0000-00-00')
                            ? $row->fechapago : null;

                        // Construir fecha vencimiento desde año/mes/dia
                        $fechaVenc = null;
                        if ($row->ano_codano && $row->mes_codmes) {
                            $dia = $row->dia_coddia ?: '1';
                            $fechaVenc = sprintf('%s-%s-%s', $row->ano_codano, str_pad($row->mes_codmes, 2, '0', STR_PAD_LEFT), str_pad($dia, 2, '0', STR_PAD_LEFT));
                            if (!strtotime($fechaVenc)) $fechaVenc = null;
                        }

                        CreditInstallment::create([
                            'credit_id'        => $row->idcab,
                            'num_cuota'        => $row->num_cuot,
                            'fecha_vencimiento' => $fechaVenc,
                            'importe_cuota'    => $row->importecuota ?? 0,
                            'importe_interes'  => $row->importeinteres ?? 0,
                            'importe_aplicado' => $row->importeapli ?? 0,
                            'interes_aplicado' => $row->aplicado ?? 0,
                            'importe_mora'     => ($row->impomora ?? 0) + ($row->impomorai ?? 0),
                            'pagado'           => (bool) $row->flpago,
                            'fecha_pago'       => $fechaPago,
                            'observacion'      => $row->observacion ?: null,
                            'usuario'          => $row->usuario ?: null,
                        ]);
                        $count++;
                    } catch (\Throwable $e) {
                        $errors++;
                        if ($errors <= 5) {
                            $this->warn("   ! Error cuota cab={$row->idcab} cuota={$row->num_cuot}: {$e->getMessage()}");
                        }
                    }
                }
            });

        $this->line("   → {$count} cuotas migradas ({$errors} errores)");
    }
}
