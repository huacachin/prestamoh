<?php

namespace App\Livewire\Layout;

use App\Models\Garantia;
use App\Models\Papeleta;
use App\Models\PapeletaRecurso;
use App\Models\PlazoJudicial;
use App\Models\TramiteNotarial;
use App\Models\Vehiculo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Campana del navbar del Área Legal: alertas derivadas de los propios datos
 * (self-heal, SIN tabla de alertas — al corregirse la causa la alerta
 * desaparece sola en el siguiente poll).
 *
 * Fuentes actuales:
 *   - RENOVACIONES SIGM: garantías vigentes con vigencia vencida o por vencer
 *     (Garantia::porRenovar). ROJO si ya venció, NARANJA si vence en <= 7 días.
 *   - NOTARÍA VARADA: trámites abiertos demasiados días en el mismo estado
 *     (TramiteNotarial::varados). NARANJA a los 15 días, ROJO a los 30.
 *   - PLAZOS JUDICIALES: plazos pendientes de expedientes que ya vencieron o
 *     vencen en <= PlazoJudicial::DIAS_AVISO días (PlazoJudicial::porVencer).
 *     ROJO si ya venció, NARANJA si vence hoy o pronto.
 *   - RECURSOS DE PAPELETAS: recursos pendientes cuyo plazo ya venció o vence
 *     en <= PapeletaRecurso::DIAS_AVISO días (PapeletaRecurso::porVencer).
 *     ROJO si ya venció, NARANJA si vence hoy o pronto.
 *   - VENCIMIENTOS DE FLOTA: SOAT / revisión técnica / habilitación ATU de
 *     vehículos ACTIVOS de empresa o tercero, vencidos o por vencer en
 *     <= 15 días (un ítem por documento). ROJO si ya venció, NARANJA si no.
 *
 * Cada fuente es un método privado que devuelve ítems con la misma forma
 * (clase, prioridad, orden, icono, titulo, detalle, url); render() las
 * concatena y ordena: rojos primero, luego naranjas, por antigüedad.
 */
class LegalBell extends Component
{
    public function render()
    {
        $items = collect();

        // Defensivo: el header ya envuelve esta campana en @can('legal.garantias'),
        // pero si se incluyera en otro layout no debe filtrar datos.
        if (auth()->user()?->can('legal.garantias')) {
            $items = $this->renovaciones()
                ->concat($this->notariaVarada())
                ->concat($this->plazosJudiciales())
                ->concat($this->recursosPapeletas())
                ->concat($this->vencimientosFlota())
                ->sortBy([['prioridad', 'asc'], ['orden', 'asc']])
                ->values();
        }

        return view('livewire.layout.legal-bell', [
            'items' => $items->take(30),
            'total' => $items->count(),
            'rojos' => $items->where('clase', 'rojo')->count(),
            'naranjas' => $items->where('clase', 'naranja')->count(),
        ]);
    }

    /** Garantías SIGM vigentes vencidas o por vencer (aviso de renovación) */
    private function renovaciones(): Collection
    {
        $hoy = now()->startOfDay();

        return Garantia::porRenovar()
            ->with(['client', 'vehiculos'])
            ->get()
            ->map(function (Garantia $g) use ($hoy) {
                // Negativo = vencida hace N días; 0 = vence hoy; positivo = vence en N días
                $dias = (int) $hoy->diffInDays($g->vigencia_hasta->startOfDay(), false);
                $placas = $g->vehiculos->pluck('placa')->filter()->implode(', ');

                return (object) [
                    'clase' => $dias < 0 ? 'rojo' : 'naranja',
                    'prioridad' => $dias < 0 ? 0 : 1,
                    'orden' => $dias, // la más vencida / más próxima primero
                    'icono' => 'ti-refresh-alert',
                    'titulo' => 'Renovar aviso SIGM — '.($g->client?->fullName() ?: 'Cliente s/d')
                        .($placas !== '' ? " ({$placas})" : ''),
                    'detalle' => match (true) {
                        $dias < 0 => 'VENCIDA hace '.abs($dias).'d · venció el '.$g->vigencia_hasta->format('d/m/Y'),
                        $dias === 0 => 'Vence HOY '.$g->vigencia_hasta->format('d/m/Y'),
                        default => 'Vence el '.$g->vigencia_hasta->format('d/m/Y').' · en '.$dias.'d',
                    },
                    'url' => route('legal.garantias.show', $g->id),
                ];
            });
    }

    /** Trámites notariales abiertos varados en su estado actual */
    private function notariaVarada(): Collection
    {
        return TramiteNotarial::varados()
            ->with(['client', 'garantia.client'])
            ->get()
            ->map(function (TramiteNotarial $t) {
                $dias = $t->diasEnEstado();
                $quien = $t->client?->fullName()
                    ?: $t->garantia?->client?->fullName()
                    ?: ($t->descripcion ?: 'Sin descripción');

                return (object) [
                    'clase' => $dias >= 2 * TramiteNotarial::DIAS_VARADO ? 'rojo' : 'naranja',
                    'prioridad' => $dias >= 2 * TramiteNotarial::DIAS_VARADO ? 0 : 1,
                    'orden' => -$dias, // el más varado primero
                    'icono' => 'ti-file-certificate',
                    'titulo' => 'Trámite notarial '.(TramiteNotarial::TIPOS[$t->tipo] ?? $t->tipo)." — {$quien}",
                    'detalle' => $dias.' días en '.(TramiteNotarial::ESTADOS[$t->estado] ?? $t->estado),
                    'url' => route('legal.notaria'),
                ];
            });
    }

