<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ConceptController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentoClienteController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\HeadquarterController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShortLinkController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// === Público ===
// Route::view / Route::redirect en vez de closures: route:cache no admite
// rutas closure y estas dos lo eran.
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.index')->name('login');
    Route::redirect('/', '/login');
});

// Recibo público: el cliente lo abre desde el link de WhatsApp, sin login.
// Protegido por firma criptográfica (URL::signedRoute) — un id alterado o
// sin firma válida devuelve 403, así nadie puede enumerar recibos ajenos.
Route::middleware('signed')->group(function () {
    Route::get('recibo/{massDeletionId}', [PaymentController::class, 'reciboPublico'])->name('recibo.publico');
    Route::get('recibo/{massDeletionId}/pdf', [PaymentController::class, 'reciboPdf'])->name('recibo.pdf');
});

// Acortador propio: /s/{code} → redirige al destino guardado (links de recibo
// para WhatsApp). Ver ShortLinkController.
Route::get('s/{code}', ShortLinkController::class)->name('short-link');

// === Logout ===
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// === Protegido ===
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    // Auditoría (solo rol director)
    Route::view('audit', 'audit.index')->name('audit.index')->middleware('role:director');

    // Clientes
    Route::get('clients/ceased', [ClientController::class, 'ceased'])->name('clients.ceased')->middleware('permission:registro.cesados');
    Route::middleware('permission:clientes')->group(function () {
        Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
        Route::get('clients/{id}/edit', [ClientController::class, 'edit'])->name('clients.edit');
        Route::get('clients/{id}/gallery', [ClientController::class, 'gallery'])->name('clients.gallery');
        Route::get('clients/{id}/aval', [ClientController::class, 'aval'])->name('clients.aval');
        Route::get('clients/{id}/documentos', [ClientController::class, 'documentos'])->name('clients.documentos');
        Route::get('clients/documentos/{id}/pdf', [DocumentoClienteController::class, 'pdf'])->name('clients.documentos.pdf');
        Route::get('clients/documentos/{id}/word', [DocumentoClienteController::class, 'word'])->name('clients.documentos.word');
        Route::get('clients/{id}', [ClientController::class, 'show'])->name('clients.show');
    });

    // Créditos
    Route::get('credits/activate', [CreditController::class, 'activate'])->name('credits.activate')->middleware('permission:registro.activar');
    Route::get('credits/change-status', [CreditController::class, 'changeStatus'])->name('credits.change-status')->middleware('permission:registro.estado');
    Route::get('credits/mass-delete', [CreditController::class, 'massDelete'])->name('credits.mass-delete')->middleware('permission:registro.eliminar-masivo');
    Route::get('credits/mass-delete/{id}/edit', [CreditController::class, 'massDeleteEdit'])->name('credits.mass-delete.edit')->middleware('permission:registro.eliminar-masivo');
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
        Route::get('payments/refinance/{creditId}', [PaymentController::class, 'refinance'])->name('payments.refinance');
        Route::get('payments/ticket/{massDeletionId}', [PaymentController::class, 'ticket'])->name('payments.ticket');
    });

    // Reportes de crédito diario/mensual/semanal (permiso propio; antes usaban "pagos")
    Route::get('payments/daily', [PaymentController::class, 'daily'])->name('payments.daily')->middleware('permission:reportes.credito-diario');
    Route::get('payments/monthly', [PaymentController::class, 'monthly'])->name('payments.monthly')->middleware('permission:reportes.credito-mensual');
    Route::get('payments/weekly', [PaymentController::class, 'weekly'])->name('payments.weekly')->middleware('permission:reportes.credito-semanal');

    // Caja
    Route::get('cash/opening', [CashController::class, 'opening'])->name('cash.opening')->middleware('permission:caja.apertura');
    Route::get('cash/incomes', [CashController::class, 'incomes'])->name('cash.incomes')->middleware('permission:caja.ingresos');
    Route::get('cash/incomes/create', [CashController::class, 'createIncome'])->name('cash.incomes.create')->middleware('permission:caja.ingresos');
    Route::get('cash/incomes/{id}/edit', [CashController::class, 'editIncome'])->name('cash.incomes.edit')->middleware('permission:caja.ingresos');
    Route::get('cash/incomes/{id}/gallery', [CashController::class, 'incomeGallery'])->name('cash.incomes.gallery')->middleware('permission:caja.ingresos');
    Route::get('cash/expenses', [CashController::class, 'expenses'])->name('cash.expenses')->middleware('permission:caja.egresos');
    Route::get('cash/expenses/create', [CashController::class, 'createExpense'])->name('cash.expenses.create')->middleware('permission:caja.egresos');
    Route::get('cash/expenses/{id}/edit', [CashController::class, 'editExpense'])->name('cash.expenses.edit')->middleware('permission:caja.egresos');
    Route::get('cash/expenses/{id}/gallery', [CashController::class, 'expenseGallery'])->name('cash.expenses.gallery')->middleware('permission:caja.egresos');

    // Reportes
    // Desembolsos: drill-down del dashboard (mismo acceso que el panel)
    Route::get('reports/desembolsos', [ReportController::class, 'desembolsos'])->name('reports.desembolsos')->middleware('permission:dashboard');
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

    // Área Legal
    Route::middleware('permission:legal.garantias')->group(function () {
        Route::get('legal/vehiculos', [LegalController::class, 'vehiculos'])->name('legal.vehiculos');
        Route::get('legal/garantias', [LegalController::class, 'garantias'])->name('legal.garantias.index');
        Route::get('legal/garantias/create/{creditId?}', [LegalController::class, 'garantiaCreate'])->name('legal.garantias.create');
        Route::get('legal/garantias/{id}', [LegalController::class, 'garantiaShow'])->name('legal.garantias.show');
    });
    Route::get('legal/settings', [LegalController::class, 'settings'])->name('legal.settings')->middleware('permission:legal.configuracion');
    Route::middleware('permission:legal.contratos')->group(function () {
        Route::get('legal/garantias/{id}/contrato', [LegalController::class, 'contratoForm'])->name('legal.contratos.form');
        Route::get('legal/contratos/{id}/pdf', [LegalController::class, 'contratoPdf'])->name('legal.contratos.pdf');
    });
    Route::get('legal/notaria', [LegalController::class, 'notaria'])->name('legal.notaria')->middleware('permission:legal.notaria');
    Route::get('legal/papeletas', [LegalController::class, 'papeletas'])->name('legal.papeletas')->middleware('permission:legal.papeletas');
    Route::get('legal/caja', [LegalController::class, 'caja'])->name('legal.caja')->middleware('permission:legal.caja');
    Route::middleware('permission:legal.judicial')->group(function () {
        Route::get('legal/expedientes', [LegalController::class, 'expedientes'])->name('legal.expedientes.index');
        Route::get('legal/expedientes/create', [LegalController::class, 'expedienteCreate'])->name('legal.expedientes.create');
        Route::get('legal/expedientes/{id}', [LegalController::class, 'expedienteShow'])->name('legal.expedientes.show');
    });

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

    // Exports — cada uno con el permiso del módulo al que pertenece
    // (mismo modelo de visualización que las vistas; doble-check 21/08)
    Route::get('/exports/clients', [ClientController::class, 'export'])->name('exports.clients')->middleware('permission:clientes');
    Route::get('/exports/credits', [CreditController::class, 'export'])->name('exports.credits')->middleware('permission:creditos');
    Route::get('/exports/payments', [PaymentController::class, 'export'])->name('exports.payments')->middleware('permission:pagos');
    Route::get('/exports/payments/daily', [PaymentController::class, 'exportDaily'])->name('exports.payments.daily')->middleware('permission:reportes.credito-diario');
    Route::get('/exports/payments/weekly', [PaymentController::class, 'exportWeekly'])->name('exports.payments.weekly')->middleware('permission:reportes.credito-semanal');
    Route::get('/exports/payments/monthly', [PaymentController::class, 'exportMonthly'])->name('exports.payments.monthly')->middleware('permission:reportes.credito-mensual');
    Route::get('/exports/incomes', [CashController::class, 'exportIncomes'])->name('exports.incomes')->middleware('permission:caja.ingresos');
    Route::get('/exports/expenses', [CashController::class, 'exportExpenses'])->name('exports.expenses')->middleware('permission:caja.egresos');
    Route::get('/exports/concepts', [ConceptController::class, 'export'])->name('exports.concepts')->middleware('permission:configuracion.conceptos');
    Route::get('/exports/reports/portfolio', [ReportController::class, 'exportPortfolio'])->name('exports.reports.portfolio')->middleware('permission:reportes.cartera');
    Route::get('/exports/reports/delinquent', [ReportController::class, 'exportDelinquent'])->name('exports.reports.delinquent')->middleware('permission:reportes.morosidad');
    Route::get('/exports/reports/cancelled', [ReportController::class, 'exportCancelled'])->name('exports.reports.cancelled')->middleware('permission:reportes.cancelados');
    Route::get('/exports/reports/payments', [ReportController::class, 'exportPayments'])->name('exports.reports.payments')->middleware('permission:reportes.pagos');
    Route::get('/exports/reports/cash-general-1', [ReportController::class, 'exportCashGeneral1'])->name('exports.reports.cash-general-1')->middleware('permission:reportes.caja-general-1');
    Route::get('/exports/reports/cash-general-2', [ReportController::class, 'exportCashGeneral2'])->name('exports.reports.cash-general-2')->middleware('permission:reportes.caja-general-2');
    Route::get('/exports/reports/cash-general-3', [ReportController::class, 'exportCashGeneral3'])->name('exports.reports.cash-general-3')->middleware('permission:reportes.caja-general-3');
    Route::get('/exports/reports/cash-statistics', [ReportController::class, 'exportCashStatistics'])->name('exports.reports.cash-statistics')->middleware('permission:reportes.caja-estadistica');
    Route::get('/exports/reports/credit-statistics', [ReportController::class, 'exportCreditStatistics'])->name('exports.reports.credit-statistics')->middleware('permission:reportes.credito-estadistica');
    Route::get('/exports/reports/advisor', [ReportController::class, 'exportAdvisor'])->name('exports.reports.advisor')->middleware('permission:reportes.asesor');
    Route::get('/exports/reports/simulator', [ReportController::class, 'exportSimulator'])->name('exports.reports.simulator')->middleware('permission:reportes.simulador');
    Route::get('/exports/credits/mass-deletions', [CreditController::class, 'exportMassDeletions'])->name('exports.credits.mass-deletions')->middleware('permission:registro.eliminar-masivo');
    Route::get('/exports/credits/{id}/schedule', [CreditController::class, 'exportSchedule'])->name('exports.credits.schedule')->middleware('permission:creditos');
    Route::get('/exports/clients/{id}/history', [ClientController::class, 'exportHistory'])->name('exports.clients.history')->middleware('permission:clientes');
});
