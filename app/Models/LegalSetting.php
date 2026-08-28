<?php

namespace App\Models;

use App\Support\Legal\LegalSettings;
use Illuminate\Database\Eloquent\Model;

class LegalSetting extends Model
{
    protected $table = 'legal_settings';

    protected $fillable = ['clave', 'valor', 'etiqueta', 'updated_by'];

    protected $casts = [
        'valor' => 'array',
    ];

    protected static function booted(): void
    {
        // Invalida el caché del accessor al guardar/borrar cualquier constante
        static::saved(fn () => LegalSettings::olvidar());
        static::deleted(fn () => LegalSettings::olvidar());
    }
}
