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
    Route::get('clients/ceased', [ClientController::class, 'ceased'])->name('clients.ceased')->middleware('permission:registro.cesados');
    Route::middleware('permission:clientes')->group(function () {
        Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
        Route::get('clients/{id}/edit', [ClientController::class, 'edit'])->name('clients.edit');
        Route::get('clients/{id}', [ClientController::class, 'show'])->name('clients.show');
    });

    // Créditos
    Route::get('credits/activate', [CreditController::class, 'activate'])->name('credits.activate')->middleware('permission:registro.activar');
    Route::get('credits/change-status', [CreditController::class, 'changeStatus'])->name('credits.change-status')->middleware('permission:registro.estado');
    Route::get('credits/mass-delete', [CreditController::class, 'massDelete'])->name('credits.mass-delete')->middleware('permission:registro.eliminar-masivo');
    Route::middleware('permission:creditos')->group(function () {
        Route::get('credits', [CreditController::class, 'index'])->name('credits.index');
        Route::get('credits/create/{clientId?}', [CreditController::class, 'create'])->name('credits.create');
        Route::get('credits/{id}', [CreditController::class, 'show'])->name('credits.show');
        Route::get('credits/{id}/schedule', [CreditController::class, 'schedule'])->name('credits.schedule');
        Route::get('credits/{id}/edit', [CreditController::class, 'edit'])->name('credits.edit');
    });

    // Pagos
    Route::middleware('permission:pagos')->group(function () {
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/create/{creditId?}', [PaymentController::class, 'create'])->name('payments.create');
        Route::get('payments/daily', [PaymentController::class, 'daily'])->name('payments.daily');
        Route::get('payments/monthly', [PaymentController::class, 'monthly'])->name('payments.monthly');
        Route::get('payments/weekly', [PaymentController::class, 'weekly'])->name('payments.weekly');
    });

    // Caja
    Route::get('cash/opening', [CashController::class, 'opening'])->name('cash.opening')->middleware('permission:caja.apertura');
    Route::get('cash/incomes', [CashController::class, 'incomes'])->name('cash.incomes')->middleware('permission:caja.ingresos');
    Route::get('cash/incomes/create', [CashController::class, 'createIncome'])->name('cash.incomes.create')->middleware('permission:caja.ingresos');
    Route::get('cash/incomes/{id}/edit', [CashController::class, 'editIncome'])->name('cash.incomes.edit')->middleware('permission:caja.ingresos');
    Route::get('cash/expenses', [CashController::class, 'expenses'])->name('cash.expenses')->middleware('permission:caja.egresos');
    Route::get('cash/expenses/create', [CashController::class, 'createExpense'])->name('cash.expenses.create')->middleware('permission:caja.egresos');
    Route::get('cash/expenses/{id}/edit', [CashController::class, 'editExpense'])->name('cash.expenses.edit')->middleware('permission:caja.egresos');

    // Reportes
    Route::get('reports/portfolio', [ReportController::class, 'portfolio'])->name('reports.portfolio')->middleware('permission:reportes.cartera');
    Route::get('reports/payments', [ReportController::class, 'payments'])->name('reports.payments')->middleware('permission:reportes.pagos');
    Route::get('reports/delinquent', [ReportController::class, 'delinquent'])->name('reports.delinquent')->middleware('permission:reportes.morosidad');
    Route::get('reports/cash', [ReportController::class, 'cash'])->name('reports.cash')->middleware('permission:reportes.caja');
    Route::get('reports/advisor', [ReportController::class, 'advisor'])->name('reports.advisor')->middleware('permission:reportes.asesor');
    Route::get('reports/cash-statistics', [ReportController::class, 'cashStatistics'])->name('reports.cash-statistics')->middleware('permission:reportes.caja-estadistica');
    Route::get('reports/credit-statistics', [ReportController::class, 'creditStatistics'])->name('reports.credit-statistics')->middleware('permission:reportes.credito-estadistica');
    Route::get('reports/cash-general-1', [ReportController::class, 'cashGeneral1'])->name('reports.cash-general-1')->middleware('permission:reportes.caja-general-1');
    Route::get('reports/cash-general-2', [ReportController::class, 'cashGeneral2'])->name('reports.cash-general-2')->middleware('permission:reportes.caja-general-2');
    Route::get('reports/cash-general-3', [ReportController::class, 'cashGeneral3'])->name('reports.cash-general-3')->middleware('permission:reportes.caja-general-3');
    Route::get('reports/cancelled', [ReportController::class, 'cancelled'])->name('reports.cancelled')->middleware('permission:reportes.cancelados');
    Route::get('reports/simulator', [ReportController::class, 'simulator'])->name('reports.simulator')->middleware('permission:reportes.simulador');

    // Configuración - Usuarios
    Route::middleware('permission:configuracion.usuarios')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('settings.users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('settings.users.create');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('settings.users.edit');
        Route::get('users/{user}/perms', [UserController::class, 'perms'])->name('settings.users.perms');
    });

    // Configuración - Sucursales
    Route::middleware('permission:configuracion.sucursales')->group(function () {
        Route::get('headquarters', [HeadquarterController::class, 'index'])->name('settings.headquarters.index');
        Route::get('headquarters/create', [HeadquarterController::class, 'create'])->name('settings.headquarters.create');
        Route::get('headquarters/{id}/edit', [HeadquarterController::class, 'edit'])->name('settings.headquarters.edit');
    });

    // Configuración - Conceptos
    Route::middleware('permission:configuracion.conceptos')->group(function () {
        Route::get('concepts', [ConceptController::class, 'index'])->name('settings.concepts.index');
        Route::get('concepts/create', [ConceptController::class, 'create'])->name('settings.concepts.create');
        Route::get('concepts/{id}/edit', [ConceptController::class, 'edit'])->name('settings.concepts.edit');
    });

    // Configuración - Tipo de Cambio
    Route::get('exchange-rates', [ExchangeRateController::class, 'index'])->name('settings.exchange-rates.index')->middleware('permission:configuracion.tipo-cambio');

    // Exports
    Route::get('/exports/clients', [ClientController::class, 'export'])->name('exports.clients');
    Route::get('/exports/credits', [CreditController::class, 'export'])->name('exports.credits');
    Route::get('/exports/payments', [PaymentController::class, 'export'])->name('exports.payments');
    Route::get('/exports/incomes', [CashController::class, 'exportIncomes'])->name('exports.incomes');
    Route::get('/exports/expenses', [CashController::class, 'exportExpenses'])->name('exports.expenses');
});
