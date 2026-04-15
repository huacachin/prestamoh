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
                            {--step= : Run a specific step (headquarters,users,clients,credits,installments,payments,incomes,expenses,cash,concepts,exchange,mass)}
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
            'expenses'     => 'migrateExpenses',
            'cash'         => 'migrateCashOpenings',
            'concepts'     => 'migrateConcepts',
            'exchange'     => 'migrateExchangeRates',
            'mass'         => 'migrateMassDeletions',
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

            $user = User::updateOrCreate(
                ['username' => strtolower($username)],
                [
                    'name'            => $lu->nombre ?? $username,
                    'email'           => $lu->email ?: strtolower($username) . '@prestamos.local',
                    'password'        => Hash::make($lu->clave ?: 'password123'),
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
        foreach ($legacyClients as $lc) {
            $asesorId = $userMap[$lc->ccodrelacion] ?? null;

            Client::updateOrCreate(
                ['id' => $lc->id],
                [
                    'expediente'          => $lc->ccodpersona ?: null,
                    'nombre'              => $lc->nombre ?: $lc->cnompersona,
                    'apellido_pat'        => $lc->apellidopat ?: null,
                    'apellido_mat'        => $lc->apellidomat ?: null,
                    'tipo_documento'      => 'DNI',
                    'documento'           => $lc->cdnipersona ?: null,
                    'fecha_nacimiento'    => ($lc->dnacpersona && $lc->dnacpersona !== '0000-00-00') ? $lc->dnacpersona : null,
                    'sexo'                => ($lc->csexpersona === 'M' || $lc->csexpersona === 'F') ? $lc->csexpersona : null,
                    'email'               => $lc->cemapersona ?: null,
                    'telefono_fijo'       => $lc->ntelefono ?: null,
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
                    'asesor_id'           => $asesorId,
                    'headquarter_id'      => 1,
                    'status'              => $lc->cestpersona == '1' ? 'active' : 'inactive',
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

        $count = 0;
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
            $clientExists = Client::where('id', $lc->ccodpersona)->exists();
            if (!$clientExists) {
                $bar->advance();
                continue;
            }

            $asesorId = $userMap[$lc->nomasesor ?? ''] ?? null;
            // Try user field
            if (!$asesorId && $lc->user) {
                $asesorId = User::where('username', strtolower($lc->user))->value('id');
            }

            Credit::updateOrCreate(
                ['id' => $lc->id],
                [
                    'client_id'          => $lc->ccodpersona,
                    'fecha_prestamo'     => ($lc->fechaprestamo && $lc->fechaprestamo !== '0000-00-00') ? $lc->fechaprestamo : now()->format('Y-m-d'),
                    'importe'            => $lc->importe ?? 0,
                    'cuotas'             => $lc->cuotas ?? 1,
                    'tipo_planilla'      => $lc->tipoplani ?? 3,
                    'interes'            => $lc->interes ?? 0,
                    'interes_total'      => $lc->interestot ?? 0,
                    'mora'               => $lc->mora ?? 0,
                    'moneda'             => 'Soles',
                    'documento'          => $lc->documento ?: null,
                    'glosa'              => $lc->glosa ?: null,
                    'situacion'          => $newSituacion,
                    'estado'             => $lc->estado ?? 1,
                    'refinanciado'       => ($lc->refi == 1 || $lc->cod_rem === 'REF'),
                    'fecha_vencimiento'  => ($lc->fechafin && $lc->fechafin !== '0000-00-00') ? $lc->fechafin : null,
                    'fecha_cancelacion'  => ($lc->fechacan && $lc->fechacan !== '0000-00-00') ? $lc->fechacan : null,
                    'asesor'             => $lc->nomasesor ?: null,
                    'user_id'            => $asesorId,
                    'headquarter_id'     => 1,
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

                    $batch[] = [
                        'credit_id'      => (int)$creditId,
                        'installment_id' => null,
                        'modo'           => $lp->modo ?: 'CREDITO',
                        'tipo'           => $tipo,
                        'documento'      => $lp->documento ?: null,
                        'nro_recibo'     => $lp->coditem ?: null,
                        'fecha'          => ($lp->fechaentrada && $lp->fechaentrada !== '0000-00-00') ? $lp->fechaentrada : now()->format('Y-m-d'),
                        'monto'          => $lp->totalgeneral ?? 0,
                        'moneda'         => 'Soles',
                        'tipo_cambio'    => ($lp->cambio && $lp->cambio > 0) ? $lp->cambio : null,
                        'detalle'        => $lp->detalle ?: null,
                        'asesor'         => $lp->asesor ?: null,
                        'user_id'        => null,
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

        $total = DB::connection('legacy')->table('ingreso')->where('modo', '<>', 'CREDITO')->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $count = 0;
        DB::connection('legacy')
            ->table('ingreso')
            ->where('modo', '<>', 'CREDITO')
            ->orderBy('identrada')
            ->chunk(1000, function ($chunk) use (&$count, $bar) {
                $batch = [];
                foreach ($chunk as $li) {
                    $bar->advance();
                    $batch[] = [
                        'date'           => ($li->fechaentrada && $li->fechaentrada !== '0000-00-00') ? $li->fechaentrada : now()->format('Y-m-d'),
                        'reason'         => $li->aa ?: ($li->modo ?: 'Otros'),
                        'detail'         => mb_substr($li->detalle ?: '', 0, 255),
                        'total'          => $li->totalgeneral ?? 0,
                        'user_id'        => null,
                        'headquarter_id' => 1,
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

    // ─── EXPENSES ──────────────────────────────────────────────────
    private function migrateExpenses(bool $fresh): void
    {
        $this->info('─── Migrating Expenses ───');

        if ($fresh) {
            Schema::disableForeignKeyConstraints();
            Expense::truncate();
            Schema::enableForeignKeyConstraints();
        }

        $total = DB::connection('legacy')->table('entrada')->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $count = 0;
        DB::connection('legacy')
            ->table('entrada')
            ->orderBy('identrada')
            ->chunk(1000, function ($chunk) use (&$count, $bar) {
                $batch = [];
                foreach ($chunk as $le) {
                    $bar->advance();
                    $batch[] = [
                        'date'           => ($le->fechaentrada && $le->fechaentrada !== '0000-00-00') ? $le->fechaentrada : now()->format('Y-m-d'),
                        'reason'         => $le->aa ?: ($le->modo ?: 'Otros'),
                        'detail'         => mb_substr($le->detalle ?: '', 0, 255),
                        'total'          => $le->totalgeneral ?? 0,
                        'document_type'  => $le->tipcom ?: null,
                        'in_charge'      => $le->respons ?: null,
                        'user_id'        => null,
                        'headquarter_id' => 1,
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

        $count = 0;
        foreach ($legacyCash as $lc) {
            CashOpening::create([
                'fecha'          => ($lc->fecha && $lc->fecha !== '0000-00-00') ? $lc->fecha : now()->format('Y-m-d'),
                'saldo_inicial'  => $lc->importe ?? 0,
                'saldo_final'    => 0,
                'estado'         => 'cerrado',
                'user_id'        => null,
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
                    'name'   => $lc->cnombrees ?: 'Sin nombre',
                    'type'   => ($lc->ctipocon == '1') ? 'Ingreso' : 'Egreso',
                    'status' => ($lc->cestparametro == 1) ? 'active' : 'inactive',
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
            MassDeletion::truncate();
            Schema::enableForeignKeyConstraints();
        }

        $validCredits = Credit::pluck('id')->flip();

        $legacyMass = DB::connection('legacy')
            ->table('cab_masivo')
            ->get();

        $count = 0;
        foreach ($legacyMass as $lm) {
            MassDeletion::create([
                'credit_id'    => isset($validCredits[$lm->codpres]) ? $lm->codpres : null,
                'amount'       => $lm->monto ?? 0,
                'date'         => ($lm->fecha && $lm->fecha !== '0000-00-00') ? $lm->fecha : now()->format('Y-m-d'),
                'time'         => $lm->hora ?: '00:00:00',
                'user'         => null,
                'advisor'      => $lm->asesor ?: null,
                'performed_by' => $lm->usuario2 ?: null,
            ]);
            $count++;
        }

        $this->info("  Mass Deletions migrated: {$count}");
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
