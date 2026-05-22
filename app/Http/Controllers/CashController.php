<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CashController extends Controller
{
    public function opening() { return view('cash.opening'); }
    public function incomes() { return view('cash.incomes'); }
    public function createIncome() { return view('cash.create-income'); }
    public function editIncome(int $id) { return view('cash.edit-income', compact('id')); }
    public function incomeGallery(int $id) { return view('cash.income-gallery', compact('id')); }
    public function expenses() { return view('cash.expenses'); }
    public function createExpense() { return view('cash.create-expense'); }
    public function editExpense(int $id) { return view('cash.edit-expense', compact('id')); }
    public function expenseGallery(int $id) { return view('cash.expense-gallery', compact('id')); }
    public function exportIncomes(Request $request)
    {
        $user = auth()->user();
        $export = new \App\Exports\IncomesExport(
            tipo:    (string) $request->query('tipo', '1'),
            compra:  (string) $request->query('compra', ''),
            fei:     (string) $request->query('fei', now()->format('Y-m-d')),
            fef:     (string) $request->query('fef', now()->format('Y-m-d')),
            userId:  $user?->id,
            crossHQ: $user?->can('acceso.cross-headquarter') ?? false,
            hqId:    $user?->headquarter_id ?? 1,
            editarHistorico: $user?->can('caja.editar-historico') ?? false,
        );
        return \Maatwebsite\Excel\Facades\Excel::download($export, 'ingresos-' . now()->format('Ymd-His') . '.xlsx');
    }

    public function exportExpenses(Request $request)
    {
        $user = auth()->user();
        $export = new \App\Exports\ExpensesExport(
            tipo:    (string) $request->query('tipo', '1'),
            compra:  (string) $request->query('compra', ''),
            fei:     (string) $request->query('fei', now()->format('Y-m-d')),
            fef:     (string) $request->query('fef', now()->format('Y-m-d')),
            userId:  $user?->id,
            crossHQ: $user?->can('acceso.cross-headquarter') ?? false,
            hqId:    $user?->headquarter_id ?? 1,
            editarHistorico: $user?->can('caja.editar-historico') ?? false,
        );
        return \Maatwebsite\Excel\Facades\Excel::download($export, 'egresos-' . now()->format('Ymd-His') . '.xlsx');
    }
}
