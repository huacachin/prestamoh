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
    public function marcarCumplido(int $notifId): void
    {
        DB::table('client_notifications')
            ->where('id', $notifId)
            ->whereNotNull('compromiso_fecha')
            ->update(['compromiso_cumplido_at' => now(), 'updated_at' => now()]);
    }

    public function render()
    {
        $hoy = now()->startOfDay();
        $limite = $hoy->copy()->addDays(2)->format('Y-m-d');

        $compromisos = DB::table('client_notifications as n')
            ->join('clients as c', 'c.id', '=', 'n.client_id')
            ->whereNotNull('n.compromiso_fecha')
            ->whereNull('n.compromiso_cumplido_at')
            ->where('n.compromiso_fecha', '<=', $limite)
            ->orderBy('n.compromiso_fecha')
            ->get([
                'n.id', 'n.compromiso_fecha', 'n.compromiso_detalle',
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
