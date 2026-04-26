<?php

namespace App\Livewire\Credits;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Create extends Component
{
    // Cliente
    public string $codigoc = '';     // DNI
    public ?string $nombreb = null;  // Nombre cliente (auto)
    public ?int $codigod = null;     // client_id (auto)

    // Crédito
    public int $codpre_;             // Código del préstamo (correlativo)
    public string $selecmoned = 'S'; // Moneda fija Soles
    public float $impopres = 0;      // Capital
    public string $selecano;         // Año
    public string $selecmes;         // Mes
    public string $seletipl = '';    // Tipo planilla (1, 3, 4)
    public int $cuot = 0;            // Cuotas
    public float $inte = 0;          // % interés
    public float $moracc = 0;        // Mora Capital (auto)
    public float $moraii = 0;        // Mora Interés (auto)
    public string $fechar;           // Fecha registro
    public string $fechad;           // Fecha préstamo
    public ?string $nomasesores = null; // Asesor (username)
    public ?string $glosa = null;
    public float $gat = 0;

    // Estado de checkboxes (auto-set por tipo)
    public bool $morai = false; // Mora Interés (mora1)
    public bool $morac = false; // Mora Capital (mora2)

    public function mount(?int $clientId = null)
    {
        $hoy = Carbon::today();
        $this->fechar = $hoy->format('Y-m-d');
        $this->fechad = $hoy->format('Y-m-d');
        $this->selecano = $hoy->format('Y');
        $this->selecmes = $hoy->format('m');

        // Próximo correlativo de crédito (legacy: huaca_tabla.correl + 1)
        $correl = (int) (DB::table('correlativos')->where('tipo', 'Credito')->value('correl') ?? 0);
        $this->codpre_ = $correl + 1;

        if ($clientId) {
            $client = Client::find($clientId);
            if ($client) {
                $this->codigoc = $client->documento;
                $this->codigod = $client->id;
                $this->nombreb = $client->fullName();
            }
        }
    }

    public function updatedCodigoc()
    {
        $client = Client::where('documento', $this->codigoc)->first();
        if ($client) {
            $this->codigod = $client->id;
            $this->nombreb = $client->fullName();
        } else {
            $this->codigod = null;
            $this->nombreb = null;
        }
    }

    public function updatedSeletipl()
    {
        // Auto-set cuotas y checkboxes según tipo (legacy diariovalida())
        if ($this->seletipl === '4') {
            $this->cuot = 30;
            $this->morai = true;  $this->morac = false;
        } elseif ($this->seletipl === '1') {
            $this->cuot = 4;
            $this->morai = false; $this->morac = true;
        } elseif ($this->seletipl === '3') {
            $this->cuot = 1;
            $this->morai = true;  $this->morac = false;
        }
        $this->recalcMora();
    }

    public function updatedImpopres() { $this->recalcMora(); }
    public function updatedInte()     { $this->recalcMora(); }
    public function updatedCuot()     { $this->recalcMora(); }

    private function recalcMora(): void
    {
        $cap = (float) $this->impopres;
        $intePct = (float) $this->inte;
        $cuotas = max(1, (int) $this->cuot);
        $interes2 = $cap * $intePct / 100;
        $tipo = $this->seletipl;

        if ($tipo === '1') {           // Semanal
            $cuota = ($cap + $interes2) / 4;
            $this->moracc = round($cuota * ($intePct * 2) / 100 / 30, 2);
            $this->moraii = round($interes2 / 30, 2);
        } elseif ($tipo === '3') {     // Mensual
            $this->moracc = round($interes2 * ($intePct * 2) / 100 / 30, 2);
            $this->moraii = round($interes2 / 30, 2);
        } elseif ($tipo === '4') {     // Diario
            $this->moracc = round($interes2 / 26, 2);
            $this->moraii = round($interes2 / 26, 2);
        } else {
            $this->moracc = 0;
            $this->moraii = 0;
        }
    }