    /** Plazos judiciales pendientes ya vencidos o por vencer (expedientes) */
    private function plazosJudiciales(): Collection
    {
        $hoy = now()->startOfDay();

        return PlazoJudicial::porVencer()
            ->with('expediente.client', 'expediente')
            ->get()
            ->map(function (PlazoJudicial $p) use ($hoy) {
                // Negativo = venció hace N días; 0 = vence hoy; positivo = vence en N días
                $dias = (int) $hoy->diffInDays($p->fecha_vencimiento->startOfDay(), false);
                $quien = $p->expediente?->client?->fullName()
                    ?: ($p->expediente?->nro_expediente ?? 'Expediente s/d');

                return (object) [
                    'clase' => $dias < 0 ? 'rojo' : 'naranja',
                    'prioridad' => $dias < 0 ? 0 : 1,
                    'orden' => $dias, // el más vencido / más próximo primero
                    'icono' => 'ti-clock-exclamation',
                    'titulo' => 'Plazo judicial — '.$quien,
                    'detalle' => Str::limit($p->descripcion, 60).': '.match (true) {
                        $dias < 0 => 'VENCIDO hace '.abs($dias).'d · venció el '.$p->fecha_vencimiento->format('d/m/Y'),
                        $dias === 0 => 'vence HOY '.$p->fecha_vencimiento->format('d/m/Y'),
                        default => 'vence '.$p->fecha_vencimiento->format('d/m/Y'),
                    },
                    'url' => route('legal.expedientes.show', $p->expediente_id),
                ];
            });
    }

    /** Recursos de papeletas pendientes ya vencidos o por vencer (plazo legal) */
    private function recursosPapeletas(): Collection
    {
        $hoy = now()->startOfDay();

        return PapeletaRecurso::porVencer()
            ->with('papeleta.vehiculo')
            ->get()
            ->map(function (PapeletaRecurso $r) use ($hoy) {
                // Negativo = venció hace N días; 0 = vence hoy; positivo = vence en N días
                $dias = (int) $hoy->diffInDays($r->plazo_vence->startOfDay(), false);
                $papeleta = $r->papeleta;
                $placa = $papeleta?->vehiculo?->placa;

                return (object) [
                    'clase' => $dias < 0 ? 'rojo' : 'naranja',
                    'prioridad' => $dias < 0 ? 0 : 1,
                    'orden' => $dias, // el más vencido / más próximo primero
                    'icono' => 'ti-file-alert',
                    'titulo' => 'Recurso de papeleta — '
                        .(Papeleta::ENTIDADES[$papeleta?->entidad] ?? $papeleta?->entidad)
                        .' '.$papeleta?->nro_papeleta
                        .($placa ? " ({$placa})" : ''),
                    'detalle' => (PapeletaRecurso::TIPOS[$r->tipo] ?? $r->tipo)
                        .': vence '.$r->plazo_vence->format('d/m/Y'),
                    'url' => route('legal.papeletas'),
                ];
            });
    }

    /** SOAT / revisión técnica / habilitación ATU de la flota activa (empresa o tercero) */
    private function vencimientosFlota(): Collection
    {
        $hoy = now()->startOfDay();
        $limite = $hoy->copy()->addDays(15)->toDateString();

        return Vehiculo::where('estado', 'activo')
            ->whereIn('propietario_tipo', ['empresa', 'tercero'])
            ->where(function ($q) use ($limite) {
                foreach (array_keys(Vehiculo::VENCIMIENTOS) as $campo) {
                    $q->orWhere($campo, '<=', $limite);
                }
            })
            ->get()
            ->flatMap(function (Vehiculo $v) use ($hoy) {
                // Un ítem POR DOCUMENTO vencido o por vencer, no por vehículo
                $items = collect();

                foreach (Vehiculo::VENCIMIENTOS as $campo => $label) {
                    $fecha = $v->{$campo};
                    if (! $fecha) {
                        continue;
                    }

                    // Negativo = venció hace N días; 0 = vence hoy; positivo = vence en N días
                    $dias = (int) $hoy->diffInDays($fecha->startOfDay(), false);
                    if ($dias > 15) {
                        continue;
                    }

                    $items->push((object) [
                        'clase' => $dias < 0 ? 'rojo' : 'naranja',
                        'prioridad' => $dias < 0 ? 0 : 1,
                        'orden' => $dias, // el más vencido / más próximo primero
                        'icono' => 'ti-car',
                        'titulo' => "{$label} — {$v->placa}",
                        'detalle' => $dias < 0
                            ? 'VENCIDO hace '.abs($dias).'d'
                            : 'vence '.$fecha->format('d/m/Y'),
                        'url' => route('legal.vehiculos'),
                    ]);
                }

                return $items;
            });
    }
}
