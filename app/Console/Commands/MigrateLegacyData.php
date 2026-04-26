<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\{User, Client, Credit, CreditInstallment, Payment, Income, Expense, CashOpening, Concept, ExchangeRate, MassDeletion, Headquarter};
use Spatie\Permission\Models\Role;

class MigrateLegacyData extends Command
{
    protected $signature = 'legacy:migrate
                            {--step= : Run a specific step (headquarters,users,clients,credits,installments,payments,incomes,incomes3,expenses,expenses3,cash,concepts,exchange,mass)}
                            {--fresh : Truncate target tables before importing}';

    protected $description = 'Migrate data from legacy huacachi_prestamo database to the new Laravel schema';

    private int $legacyEmpresa = 1; // default empresa filter

    public function handle(): int
    {
        $step = $this->option('step');
        $fresh = $this->option('fresh');

        if ($fresh && !$step) {
            if (!$this->confirm('This will DELETE all existing data. Continue?')) {
                return 1;
            }
        }

        // Test legacy connection
        try {
            DB::connection('legacy')->getPdo();
            $this->info('Legacy database connection OK.');
        } catch (\Exception $e) {
            $this->error('Cannot connect to legacy database: ' . $e->getMessage());
            return 1;
        }

        $steps = [
            'headquarters' => 'migrateHeadquarters',
            'users'        => 'migrateUsers',
            'clients'      => 'migrateClients',
            'credits'      => 'migrateCredits',
            'installments' => 'migrateInstallments',
            'payments'     => 'migratePayments',
            'incomes'      => 'migrateIncomes',
            'incomes3'     => 'migrateIncomes3',
            'expenses'     => 'migrateExpenses',
            'expenses3'    => 'migrateExpenses3',
            'cash'         => 'migrateCashOpenings',
            'concepts'     => 'migrateConcepts',
            'exchange'     => 'migrateExchangeRates',
            'mass'         => 'migrateMassDeletions',
            'capineto'     => 'migrateCapitalNeto',
            'cache'        => 'migrateCacheTables',
            'mora'         => 'migrateMoraTables',
            'resumen'      => 'migrateResumenMensual',
        ];

        if ($step) {
            if (!isset($steps[$step])) {
                $this->error("Unknown step: {$step}. Valid: " . implode(', ', array_keys($steps)));
                return 1;
            }
            $this->{$steps[$step]}($fresh);
        } else {
            foreach ($steps as $name => $method) {
                $this->{$method}($fresh);
            }
        }

        $this->newLine();
        $this->info('Migration completed!');
        return 0;
    }

    // ─── HEADQUARTERS ──────────────────────────────────────────────
    private function migrateHeadquarters(bool $fresh): void
    {
        $this->info('─── Migrating Headquarters ───');

        if ($fresh) {
            Schema::disableForeignKeyConstraints();
            Headquarter::truncate();
            Schema::enableForeignKeyConstraints();
        }

        // Legacy doesn't have a dedicated sucursal table with details,
        // but we know there's at least one branch. Create default.
        $hq = Headquarter::firstOrCreate(
            ['id' => 1],
            [
                'name'       => 'Huacachin - Principal',
                'empresa'    => 'Huacachin',
                'status'     => 'active',
                'sort_order' => 1,
            ]
        );

        $this->info("  Headquarters: {$hq->name} (ID: {$hq->id})");
    }

    // ─── USERS ─────────────────────────────────────────────────────
    private function migrateUsers(bool $fresh): void
    {
        $this->info('─── Migrating Users ───');

        if ($fresh) {
            Schema::disableForeignKeyConstraints();
            DB::table('model_has_roles')->truncate();
            DB::table('model_has_permissions')->truncate();
            User::truncate();
            Schema::enableForeignKeyConstraints();
        }

        // Ensure roles exist
        $roleMap = [
            'SuperUsuario'  => 'superusuario',
            'Administrador' => 'administrador',
            'Director'      => 'director',
            'Asesor'        => 'asesor',
            'Cobranza'      => 'cobranza',
            'Web'           => 'web',
        ];

        foreach ($roleMap as $legacy => $new) {
            Role::firstOrCreate(['name' => $new, 'guard_name' => 'web']);
        }

        $legacyUsers = DB::connection('legacy')
            ->table('clientes')
            ->whereNotNull('user')
            ->where('user', '<>', '')
            ->get();

        $count = 0;
        foreach ($legacyUsers as $lu) {
            $username = trim($lu->user);
            if (empty($username)) continue;

            $roleName = $roleMap[$lu->nivel] ?? 'web';

            // Contraseña: usar obs3 (texto plano) si existe, sino usar 'password123'
            $plainPassword = trim($lu->obs3 ?? '') ?: 'password123';

            $user = User::updateOrCreate(
                ['username' => strtolower($username)],
                [
                    'name'            => $lu->nombre ?? $username,
                    'email'           => $lu->email ?: strtolower($username) . '@prestamos.local',
                    'password'        => Hash::make($plainPassword),
                    'document_type'   => $lu->tdoc ?: 'DNI',
                    'document_number' => $lu->ruc ?: null,
                    'phone'           => $lu->movistar ?: $lu->claro ?: $lu->fijo ?: null,
                    'nivel'           => $lu->nivel,
                    'headquarter_id'  => 1,
                    'status'          => ($lu->estado === 'Cesado') ? 'inactive' : 'active',
                ]
            );

            $user->syncRoles([$roleName]);

            // Assign all permissions to superusuario
            if ($roleName === 'superusuario') {
                $allPerms = \App\Models\Permission::pluck('name')->toArray();
                $user->syncPermissions($allPerms);
            }

            // Store legacy cod mapping for later reference
            DB::table('users')
                ->where('id', $user->id)
                ->update(['nivel' => $lu->cod]); // temporarily store legacy cod in nivel

            $count++;
        }

        $this->info("  Users migrated: {$count}");
    }

