<?php

namespace App\Services\Legal;

use App\Models\Expense;
use App\Models\Income;

/**
 * Asientos automáticos de la caja del Área Legal (caja = 4).
 *
 * Las tablas incomes/expenses son compartidas con la caja operativa y tienen
 * `caja` con DEFAULT 1 (caja=1 operativa, caja=3 espejo, caja=4 Área Legal).
 * Por eso TODO create() de un asiento legal DEBE fijar 'caja' => self::CAJA
 * de forma EXPLÍCITA: si se omite, el asiento cae silenciosamente en la caja
 * operativa y contamina sus reportes (Reports/Cash y CashStatistics filtran
 * whereIn('caja', [1, 3]), y la caja legal filtra caja = 4).
 *
 * Reglas adicionales de estos asientos:
 * - NUNCA llevar mass_deletion_id: la reversa de cobros por depósito borra
 *   expenses por ese campo sin filtrar caja y arrasaría asientos legales.
 * - NUNCA llevar parent_id: no participan del espejo caja 3.
 * - Se gestionan desde su documento de origen (aviso SIGM, trámite notarial);
 *   los editores/galerías de la caja operativa abortan 403 si caja === 4.
 */
class CajaLegal
{
    /** Valor de la columna `caja` reservado para el Área Legal */
    public const CAJA = 4;

    /**
     * Registra un egreso en la caja legal (calca los campos de
     * App\Livewire\Cash\CreateExpense::save(), con caja=4 explícito).
     */
    public static function egreso(string $motivo, float $monto, string $detalle = '', ?string $fecha = null): Expense
    {
        return Expense::create([
            'date' => $fecha ?: now()->format('Y-m-d'),
            'modo' => 'Otros',
            'documento' => 'GUIA',
            'caja' => self::CAJA, // EXPLÍCITO: el default de la columna es 1 (caja operativa)
            'reason' => $motivo,
            'detail' => $detalle,
            'total' => $monto,
            'document_type' => null,
            'in_charge' => null,
            'user_id' => auth()->id(),
            'headquarter_id' => auth()->user()?->headquarter_id ?? 1,
            // Sin mass_deletion_id ni parent_id (ver docblock de la clase).
        ]);
    }

    /**
     * Registra un ingreso en la caja legal (calca los campos de
     * App\Livewire\Cash\CreateIncome::save(), con caja=4 explícito).
     */
    public static function ingreso(string $motivo, float $monto, string $detalle = '', ?string $fecha = null): Income
    {
        return Income::create([
            'date' => $fecha ?: now()->format('Y-m-d'),
            'modo' => 'Otros',
            'documento' => 'GUIA',
            'caja' => self::CAJA, // EXPLÍCITO: el default de la columna es 1 (caja operativa)
            'reason' => $motivo,
            'detail' => $detalle,
            'total' => $monto,
            'user_id' => auth()->id(),
            'headquarter_id' => auth()->user()?->headquarter_id ?? 1,
            // Sin parent_id: los asientos legales no participan del espejo caja 3.
        ]);
    }
}
