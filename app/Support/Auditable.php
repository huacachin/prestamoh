<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Auditoría automática por modelo (25/09, traída de newtaxivan sobre
 * spatie/activitylog): cada creación, edición y borrado del modelo deja un
 * registro en el log 'auditoria' con el evento, la fila (creación/borrado) o
 * solo los campos que cambiaron con su valor anterior y nuevo (edición), y el
 * contexto (IP, navegador, usuario y rol) que añade GuardarActividad.
 *
 * El modelo puede declarar:
 *   const AUDIT_MODULO     = 'Cliente';            // etiqueta en el visor (por defecto, el nombre de la clase)
 *   const AUDIT_EXCLUIR    = ['remember_token'];   // columnas que no se guardan
 *   const AUDIT_ENMASCARAR = ['password'];         // columnas que se guardan como ••••••
 *   public function auditNombre(): ?string         // texto corto para la descripción ("Creó Cliente #12 (PEREZ ROSA)")
 *
 * Las escrituras que no pasan por Eloquent (DB::table, updates masivos) no
 * generan registro; para eso sigue estando Audit::log.
 */
trait Auditable
{
    use LogsActivity;

    public const AUDIT_MASCARA = '••••••';

    public function getActivitylogOptions(): LogOptions
    {
        $excluir = array_merge(['created_at', 'updated_at', 'remember_token'], static::auditConstante('AUDIT_EXCLUIR', []));

        return LogOptions::defaults()
            ->useLogName(Audit::LOG)
            ->logAll()
            ->logExcept($excluir)
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $evento) => $this->descripcionAuditoria($evento));
    }

    /** "Creó Cliente #12 (PEREZ ROSA)", "Editó Crédito #28603", "Eliminó Ingreso #5". */
    public function descripcionAuditoria(string $evento): string
    {
        $verbo = match ($evento) {
            'created' => 'Creó',
            'updated' => 'Editó',
            'deleted' => 'Eliminó',
            'restored' => 'Restauró',
            default => ucfirst($evento),
        };
        $nombre = method_exists($this, 'auditNombre') ? trim((string) $this->auditNombre()) : '';

        return trim("{$verbo} ".static::auditModulo()." #{$this->getKey()}".($nombre !== '' ? " ({$nombre})" : ''));
    }

    /**
     * Ejecuta un guardado SIN la fila automática, para cuando el código ya deja
     * un Audit::log con el verbo de negocio (Anuló, Desactivó, Generó…) y la fila
     * "Editó X" saldría duplicada (26/09).
     */
    public function sinAuditoriaAutomatica(callable $fn): mixed
    {
        $this->disableLogging();
        try {
            return $fn();
        } finally {
            $this->enableLogging();
        }
    }

    public static function auditModulo(): string
    {
        return static::auditConstante('AUDIT_MODULO', class_basename(static::class));
    }

    /**
     * Hook de spatie justo antes de guardar: deja solo los cambios reales y
     * enmascara las columnas sensibles.
     */
    public function beforeActivityLogged(Model $activity, string $evento): void
    {
        if (empty($activity->attribute_changes)) {
            return;
        }
        $cambios = collect($activity->attribute_changes)->toArray();

        // En una edición, spatie compara contra lo que el modelo tenía en memoria:
        // si la instancia se creó en la misma petición, los valores por defecto de
        // la tabla salen como "cambios" (null → valor). Los cambios reales son los
        // que Eloquent acaba de guardar.
        if ($evento === 'updated' && isset($cambios['attributes'])) {
            $reales = array_keys($this->getChanges());
            $filtrados = array_intersect_key($cambios['attributes'], array_flip($reales));
            if (! empty($filtrados)) {
                $cambios['attributes'] = $filtrados;
                $cambios['old'] = array_intersect_key($cambios['old'] ?? [], $filtrados);
            }
        }

        $enmascarar = static::auditConstante('AUDIT_ENMASCARAR', []);
        foreach (['attributes', 'old'] as $bloque) {
            foreach ($enmascarar as $col) {
                if (array_key_exists($col, $cambios[$bloque] ?? []) && $cambios[$bloque][$col] !== null) {
                    $cambios[$bloque][$col] = self::AUDIT_MASCARA;
                }
            }
        }
        $activity->attribute_changes = $cambios;
    }

    private static function auditConstante(string $nombre, mixed $porDefecto): mixed
    {
        return defined(static::class.'::'.$nombre) ? constant(static::class.'::'.$nombre) : $porDefecto;
    }
}
