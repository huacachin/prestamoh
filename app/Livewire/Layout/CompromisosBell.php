<?php

namespace App\Livewire\Layout;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Campana del navbar: compromisos de pago próximos o vencidos (registrados en
 * el modal de notificaciones WhatsApp de /clients).
 *
 *   - ROJO:    fecha de compromiso hoy o ya vencida.
 *   - NARANJA: vence en 1 o 2 días.
 *
 * Así cobranza ve de un vistazo a quiénes tocar hoy sin entrar a /clients.
 * Un compromiso sale de la campana al marcarse cumplido (✓) o al registrarse
 * uno nuevo para el cliente.
 */
class CompromisosBell extends Component
{
    public function marcarCumplido(int $compId): void
    {
        // Analista (scope-propio): solo compromisos de SU cartera
        DB::table('compromisos_pago')
            ->where('id', $compId)
            ->when(auth()->user()?->can('clientes.scope-propio'), fn ($q) => $q->whereIn(
                'client_id', DB::table('clients')->where('asesor_id', auth()->id())->select('id')
            ))
            ->update(['cumplido_at' => now(), 'updated_at' => now()]);
    }

    public function render()
    {
        $hoy = now()->startOfDay();
        $limite = $hoy->copy()->addDays(2)->format('Y-m-d');

        // Desde compromisos_pago (varios por notificación); cada uno con su
        // propia fecha sale/entra de la campana de forma independiente.
        $compromisos = DB::table('compromisos_pago as p')
            ->join('clients as c', 'c.id', '=', 'p.client_id')
            ->whereNull('p.cumplido_at')
            ->where('p.fecha', '<=', $limite)
            ->when(auth()->user()?->can('clientes.scope-propio'), fn ($q) => $q->where('c.asesor_id', auth()->id()))
            ->orderBy('p.fecha')
            ->get([
                'p.id', 'p.fecha as compromiso_fecha', 'p.detalle as compromiso_detalle', 'p.credit_id',
                'c.id as client_id', 'c.documento', 'c.apellido_pat', 'c.apellido_mat', 'c.nombre', 'c.celular1',
            ])
            ->map(function ($r) use ($hoy) {
                $dias = $hoy->diffInDays(Carbon::parse($r->compromiso_fecha)->startOfDay(), false);
                $r->dias = (int) $dias;
                $r->estado = $dias <= 0 ? 'rojo' : 'naranja';
                $r->cliente = trim("{$r->apellido_pat} {$r->apellido_mat} {$r->nombre}");

                return $r;
            });

        return view('livewire.layout.compromisos-bell', [
            'compromisos' => $compromisos,
            'rojos' => $compromisos->where('estado', 'rojo')->count(),
            'naranjas' => $compromisos->where('estado', 'naranja')->count(),
        ]);
    }
}
