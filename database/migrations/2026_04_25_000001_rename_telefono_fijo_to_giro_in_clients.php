<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('clients', 'telefono_fijo') && !Schema::hasColumn('clients', 'giro')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->renameColumn('telefono_fijo', 'giro');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'giro') && !Schema::hasColumn('clients', 'telefono_fijo')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->renameColumn('giro', 'telefono_fijo');
            });
        }
    }
};
