<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CashController extends Controller
{
    public function opening() { return view('cash.opening'); }
    public function incomes() { return view('cash.incomes'); }
    public function createIncome() { return view('cash.create-income'); }
    public function editIncome(int $id) { return view('cash.edit-income', compact('id')); }
    public function expenses() { return view('cash.expenses'); }
    public function createExpense() { return view('cash.create-expense'); }
    public function editExpense(int $id) { return view('cash.edit-expense', compact('id')); }
    public function exportIncomes(Request $request) { }
    public function exportExpenses(Request $request) { }
}
