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

    /*
    |--------------------------------------------------------------------------
    | Ubicación obligatoria al cobrar (22/10/2026, pedido de Antony)
    |--------------------------------------------------------------------------
    |
    | Cada pago guarda la latitud/longitud del navegador de quien lo registra
    | (payments.latitud/longitud, como el legacy) y la columna Map del reporte
    | de pagos la enlaza. Con true, sin ubicación el cobro se rechaza; con
    | false solo se guarda si el navegador la entrega. Los tests lo apagan
    | (phpunit.xml) salvo los que prueban la validación.
    |
    */

    'ubicacion_obligatoria' => (bool) env('COBRO_UBICACION_OBLIGATORIA', true),

];
