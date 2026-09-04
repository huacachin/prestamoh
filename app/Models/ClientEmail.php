<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Correos del cliente. Invariante: como máximo UN principal por cliente y
 * `clients.email` es siempre su espejo (lo que leen contratos y exports).
 * Cualquier escritura de correos debe terminar llamando a espejar().
 */
class ClientEmail extends Model
{
    protected $fillable = ['client_id', 'email', 'principal'];

    protected $casts = ['principal' => 'boolean'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Restaura el invariante para un cliente: garantiza un único principal
     * (si no hay ninguno, promueve el más antiguo) y espeja `clients.email`.
     */
    public static function espejar(int $clientId): void
    {
        $principal = DB::table('client_emails')
            ->where('client_id', $clientId)->where('principal', 1)
            ->orderBy('id')->first();

        if ($principal === null) {
            $principal = DB::table('client_emails')
                ->where('client_id', $clientId)->orderBy('id')->first();
            if ($principal !== null) {
                DB::table('client_emails')->where('id', $principal->id)->update(['principal' => 1, 'updated_at' => now()]);
            }
        }

        // Un solo principal: cualquier otro marcado se apaga.
        DB::table('client_emails')->where('client_id', $clientId)
            ->when($principal !== null, fn ($q) => $q->where('id', '<>', $principal->id))
            ->where('principal', 1)->update(['principal' => 0, 'updated_at' => now()]);

        DB::table('clients')->where('id', $clientId)
            ->update(['email' => $principal->email ?? null, 'updated_at' => now()]);
    }
}
