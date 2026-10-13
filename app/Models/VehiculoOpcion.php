<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Opción de los catálogos del vehículo (13/10/2026): categoría, carrocería,
 * color o combustible. Crece sola con lo que se escribe en los formularios
 * (ver App\Support\VehiculoCatalogos).
 */
class VehiculoOpcion extends Model
{
    protected $table = 'vehiculo_opciones';

    protected $fillable = ['tipo', 'valor'];
}
