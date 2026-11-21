<?php

/*
|--------------------------------------------------------------------------
| Ruta del expediente (20/11/2026)
|--------------------------------------------------------------------------
| La ficha del cliente muestra en qué paso va su expediente: Cliente →
| Vehículo → Préstamo → Documentos → SIGM → GPS → Adjuntos
| (App\Support\RutaExpediente). Solo orienta; no bloquea ningún paso.
*/

return [

    // "T. Crédito" del cliente (clients.zona) que lleva garantía sobre un vehículo: a esos préstamos la
    // ruta les pide vehículo, documentos, SIGM y GPS. A los demás (hipotecaria, alquiler-venta, sin
    // garantía, demandados…) esos pasos les salen como "No aplica", salvo que ya tengan algo registrado.
    // Se compara por el inicio del texto, sin mayúsculas ni tildes ("SIGM.S", "SIGM.M"…).
    // 20/11/2026 (Antony): también "Cred. Vehicular".
    'tipos_con_garantia_mobiliaria' => ['sigm', 'garantia mobiliaria', 'cred. vehicular'],

    // ¿Un préstamo que viene de una refinanciación pide pasos propios? Por defecto no: al 20/11/2026
    // ninguno de los préstamos refinanciados activos tenía documentos (frente a 36 de 57 de los préstamos
    // nuevos), así que la refinanciación no abre expediente: sigue siendo el del préstamo original.
    'refinanciacion_pide_documentos' => false,

    // Documentos que completan el paso, en el orden en que se emiten.
    'documentos' => ['contrato', 'anexo1', 'anexo2', 'djprop', 'djpubli'],

    /*
    | A qué préstamo pertenece cada foto, vehículo y cuenta GPS (20/11/2026, Antony: con un préstamo
    | nuevo, Vehículo, Adjuntos y GPS se vuelven a pedir). No llevan el préstamo anotado: se deduce de
    | cuándo se registraron — pertenecen al préstamo vigente en ese momento (App\Support\RutaExpediente).
    */

    // Lo registrado hasta estos días ANTES de crear un préstamo cuenta para ese préstamo (el cliente
    // trae sus papeles y el préstamo se registra después), no para el anterior.
    'dias_previos' => 7,

    // Lo sellado hasta esta fecha vino del sistema anterior (la migración lo fechó todo el 01/09/2026):
    // no tiene fecha real y pertenece a los préstamos antiguos, nunca a uno nuevo.
    'migrado_hasta' => '2026-09-01 23:59:59',

];
