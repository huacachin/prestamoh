<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // CREDITS
        $this->ensureIndex('credits', 'credits_fecha_prestamo_index', ['fecha_prestamo']);
        $this->ensureIndex('credits', 'credits_fecha_actualizacion_index', ['fecha_actualizacion']);
        $this->ensureIndex('credits', 'credits_fecha_cancelacion_index', ['fecha_cancelacion']);
        $this->ensureIndex('credits', 'credits_refi_fechacan_index', ['refinanciado', 'fecha_cancelacion']);
        $this->ensureIndex('credits', 'credits_tipo_planilla_index', ['tipo_planilla']);

        // PAYMENTS
        $this->ensureIndex('payments', 'payments_credit_id_tipo_index', ['credit_id', 'tipo']);

        // INCOMES
        $this->ensureIndex('incomes', 'incomes_date_index', ['date']);
        $this->ensureIndex('incomes', 'incomes_modo_index', ['modo']);

        // EXPENSES
        $this->ensureIndex('expenses', 'expenses_date_index', ['date']);
        $this->ensureIndex('expenses', 'expenses_modo_index', ['modo']);

        // MASS_DELETIONS
        $this->ensureIndex('mass_deletions', 'mass_deletions_date_index', ['date']);
        $this->ensureIndex('mass_deletions', 'mass_deletions_credit_id_index', ['credit_id']);

        // CREDIT_INSTALLMENTS
        $this->ensureIndex('credit_installments', 'credit_installments_fecha_venc_index', ['fecha_vencimiento']);
    }

    public function down(): void
    {
        $this->dropIndex('credits', 'credits_fecha_prestamo_index');
        $this->dropIndex('credits', 'credits_fecha_actualizacion_index');
        $this->dropIndex('credits', 'credits_fecha_cancelacion_index');
        $this->dropIndex('credits', 'credits_refi_fechacan_index');
        $this->dropIndex('credits', 'credits_tipo_planilla_index');
        $this->dropIndex('payments', 'payments_credit_id_tipo_index');
        $this->dropIndex('incomes', 'incomes_date_index');
        $this->dropIndex('incomes', 'incomes_modo_index');
        $this->dropIndex('expenses', 'expenses_date_index');
        $this->dropIndex('expenses', 'expenses_modo_index');
        $this->dropIndex('mass_deletions', 'mass_deletions_date_index');
        $this->dropIndex('mass_deletions', 'mass_deletions_credit_id_index');
        $this->dropIndex('credit_installments', 'credit_installments_fecha_venc_index');
    }

    private function ensureIndex(string $table, string $indexName, array $columns): void
    {
        $exists = collect(DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]))->isNotEmpty();
        if (!$exists) {
            Schema::table($table, function (Blueprint $t) use ($columns, $indexName) {
                $t->index($columns, $indexName);
            });
        }
    }

    private function dropIndex(string $table, string $indexName): void
    {
        $exists = collect(DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]))->isNotEmpty();
        if ($exists) {
            Schema::table($table, function (Blueprint $t) use ($indexName) {
                $t->dropIndex($indexName);
            });
        }
    }
};