    public function save()
    {
        $errors = [];
        if (!$this->codigoc) $errors[] = 'Ingrese DNI del cliente';
        if (!$this->codigod) $errors[] = 'El cliente no está registrado';
        if (!$this->cuot || $this->cuot <= 0) $errors[] = 'Cantidad de cuotas requerida';
        if (!$this->impopres || $this->impopres <= 0) $errors[] = 'Importe requerido';
        if (!$this->nomasesores) $errors[] = 'Indique nombre del asesor';
        if (!$this->seletipl || $this->seletipl === '0000') $errors[] = 'Seleccione tipo de planilla';

        // Validar que el código de préstamo no esté en uso
        if (DB::table('credits')->where('id', $this->codpre_)->exists()) {
            $errors[] = 'El código de préstamo ya está utilizado';
        }

        if (!empty($errors)) {
            $this->dispatch('errorAlert', ['message' => implode(' / ', $errors)]);
            return;
        }

        DB::transaction(function () {
            $pid = (int) $this->codpre_;
            $fechaBase = Carbon::parse($this->fechad);

            // Actualizar correlativo (legacy: UPDATE huaca_tabla SET correl=$pid WHERE tipo='Credito')
            DB::table('correlativos')->where('tipo', 'Credito')->update([
                'correl' => $pid,
                'updated_at' => now(),
            ]);
            $impopres = (float) $this->impopres;
            $inte = (float) $this->inte;
            $tocuota = (int) $this->cuot;
            $tipo = (int) $this->seletipl;

            // ─── 1) INSERT credit (cabecera) ───────────────────────────────
            DB::table('credits')->insert([
                'id'                  => $pid,
                'client_id'           => $this->codigod,
                'fecha_prestamo'      => $this->fechad,
                'fecha_actualizacion' => null,
                'importe'             => $impopres,
                'cuotas'              => $tocuota,
                'tipo_planilla'       => $tipo,
                'interes'             => $inte,
                'interes_total'       => 0, // se actualiza al final
                'mora'                => 0,
                'mora1'               => $this->morai ? $this->moraii : 0,
                'mora2'               => $this->morac ? $this->moracc : 0,
                'moneda'              => 'Soles',
                'documento'           => null,
                'glosa'               => $this->glosa,
                'situacion'           => 'Activo',
                'estado'              => 1,
                'refinanciado'        => 0,
                'cod_rem'             => null,
                'gat'                 => $this->gat ?? 0,
                'idcan'               => null,
                'fecha_vencimiento'   => null, // se actualiza al final
                'fecha_cancelacion'   => null,
                'asesor'              => null,
                'user_id'             => auth()->id(),
                'usuario'             => $this->nomasesores,
                'headquarter_id'      => 1,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            // ─── 2) Generar cuotas ─────────────────────────────────────────
            $selano = (int) $fechaBase->format('Y');
            $selmes = (int) $fechaBase->format('m');
            $selay  = $fechaBase->format('d');

            // Para Diario: contar días hábiles hasta cubrir 22 cuotas-base
            if ($tipo === 4) {
                $moncuo_n = round($impopres / 22, 2);
                $moncuo_n2 = 0;
                $ndiasdias = 0;
                $fechaIter = clone $fechaBase;
                for ($i = 0; $i < $tocuota + 4; $i++) {
                    $fechaIter->addDay();
                    $dow = $fechaIter->dayOfWeek;
                    if ($dow !== Carbon::SATURDAY && $dow !== Carbon::SUNDAY) {
                        $moncuo_n2 += $moncuo_n;
                    }
                    if ($impopres > $moncuo_n2) $ndiasdias++;
                }
                $ndiasdias++;
                $tocuota = $ndiasdias;
                if ($ndiasdias >= 33) $tocuota = 32;
                if ($ndiasdias === 29) $tocuota = 30;
            }

            $r = 0;
            $montototal = 0;
            $montototalinte = 0;
            $inttotal = 0;
            $fecha9 = null;
            $fechasem = clone $fechaBase;
            $anoCuota = $selano;
            $mesCuotaNum = $selmes;

            for ($i = 0; $i < $tocuota; $i++) {
                $r++;

                // Calcular monto cuota
                if ($tipo === 4) {
                    $moncuo = round($impopres / 22, 2);
                    $moncuot = round($moncuo * 22, 2);
                    $moncuott = $impopres - $moncuot;
                } else {
                    $moncuo = round($impopres / $this->cuot, 2);
                    $moncuot = round($moncuo * $this->cuot, 2);
                    $moncuott = $impopres - $moncuot;
                }

                if ($moncuott < 1) {
                    $moncuott1 = abs($moncuott);
                    $men = '0';
                } else {
                    $moncuott1 = abs($moncuott);
                    $men = '1';
                }

                // Avanzar mes (para mensual)
                $mesCuotaNum++;
                if ($mesCuotaNum > 12) {
                    $mesCuotaNum = 1;
                    $anoCuota++;
                }
                $mesCuotaStr = str_pad($mesCuotaNum, 2, '0', STR_PAD_LEFT);

                // Fecha base mensual: $ano-$mes-$día_original
                $fechaCandidata = $anoCuota . '-' . $mesCuotaStr . '-' . $selay;
                if (!checkdate($mesCuotaNum, (int) $selay, $anoCuota)) {
                    $aux = Carbon::parse("$anoCuota-$mesCuotaStr-01")->addMonth();
                    $fechaCandidata = $aux->subDay()->format('Y-m-d');
                }
                $fecha9 = $fechaCandidata;

                // Última cuota: ajustar diferencia
                if ($r === $this->cuot) {
                    $diferenciapl = $impopres - $montototal;
                    $moncuo = ($men === '1') ? $moncuo - $moncuott1 : $moncuo + $moncuott1;
                }

                // Calcular interés y fecha según tipo
                $dechal = '';
                if ($tipo === 1) {                            // Semanal
                    $inter = $impopres * ($inte / 100) / $this->cuot;
                    $fechasem->addDays(7);
                    $fecha9 = $fechasem->format('Y-m-d');
                } elseif ($tipo === 4) {                      // Diario
                    $inter2 = round($impopres * ($inte / 100), 2);
                    $inter = round($inter2 / 22, 2);
                    $fechasem->addDay();
                    $fecha9 = $fechasem->format('Y-m-d');
                    $dechal = $fechasem->dayOfWeek;
                    if ($dechal === Carbon::SATURDAY || $dechal === Carbon::SUNDAY) {
                        $inter = 0;
                        $moncuo = 0;
                    }
                } else {                                       // Mensual
                    $inter = round($impopres * ($inte / 100), 2);
                }

                $montototal += $moncuo;
                $montototalinte += $inter;

                // Última cuota Diario: ajustar diferencia interés
                if ($r === $tocuota && $tipo === 4) {
                    $diferenciapl = $impopres - $montototal;
                    $moncuo -= abs($diferenciapl);
                    $inter = round($impopres * ($inte / 100), 2);
                    $difereinte = $inter - $montototalinte;
                    $inter2 = round($impopres * ($inte / 100), 2);
                    $inter = round($inter2 / 22, 2);
                    $inter += ($difereinte > 0) ? abs($difereinte) : -abs($difereinte);
                }

                $inttotal += $inter;

                $isWeekend = ($dechal === Carbon::SATURDAY || $dechal === Carbon::SUNDAY);

                if ((int) $moncuo > 0 && $inter > 0) {
                    DB::table('credit_installments')->insert([
                        'credit_id'        => $pid,
                        'num_cuota'        => $r,
                        'fecha_vencimiento'=> $fecha9,
                        'fecha_pago'       => $fecha9,
                        'importe_cuota'    => $moncuo,
                        'importe_interes'  => $inter,
                        'importe_aplicado' => 0,
                        'interes_aplicado' => 0,
                        'importe_mora'     => 0,
                        'pagado'           => false,
                        'usuario'          => $this->nomasesores,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                }
                if ($isWeekend) {
                    DB::table('credit_installments')->insert([
                        'credit_id'        => $pid,
                        'num_cuota'        => $r,
                        'fecha_vencimiento'=> $fecha9,
                        'fecha_pago'       => $fecha9,
                        'importe_cuota'    => 0,
                        'importe_interes'  => 0,
                        'importe_aplicado' => 0,
                        'interes_aplicado' => 0,
                        'importe_mora'     => 0,
                        'pagado'           => false,
                        'usuario'          => $this->nomasesores,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                }
            }

            // ─── 3) Actualizar credit: fecha_vencimiento + interes_total ──
            DB::table('credits')->where('id', $pid)->update([
                'fecha_vencimiento' => $fecha9,
                'interes_total'     => $inttotal,
                'updated_at'        => now(),
            ]);

            // ─── 4) Diario: ajustar última cuota con interés residual ─────
            if ($tipo === 4) {
                $maxIns = DB::table('credit_installments')
                    ->where('credit_id', $pid)
                    ->where('importe_cuota', '>', 0)
                    ->orderByDesc('id')->first();
                if ($maxIns) {
                    $impInteresT = round(($impopres * $inte) / 100, 2);
                    $impInteresC = round($impInteresT / 22, 2);
                    $impInteresCu = round($impInteresC * 21, 2);
                    $impInteresUC = round($impInteresT - $impInteresCu, 2);
                    DB::table('credit_installments')->where('id', $maxIns->id)
                        ->update(['importe_interes' => $impInteresUC]);
                }
            }
        });

        session()->flash('credit_success', 'Crédito #' . $this->codpre_ . ' creado.');
        return redirect()->route('credits.show', $this->codpre_);
    }

    public function render()
    {
        $asesores = User::orderBy('name')->get(['id', 'name', 'username']);
        return view('livewire.credits.create', compact('asesores'));
    }
}
