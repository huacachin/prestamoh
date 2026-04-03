<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\{
    DashboardController, ClientController, CreditController,
    PaymentController, CashController, ReportController,
    UserController, HeadquarterController, ConceptController,
    ExchangeRateController
};

// === Público ===
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('auth.index'))->name('login');
    Route::get('/', fn () => redirect()->route('login'));
});

// === Logout ===
Route::post('/logout', function (Request $request) {
    auth()->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('login');
})->name('logout');

// === Protegido ===
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    // Clientes
    Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
    Route::get('clients/{id}/edit', [ClientController::class, 'edit'])->name('clients.edit');
    Route::get('clients/{id}', [ClientController::class, 'show'])->name('clients.show');

    // Créditos
    Route::get('credits', [CreditController::class, 'index'])->name('credits.index');
    Route::get('credits/create/{clientId?}', [CreditController::class, 'create'])->name('credits.create');
    Route::get('credits/{id}', [CreditController::class, 'show'])->name('credits.show');
    Route::get('credits/{id}/schedule', [CreditController::class, 'schedule'])->name('credits.schedule');
    Route::get('credits/{id}/edit', [CreditController::class, 'edit'])->name('credits.edit');

    // Pagos
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/create/{creditId?}', [PaymentController::class, 'create'])->name('payments.create');
    Route::get('payments/daily', [PaymentController::class, 'daily'])->name('payments.daily');
    Route::get('payments/monthly', [PaymentController::class, 'monthly'])->name('payments.monthly');
    Route::get('payments/weekly', [PaymentController::class, 'weekly'])->name('payments.weekly');

    // Caja
    Route::get('cash/opening', [CashController::class, 'opening'])->name('cash.opening');
    Route::get('cash/incomes', [CashController::class, 'incomes'])->name('cash.incomes');
    Route::get('cash/incomes/create', [CashController::class, 'createIncome'])->name('cash.incomes.create');
    Route::get('cash/incomes/{id}/edit', [CashController::class, 'editIncome'])->name('cash.incomes.edit');
    Route::get('cash/expenses', [CashController::class, 'expenses'])->name('cash.expenses');
    Route::get('cash/expenses/create', [CashController::class, 'createExpense'])->name('cash.expenses.create');
    Route::get('cash/expenses/{id}/edit', [CashController::class, 'editExpense'])->name('cash.expenses.edit');
    Route::get('cash/balance', [CashController::class, 'balance'])->name('cash.balance');

    // Reportes
    Route::get('reports/portfolio', [ReportController::class, 'portfolio'])->name('reports.portfolio');
    Route::get('reports/payments', [ReportController::class, 'payments'])->name('reports.payments');
    Route::get('reports/delinquent', [ReportController::class, 'delinquent'])->name('reports.delinquent');
    Route::get('reports/cash', [ReportController::class, 'cash'])->name('reports.cash');
    Route::get('reports/simulator', [ReportController::class, 'simulator'])->name('reports.simulator');

    // Configuración - Usuarios
    Route::get('users', [UserController::class, 'index'])->name('settings.users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('settings.users.create');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('settings.users.edit');
    Route::get('users/{user}/perms', [UserController::class, 'perms'])->name('settings.users.perms');

    // Configuración - Sucursales
    Route::get('headquarters', [HeadquarterController::class, 'index'])->name('settings.headquarters.index');
    Route::get('headquarters/create', [HeadquarterController::class, 'create'])->name('settings.headquarters.create');
    Route::get('headquarters/{id}/edit', [HeadquarterController::class, 'edit'])->name('settings.headquarters.edit');

    // Configuración - Conceptos
    Route::get('concepts', [ConceptController::class, 'index'])->name('settings.concepts.index');
    Route::get('concepts/create', [ConceptController::class, 'create'])->name('settings.concepts.create');
    Route::get('concepts/{id}/edit', [ConceptController::class, 'edit'])->name('settings.concepts.edit');

    // Configuración - Tipo de Cambio
    Route::get('exchange-rates', [ExchangeRateController::class, 'index'])->name('settings.exchange-rates.index');

    // Exports
    Route::get('/exports/clients', [ClientController::class, 'export'])->name('exports.clients');
    Route::get('/exports/credits', [CreditController::class, 'export'])->name('exports.credits');
    Route::get('/exports/payments', [PaymentController::class, 'export'])->name('exports.payments');
    Route::get('/exports/incomes', [CashController::class, 'exportIncomes'])->name('exports.incomes');
    Route::get('/exports/expenses', [CashController::class, 'exportExpenses'])->name('exports.expenses');
});