    // ─── CLIENTS ───────────────────────────────────────────────────
    private function migrateClients(bool $fresh): void
    {
        $this->info('─── Migrating Clients ───');

        if ($fresh) {
            Schema::disableForeignKeyConstraints();
            Client::truncate();
            Schema::enableForeignKeyConstraints();
        }

        $legacyClients = DB::connection('legacy')
            ->table('persona')
            ->whereIn('cnivpersona', [2, 3])
            ->get();

        $bar = $this->output->createProgressBar($legacyClients->count());
        $bar->start();

        // Build user mapping: legacy clientes.cod → users.id
        $userMap = $this->buildUserMap();

        $count = 0;
        $now = now();
        foreach ($legacyClients as $lc) {
            $asesorId = $userMap[$lc->ccodrelacion] ?? null;

            DB::table('clients')->updateOrInsert(
                ['id' => $lc->id],
                [
                    'id'                  => $lc->id,
                    'expediente'          => $lc->ccodpersona ?: null,
                    'nombre'              => $lc->nombre ?: $lc->cnompersona,
                    'apellido_pat'        => $lc->apellidopat ?: null,
                    'apellido_mat'        => $lc->apellidomat ?: null,
                    'tipo_documento'      => 'DNI',
                    'documento'           => $lc->cdnipersona ?: null,
                    'fecha_registro'      => ($lc->dfecpersona && $lc->dfecpersona !== '0000-00-00') ? $lc->dfecpersona : null,
                    'usuario'             => $lc->cnikpersona ?: null,
                    'fecha_nacimiento'    => ($lc->dnacpersona && $lc->dnacpersona !== '0000-00-00') ? $lc->dnacpersona : null,
                    'sexo'                => ($lc->csexpersona === 'M' || $lc->csexpersona === 'F') ? $lc->csexpersona : null,
                    'email'               => $lc->cemapersona ?: null,
                    'giro'                => $lc->ntelefono ?: null,
                    'celular1'            => $lc->nmovil ?: null,
                    'celular2'            => $lc->nmovil2 ?: null,
                    'direccion'           => $lc->cdireccion ?: null,
                    'referencia'          => $lc->creferencia ?: null,
                    'zona'                => $lc->cnomzona ?: null,
                    'banco_haberes'       => $lc->bancohaberes ?: null,
                    'cuenta_haberes'      => $lc->cuentabanhabe ?: null,
                    'banco_cts'           => $lc->bancocts ?: null,
                    'cuenta_cts'          => $lc->cuentacts ?: null,
                    'afp'                 => $lc->codigoafp ?: null,
                    'cussp'               => $lc->cussp ?: null,
                    'latitud'             => is_numeric($lc->latitud) ? $lc->latitud : null,
                    'longitud'            => is_numeric($lc->longitud) ? $lc->longitud : null,
                    'latitud2'            => is_numeric($lc->latitud2 ?? null) ? $lc->latitud2 : null,
                    'longitud2'           => is_numeric($lc->longitud2 ?? null) ? $lc->longitud2 : null,
                    'asesor_id'           => $asesorId,
                    'headquarter_id'      => 1,
                    'status'              => match ((string) $lc->cestpersona) {
                        '1'     => 'active',
                        '0'     => 'inactive',
                        default => 'deleted',
                    },
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ]
            );

            $count++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  Clients migrated: {$count}");
    }

    // ─── CREDITS ───────────────────────────────────────────────────
    private function migrateCredits(bool $fresh): void
    {
        $this->info('─── Migrating Credits ───');

        if ($fresh) {
            Schema::disableForeignKeyConstraints();
            Credit::truncate();
            Schema::enableForeignKeyConstraints();
        }

        $userMap = $this->buildUserMap();

        $legacyCredits = DB::connection('legacy')
            ->table('cab_cuentacorriente')
            ->get();

        $bar = $this->output->createProgressBar($legacyCredits->count());
        $bar->start();

        // Pre-load valid client IDs for fast lookup
        $validClientIds = Client::pluck('id')->flip();

        $count = 0;
        $now = now();
        foreach ($legacyCredits as $lc) {
            // Map situacion
            $situacion = $lc->situacion ?: 'Activo';
            $situacionMap = [
                'Vigente'     => 'Activo',
                'Cancelado'   => 'Cancelado',
                'Refinanciado' => 'Refinanciado',
                'R. Capital'  => 'Cancelado',
                'R.C.P. Int. Cong' => 'Cancelado',
                'Judicializado' => 'Activo',
                'Condonado'   => 'Cancelado',
            ];
            $newSituacion = $situacionMap[$situacion] ?? $situacion;

            // Find client ID in new DB
            if (!isset($validClientIds[$lc->ccodpersona])) {
                $bar->advance();
                continue;
            }

            $asesorId = $userMap[$lc->nomasesor ?? ''] ?? null;
            // Try user field
            if (!$asesorId && $lc->user) {
                $asesorId = User::where('username', strtolower($lc->user))->value('id');
            }

            DB::table('credits')->updateOrInsert(
                ['id' => $lc->id],
                [
                    'id'                 => $lc->id,
                    'client_id'          => $lc->ccodpersona,
                    'fecha_prestamo'     => ($lc->fechaprestamo && $lc->fechaprestamo !== '0000-00-00') ? $lc->fechaprestamo : now()->format('Y-m-d'),
                    'fecha_actualizacion' => ($lc->fechaactua && $lc->fechaactua !== '0000-00-00') ? $lc->fechaactua : null,
                    'importe'            => $lc->importe ?? 0,
                    'cuotas'             => $lc->cuotas ?? 1,
                    'tipo_planilla'      => $lc->tipoplani ?? 3,
                    'interes'            => $lc->interes ?? 0,
                    'interes_total'      => $lc->interestot ?? 0,
                    'mora'               => $lc->mora ?? 0,
                    'mora1'              => $lc->mora1 ?? 0,
                    'mora2'              => $lc->mora2 ?? 0,
                    'moneda'             => 'Soles',
                    'documento'          => $lc->documento ?: null,
                    'glosa'              => $lc->glosa ?: null,
                    'situacion'          => $newSituacion,
                    'estado'             => $lc->estado ?? 1,
                    'refinanciado'       => ($lc->refi == 1 || $lc->cod_rem === 'REF') ? 1 : 0,
                    'cod_rem'            => $lc->cod_rem ?: null,
                    'gat'                => $lc->gat ?? 0,
                    'idcan'              => $lc->idcan ?: null,
                    'fecha_vencimiento'  => ($lc->fechafin && $lc->fechafin !== '0000-00-00') ? $lc->fechafin : null,
                    'fecha_cancelacion'  => ($lc->fechacan && $lc->fechacan !== '0000-00-00') ? $lc->fechacan : null,
                    'asesor'             => $lc->nomasesor ?: null,
                    'user_id'            => $asesorId,
                    'usuario'            => $lc->user ?: null,
                    'headquarter_id'     => 1,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ]
            );

            $count++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  Credits migrated: {$count}");
    }

    // ─── INSTALLMENTS ──────────────────────────────────────────────
    private function migrateInstallments(bool $fresh): void
    {
        $this->info('─── Migrating Credit Installments ───');

        if ($fresh) {
            Schema::disableForeignKeyConstraints();
            CreditInstallment::truncate();
            Schema::enableForeignKeyConstraints();
        }

        // Get valid credit IDs
        $validCredits = Credit::pluck('id')->flip();

        $total = DB::connection('legacy')->table('det_cuentacorriente')->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $count = 0;
        DB::connection('legacy')
            ->table('det_cuentacorriente')
            ->orderBy('id')
            ->chunk(1000, function ($chunk) use (&$count, $validCredits, $bar) {
                $batch = [];
                foreach ($chunk as $li) {
                    $bar->advance();

                    if (!isset($validCredits[$li->idcab])) {
                        continue;
                    }

                    $fechaVenc = null;
                    if ($li->ano_codano && $li->mes_codmes && $li->dia_coddia) {
                        $fechaVenc = sprintf('%s-%s-%s', $li->ano_codano, str_pad($li->mes_codmes, 2, '0', STR_PAD_LEFT), str_pad($li->dia_coddia, 2, '0', STR_PAD_LEFT));
                        if (!strtotime($fechaVenc)) $fechaVenc = null;
                    }

                    $batch[] = [
                        'id'                => $li->id,
                        'credit_id'         => $li->idcab,
                        'num_cuota'         => $li->num_cuot ?? 0,
                        'fecha_vencimiento' => $fechaVenc ?? now()->format('Y-m-d'),
                        'importe_cuota'     => $li->importecuota ?? 0,
                        'importe_interes'   => $li->importeinteres ?? 0,
                        'importe_aplicado'  => $li->importeapli ?? 0,
                        'interes_aplicado'  => $li->aplicado ?? 0,
                        'importe_mora'      => ($li->impomora ?? 0) + ($li->impomorai ?? 0),
                        'pagado'            => ($li->flpago == 1),
                        'fecha_pago'        => ($li->fechapago && $li->fechapago !== '0000-00-00') ? $li->fechapago : null,
                        'observacion'       => $li->observacion ?: null,
                        'usuario'           => $li->usuario ?: null,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ];

                    $count++;
                }

                if (!empty($batch)) {
                    CreditInstallment::upsert($batch, ['id'], array_keys($batch[0]));
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info("  Installments migrated: {$count}");
    }

    // ─── PAYMENTS ──────────────────────────────────────────────────
    private function migratePayments(bool $fresh): void
    {
        $this->info('─── Migrating Payments ───');

        if ($fresh) {
            Schema::disableForeignKeyConstraints();
            Payment::truncate();
            Schema::enableForeignKeyConstraints();
        }

        $validCredits = Credit::pluck('id')->flip();

        $total = DB::connection('legacy')->table('ingreso')->where('modo', 'CREDITO')->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $count = 0;
        DB::connection('legacy')
            ->table('ingreso')
            ->where('modo', 'CREDITO')
            ->orderBy('identrada')
            ->chunk(1000, function ($chunk) use (&$count, $validCredits, $bar) {
                $batch = [];
                foreach ($chunk as $lp) {
                    $bar->advance();

                    $creditId = $lp->nroentrada;
                    if (!is_numeric($creditId) || !isset($validCredits[(int)$creditId])) {
                        continue;
                    }

                    $doc = strtoupper(trim($lp->documento ?? ''));
                    $tipo = 'CAPITAL';
                    if (str_contains($doc, 'INTERES')) {
                        $tipo = 'INTERES';
                    } elseif (str_contains($doc, 'MORA')) {
                        $tipo = 'MORA';
                    }

                    // Fechas invalidas legacy ('0000-00-00') se guardan como NULL
                    // para que no aparezcan en reportes filtrados por fecha
                    // pero sí cuenten en agregados por crédito (igual al legacy)
                    $fechaPago = ($lp->fechaentrada && $lp->fechaentrada !== '0000-00-00')
                        ? $lp->fechaentrada
                        : null;

                    $batch[] = [
                        'credit_id'      => (int)$creditId,
                        'installment_id' => null,
                        'modo'           => $lp->modo ?: 'CREDITO',
                        'tipo'           => $tipo,
                        'documento'      => $lp->documento ?: null,
                        'nro_recibo'     => $lp->coditem ?: null,
                        'fecha'          => $fechaPago,
                        'hora'           => $lp->tipo ?: null,
                        'monto'          => $lp->totalgeneral ?? 0,
                        'moneda'         => 'Soles',
                        'tipo_cambio'    => ($lp->cambio && $lp->cambio > 0) ? $lp->cambio : null,
                        'detalle'        => $lp->detalle ?: null,
                        'asesor'         => $lp->asesor ?: null,
                        'user_id'        => null,
                        'usuario'        => $lp->usuario ?: null,
                        'headquarter_id' => 1,
                        'latitud'        => is_numeric($lp->latitud ?? null) ? $lp->latitud : null,
                        'longitud'       => is_numeric($lp->longitud ?? null) ? $lp->longitud : null,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];

                    $count++;
                }

                if (!empty($batch)) {
                    Payment::insert($batch);
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info("  Payments migrated: {$count}");
    }

    // ─── INCOMES ───────────────────────────────────────────────────
    private function migrateIncomes(bool $fresh): void
    {
        $this->info('─── Migrating Incomes ───');

        if ($fresh) {
            Schema::disableForeignKeyConstraints();
            Income::truncate();
            Schema::enableForeignKeyConstraints();
        }

        // Build user mapping
        $userMap = [];
        foreach (\App\Models\User::pluck('id', 'username') as $username => $id) {
            $userMap[strtolower($username)] = $id;
        }

        $total = DB::connection('legacy')->table('ingreso')->where('modo', '<>', 'CREDITO')->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $count = 0;
        DB::connection('legacy')
            ->table('ingreso')
            ->where('modo', '<>', 'CREDITO')
            ->orderBy('identrada')
            ->chunk(1000, function ($chunk) use (&$count, $bar, $userMap) {
                $batch = [];
                foreach ($chunk as $li) {
                    $bar->advance();
                    $userId = $userMap[strtolower(trim($li->usuario ?? ''))] ?? null;

                    $batch[] = [
                        'date'           => ($li->fechaentrada && $li->fechaentrada !== '0000-00-00') ? $li->fechaentrada : now()->format('Y-m-d'),
                        'reason'         => $li->aa ?: ($li->modo ?: 'Otros'),
                        'modo'           => $li->modo ?: null,
                        'documento'      => $li->documento ?: null,
                        'asesor'         => $li->asesor ?: null,
                        'detail'         => mb_substr($li->detalle ?: '', 0, 255),
                        'total'          => $li->totalgeneral ?? 0,
                        'user_id'        => $userId,
                        'headquarter_id' => 1,
                        'caja'           => 1,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                    $count++;
                }
                if (!empty($batch)) {
                    Income::insert($batch);
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info("  Incomes migrated: {$count}");
    }

    // ─── INCOMES CAJA 3 ────────────────────────────────────────────
    private function migrateIncomes3(bool $fresh): void
    {
        $this->info('─── Migrating Incomes (Caja 3) ───');

        if ($fresh) {
            Schema::disableForeignKeyConstraints();
            Income::where('caja', 3)->delete();
            Schema::enableForeignKeyConstraints();
        }

        $userMap = [];
        foreach (\App\Models\User::pluck('id', 'username') as $username => $id) {
            $userMap[strtolower($username)] = $id;
        }

        $total = DB::connection('legacy')->table('ingreso3')->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $count = 0;
        DB::connection('legacy')
            ->table('ingreso3')
            ->orderBy('identrada')
            ->chunk(1000, function ($chunk) use (&$count, $bar, $userMap) {
                $batch = [];
                foreach ($chunk as $li) {
                    $bar->advance();
                    $userId = $userMap[strtolower(trim($li->usuario ?? ''))] ?? null;

                    $batch[] = [
                        'date'           => ($li->fechaentrada && $li->fechaentrada !== '0000-00-00') ? $li->fechaentrada : now()->format('Y-m-d'),
                        'reason'         => $li->aa ?: ($li->modo ?: 'Otros'),
                        'modo'           => $li->modo ?: null,
                        'documento'      => $li->documento ?: null,
                        'asesor'         => $li->asesores ?: null,
                        'detail'         => mb_substr($li->detalle ?: '', 0, 255),
                        'total'          => $li->totalgeneral ?? 0,
                        'user_id'        => $userId,
                        'headquarter_id' => 1,
                        'caja'           => 3,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                    $count++;
                }
                if (!empty($batch)) {
                    Income::insert($batch);
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info("  Incomes Caja 3 migrated: {$count}");
    }

    // ─── EXPENSES ──────────────────────────────────────────────────
    private function migrateExpenses(bool $fresh): void
    {
        $this->info('─── Migrating Expenses ───');

        if ($fresh) {
            Schema::disableForeignKeyConstraints();
            Expense::truncate();
            Schema::enableForeignKeyConstraints();
        }

        // Build user mapping
        $userMap = [];
        foreach (\App\Models\User::pluck('id', 'username') as $username => $id) {
            $userMap[strtolower($username)] = $id;
        }

        $total = DB::connection('legacy')->table('entrada')->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $count = 0;
        DB::connection('legacy')
            ->table('entrada')
            ->orderBy('identrada')
            ->chunk(1000, function ($chunk) use (&$count, $bar, $userMap) {
                $batch = [];
                foreach ($chunk as $le) {
                    $bar->advance();
                    $userId = $userMap[strtolower(trim($le->usuario ?? ''))] ?? null;

                    $batch[] = [
                        'date'           => ($le->fechaentrada && $le->fechaentrada !== '0000-00-00') ? $le->fechaentrada : now()->format('Y-m-d'),
                        'reason'         => $le->aa ?: ($le->modo ?: 'Otros'),
                        'modo'           => $le->modo ?: null,
                        'detail'         => mb_substr($le->detalle ?: '', 0, 255),
                        'total'          => $le->totalgeneral ?? 0,
                        'document_type'  => $le->tipcom ?: null,
                        'in_charge'      => $le->respons ?: null,
                        'user_id'        => $userId,
                        'headquarter_id' => 1,
                        'caja'           => 1,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                    $count++;
                }
                if (!empty($batch)) {
                    Expense::insert($batch);
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info("  Expenses migrated: {$count}");
    }

    // ─── EXPENSES CAJA 3 ───────────────────────────────────────────
    private function migrateExpenses3(bool $fresh): void
    {
        $this->info('─── Migrating Expenses (Caja 3) ───');

        if ($fresh) {
            Schema::disableForeignKeyConstraints();
            Expense::where('caja', 3)->delete();
            Schema::enableForeignKeyConstraints();
        }

        $userMap = [];
        foreach (\App\Models\User::pluck('id', 'username') as $username => $id) {
            $userMap[strtolower($username)] = $id;
        }

        $total = DB::connection('legacy')->table('entrada3')->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $count = 0;
        DB::connection('legacy')
            ->table('entrada3')
            ->orderBy('identrada')
            ->chunk(1000, function ($chunk) use (&$count, $bar, $userMap) {
                $batch = [];
                foreach ($chunk as $le) {
                    $bar->advance();
                    $userId = $userMap[strtolower(trim($le->usuario ?? ''))] ?? null;

                    $batch[] = [
                        'date'           => ($le->fechaentrada && $le->fechaentrada !== '0000-00-00') ? $le->fechaentrada : now()->format('Y-m-d'),
                        'reason'         => $le->aa ?: ($le->modo ?: 'Otros'),
                        'modo'           => $le->modo ?: null,
                        'detail'         => mb_substr($le->detalle ?: '', 0, 255),
                        'total'          => $le->totalgeneral ?? 0,
                        'document_type'  => null,
                        'in_charge'      => null,
                        'user_id'        => $userId,
                        'headquarter_id' => 1,
                        'caja'           => 3,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                    $count++;
                }
                if (!empty($batch)) {
                    Expense::insert($batch);
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info("  Expenses Caja 3 migrated: {$count}");
    }

    // ─── CASH OPENINGS ─────────────────────────────────────────────
    private function migrateCashOpenings(bool $fresh): void
    {
        $this->info('─── Migrating Cash Openings ───');

        if ($fresh) {
            Schema::disableForeignKeyConstraints();
            CashOpening::truncate();
            Schema::enableForeignKeyConstraints();
        }

        $legacyCash = DB::connection('legacy')
            ->table('caja')
            ->get();

        // Build user mapping: legacy clientes.user/usuario → new users.id
        $userMap = [];
        foreach (\App\Models\User::pluck('id', 'username') as $username => $id) {
            $userMap[strtolower($username)] = $id;
        }

        $count = 0;
        foreach ($legacyCash as $lc) {
            $userId = $userMap[strtolower(trim($lc->usuario ?? ''))] ?? null;

            CashOpening::create([
                'fecha'          => ($lc->fecha && $lc->fecha !== '0000-00-00') ? $lc->fecha : now()->format('Y-m-d'),
                'hora'           => $lc->hora ?: null,
                'saldo_inicial'  => $lc->importe ?? 0,
                'saldo_final'    => 0,
                'estado'         => match (strtolower($lc->estado ?? '')) {
                    'activo', 'abierto' => 'abierto',
                    default              => 'cerrado',
                },
                'moneda'         => $lc->moneda ?: 'Soles',
                'user_id'        => $userId,
                'headquarter_id' => 1,
            ]);
            $count++;
        }

        $this->info("  Cash Openings migrated: {$count}");
    }

    // ─── CONCEPTS ──────────────────────────────────────────────────
    private function migrateConcepts(bool $fresh): void
    {
        $this->info('─── Migrating Concepts ───');

        if ($fresh) {
            Concept::truncate();
        }

        $legacyConcepts = DB::connection('legacy')
            ->table('webparametros')
            ->where('ccodparametro', '0012')
            ->get();

        $count = 0;
        foreach ($legacyConcepts as $lc) {
            Concept::updateOrCreate(
                ['code' => $lc->cvalparametro],
                [
                    'name'           => $lc->cnombrees ?: 'Sin nombre',
                    'type'           => ($lc->ctipocon == '1') ? 'Ingreso' : 'Egreso',
                    'factor_ingreso' => $lc->facpago ?? 0,
                    'factor_egreso'  => $lc->facdivi ?? 0,
                    'status'         => ($lc->cestparametro == 1) ? 'active' : 'inactive',
                ]
            );
            $count++;
        }

        $this->info("  Concepts migrated: {$count}");
    }

    // ─── EXCHANGE RATES ────────────────────────────────────────────
    private function migrateExchangeRates(bool $fresh): void
    {
        $this->info('─── Migrating Exchange Rates ───');

        if ($fresh) {
            ExchangeRate::truncate();
        }

        $legacyRates = DB::connection('legacy')
            ->table('tipocambio')
            ->get();

        $count = 0;
        foreach ($legacyRates as $lr) {
            $fecha = ($lr->fecha && $lr->fecha !== '0000-00-00') ? $lr->fecha : now()->format('Y-m-d');

            ExchangeRate::updateOrCreate(
                ['fecha' => $fecha],
                [
                    'compra' => $lr->compra ?? 0,
                    'venta'  => $lr->cambio ?? 0,
                ]
            );
            $count++;
        }

        $this->info("  Exchange Rates migrated: {$count}");
    }

    // ─── MASS DELETIONS ────────────────────────────────────────────
    private function migrateMassDeletions(bool $fresh): void
    {
        $this->info('─── Migrating Mass Deletions ───');

        if ($fresh) {
            Schema::disableForeignKeyConstraints();
            DB::table('mass_deletion_details')->truncate();
            MassDeletion::truncate();
            Schema::enableForeignKeyConstraints();
        }

        $validCredits = Credit::pluck('id')->flip();

        $legacyMass = DB::connection('legacy')
            ->table('cab_masivo')
            ->get();

        $now = now();
        $count = 0;
        foreach ($legacyMass as $lm) {
            DB::table('mass_deletions')->updateOrInsert(
                ['id' => $lm->id],
                [
                    'id'           => $lm->id,
                    'credit_id'    => isset($validCredits[$lm->codpres]) ? $lm->codpres : null,
                    'amount'       => $lm->monto ?? 0,
                    'date'         => ($lm->fecha && $lm->fecha !== '0000-00-00') ? $lm->fecha : now()->format('Y-m-d'),
                    'time'         => $lm->hora ?: '00:00:00',
                    'user'         => null,
                    'advisor'      => $lm->asesor ?: null,
                    'performed_by' => $lm->usuario2 ?: null,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]
            );
            $count++;
        }

        $this->info("  Mass Deletions migrated: {$count}");

        // Migrate details
        $this->info('─── Migrating Mass Deletion Details ───');
        $validMass = DB::table('mass_deletions')->pluck('id')->flip();
        $total = DB::connection('legacy')->table('det_masivo')->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $countDet = 0;
        DB::connection('legacy')->table('det_masivo')->orderBy('id')->chunk(1000, function ($chunk) use (&$countDet, $validMass, $bar, $now) {
            $batch = [];
            foreach ($chunk as $dm) {
                $bar->advance();
                if (!isset($validMass[$dm->idcab])) {
                    continue;
                }
                $batch[] = [
                    'id'                => $dm->id,
                    'mass_deletion_id'  => $dm->idcab,
                    'installment_id'    => $dm->codigocuota ?: null,
                    'payment_id'        => $dm->codigoing ?: null,
                    'amount'            => $dm->montocuota ?? 0,
                    'fecha'             => ($dm->fecha && $dm->fecha !== '0000-00-00 00:00:00') ? $dm->fecha : null,
                    'tipo'              => $dm->tipo ?: null,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
                $countDet++;
            }
            if (!empty($batch)) {
                DB::table('mass_deletion_details')->insert($batch);
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("  Mass Deletion Details migrated: {$countDet}");
    }

    // ─── CAPITAL NETO ──────────────────────────────────────────────
    private function migrateCapitalNeto(bool $fresh): void
    {
        $this->info('─── Migrating Capital Neto ───');

        if ($fresh) {
            DB::table('capital_neto')->truncate();
        }

        $now = now();
        $count = 0;

        DB::connection('legacy')
            ->table('capineto')
            ->orderBy('fecha')
            ->chunk(1000, function ($chunk) use (&$count, $now) {
                $batch = [];
                foreach ($chunk as $r) {
                    if (!$r->fecha || $r->fecha === '0000-00-00') continue;
                    $batch[] = [
                        'fecha'      => $r->fecha,
                        'importe'    => $r->importe ?? 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $count++;
                }
                if (!empty($batch)) {
                    DB::table('capital_neto')->upsert($batch, ['fecha'], ['importe', 'updated_at']);
                }
            });

        $this->info("  Capital Neto migrated: {$count}");
    }

    // ─── RESUMEN MENSUAL (huaca_tbresumen) ─────────────────────────
    public function migrateResumenMensual(bool $fresh): void
    {
        $this->info('─── Migrating Resumen Mensual ───');

        if ($fresh) {
            DB::table('cache_resumen_mensual')->truncate();
        }

        $now = now();
        $count = 0;
        DB::connection('legacy')->table('tbresumen')->orderBy('idano')->orderBy('idmes')
            ->chunk(500, function ($chunk) use (&$count, $now) {
                $batch = [];
                foreach ($chunk as $r) {
                    $batch[] = [
                        'idmes'     => $r->idmes,
                        'idano'     => $r->idano,
                        'capital'   => $r->capital ?? 0,
                        'recucapi'  => $r->recucapi ?? 0,
                        'n1'        => $r->n1 ?? 0,
                        'mensual'   => $r->mensual ?? 0,
                        'n2'        => $r->n2 ?? 0,
                        'semanal'   => $r->semanal ?? 0,
                        'n3'        => $r->n3 ?? 0,
                        'diario'    => $r->diario ?? 0,
                        'mora'      => $r->mora ?? 0,
                        'total'     => $r->total ?? 0,
                        'otros'     => $r->otros ?? 0,
                        'egreso'    => $r->egreso ?? 0,
                        'utilidad'  => $r->utilidad ?? 0,
                        'otros2'    => $r->otros2 ?? 0,
                        'egresov'   => $r->egresov ?? 0,
                        'utilidad2' => $r->utilidad2 ?? 0,
                        'fijoi'     => $r->fijoi ?? 0,
                        'otrosi'    => $r->otrosi ?? 0,
                        'fijoe'     => $r->fijoe ?? 0,
                        'otrose'    => $r->otrose ?? 0,
                        'mora1'     => $r->mora1 ?? 0,
                        'mora3'     => $r->mora3 ?? 0,
                        'mora4'     => $r->mora4 ?? 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $count++;
                }
                if (!empty($batch)) {
                    DB::table('cache_resumen_mensual')->upsert(
                        $batch, ['idmes', 'idano'],
                        ['capital','recucapi','n1','mensual','n2','semanal','n3','diario','mora','total','otros','egreso','utilidad','otros2','egresov','utilidad2','fijoi','otrosi','fijoe','otrose','mora1','mora3','mora4','updated_at']
                    );
                }
            });

        $this->info("  Resumen Mensual migrated: {$count}");
    }

    // ─── MORA TABLES (huaca_diasmora, huaca_moraacum) ──────────────
    private function migrateMoraTables(bool $fresh): void
    {
        $this->info('─── Migrating Mora Tables ───');

        if ($fresh) {
            DB::table('dias_mora')->truncate();
            DB::table('mora_acumulada')->truncate();
        }

        $now = now();

        // huaca_diasmora → dias_mora
        $count1 = 0;
        DB::connection('legacy')->table('diasmora')->orderBy('id')
            ->chunk(2000, function ($chunk) use (&$count1, $now) {
                $batch = [];
                foreach ($chunk as $r) {
                    $batch[] = [
                        'credit_id'         => (int) $r->idprestamos,
                        'dias'              => (int) ($r->diascan ?? 0),
                        'dias_descontados'  => (int) ($r->diascondenado ?? 0),
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ];
                    $count1++;
                }
                if (!empty($batch)) DB::table('dias_mora')->insert($batch);
            });
        $this->info("  dias_mora migrated: {$count1}");

        // huaca_moraacum → mora_acumulada
        $count2 = 0;
        DB::connection('legacy')->table('moraacum')->orderBy('id')
            ->chunk(2000, function ($chunk) use (&$count2, $now) {
                $batch = [];
                foreach ($chunk as $r) {
                    $batch[] = [
                        'credit_id'  => (int) $r->idcab,
                        'importe'    => $r->importe ?? 0,
                        'dias'       => (int) ($r->dias ?? 0),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $count2++;
                }
                if (!empty($batch)) DB::table('mora_acumulada')->insert($batch);
            });
        $this->info("  mora_acumulada migrated: {$count2}");
    }

    // ─── CACHE TABLES (huaca_totcaj1a, huaca_totcaj3) ──────────────
    private function migrateCacheTables(bool $fresh): void
    {
        $this->info('─── Migrating Cache Tables ───');

        if ($fresh) {
            DB::table('cache_capital_cobrado')->truncate();
            DB::table('cache_credit_totals')->truncate();
            DB::table('cache_ingreso_diario')->truncate();
            DB::table('cache_advisor_monthly')->truncate();
        }

        // huaca_reptotales → cache_advisor_monthly (cache mensual de reporte de asesores)
        $countAdv = 0;
        DB::connection('legacy')->table('reptotales')->orderBy('ano')->orderBy('mesmes')
            ->chunk(500, function ($chunk) use (&$countAdv) {
                $batch = [];
                foreach ($chunk as $r) {
                    $batch[] = [
                        'mes'       => $r->mes ?: '',
                        'xcn'       => $r->xcn ?? 0,
                        'xrc'       => $r->xrc ?? 0,
                        'canc'      => $r->canc ?? 0,
                        'total'     => $r->total ?? 0,
                        'capital'   => $r->capital ?? 0,
                        'impacobra' => $r->impacobra ?? 0,
                        'cobcnt'    => $r->cobcnt ?? 0,
                        'cobimp'    => $r->cobimp ?? 0,
                        'nocobcnt'  => $r->nocobcnt ?? 0,
                        'nocobimp'  => $r->nocobimp ?? 0,
                        'ano'       => $r->ano,
                        'mesmes'    => str_pad($r->mesmes, 2, '0', STR_PAD_LEFT),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $countAdv++;
                }
                if (!empty($batch)) {
                    DB::table('cache_advisor_monthly')->upsert($batch, ['ano', 'mesmes'],
                        ['mes','xcn','xrc','canc','total','capital','impacobra','cobcnt','cobimp','nocobcnt','nocobimp','updated_at']);
                }
            });
        $this->info("  cache_advisor_monthly migrated: {$countAdv}");

        $now = now();

        // huaca_totalesmor → cache_ingreso_diario
        $countMor = 0;
        DB::connection('legacy')->table('totalesmor')->orderBy('fecha')
            ->chunk(1000, function ($chunk) use (&$countMor, $now) {
                $batch = [];
                foreach ($chunk as $r) {
                    if (!$r->fecha || $r->fecha === '0000-00-00') continue;
                    $batch[] = [
                        'fecha' => $r->fecha,
                        'importe' => $r->importe ?? 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $countMor++;
                }
                if (!empty($batch)) {
                    DB::table('cache_ingreso_diario')->upsert($batch, ['fecha'], ['importe', 'updated_at']);
                }
            });
        $this->info("  cache_ingreso_diario migrated: {$countMor}");

        // huaca_totcaj1a → cache_capital_cobrado
        $count1 = 0;
        DB::connection('legacy')->table('totcaj1a')->orderBy('fecha')
            ->chunk(1000, function ($chunk) use (&$count1, $now) {
                $batch = [];
                foreach ($chunk as $r) {
                    if (!$r->fecha || $r->fecha === '0000-00-00') continue;
                    $batch[] = [
                        'fecha' => $r->fecha,
                        'importe' => $r->importe ?? 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $count1++;
                }
                if (!empty($batch)) {
                    DB::table('cache_capital_cobrado')->upsert($batch, ['fecha'], ['importe', 'updated_at']);
                }
            });
        $this->info("  cache_capital_cobrado migrated: {$count1}");

        // huaca_totcaj3 → cache_credit_totals
        $count3 = 0;
        DB::connection('legacy')->table('totcaj3')->orderBy('fecha')
            ->chunk(1000, function ($chunk) use (&$count3, $now) {
                $batch = [];
                foreach ($chunk as $r) {
                    if (!$r->fecha || $r->fecha === '0000-00-00') continue;
                    $batch[] = [
                        'fecha'     => $r->fecha,
                        'interes'   => $r->interes ?? 0,
                        'mora'      => $r->mora ?? 0,
                        'n_mensual' => $r->n1 ?? 0,
                        'mensual'   => $r->mensual ?? 0,
                        'n_semanal' => $r->n2 ?? 0,
                        'semanal'   => $r->semanal ?? 0,
                        'n_diario'  => $r->n3 ?? 0,
                        'diario'    => $r->diario ?? 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $count3++;
                }
                if (!empty($batch)) {
                    DB::table('cache_credit_totals')->upsert($batch, ['fecha'], ['interes', 'mora', 'n_mensual', 'mensual', 'n_semanal', 'semanal', 'n_diario', 'diario', 'updated_at']);
                }
            });
        $this->info("  cache_credit_totals migrated: {$count3}");
    }

    // ─── HELPERS ───────────────────────────────────────────────────

    /**
     * Build mapping: legacy clientes.cod → new users.id
     */
    private function buildUserMap(): array
    {
        $map = [];

        $legacyUsers = DB::connection('legacy')
            ->table('clientes')
            ->whereNotNull('user')
            ->where('user', '<>', '')
            ->get(['cod', 'user']);

        foreach ($legacyUsers as $lu) {
            $newUser = User::where('username', strtolower(trim($lu->user)))->first();
            if ($newUser) {
                $map[$lu->cod] = $newUser->id;
            }
        }

        return $map;
    }
}
