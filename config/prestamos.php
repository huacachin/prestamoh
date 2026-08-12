<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Orden de imputación de los pagos
    |--------------------------------------------------------------------------
    |
    | 'interes' — REGLA VIGENTE (decisión de negocio del 12/08/2026): todo pago
    |             se imputa INTERÉS PRIMERO, cuota por cuota en orden
    |             (interés → capital → excedente, y el resto pasa a la cuota
    |             siguiente empezando otra vez por su interés). La regla es
    |             independiente del camino: partir un cobro en varias
    |             operaciones da el mismo resultado que una sola armada.
    |
    | 'legacy'  — la regla anterior (espejo de pagossmasivo.php): capital
    |             primero salvo mensual-1-cuota y la "fase de interés".
    |             Dejar este valor en PRESTAMOS_IMPUTACION del .env para
    |             volver al comportamiento previo (rollback inmediato).
    |
    | El cambio de regla NO recalcula el historial: los pagos ya registrados
    | quedan como están; solo cambia la distribución de los cobros nuevos.
    | ADVERTENCIA asumida por el negocio: con 'interes' el desglose
    | capital/interés deja de cuadrar contra el legacy en pagos parciales
    | (el total del día y la deuda siguen cuadrando). reports:comparar-legacy
    | lo reporta como diferencia esperada, no como issue.
    |
    */

    'imputacion' => env('PRESTAMOS_IMPUTACION', 'interes'),

];
