<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Excluir uma preceptoria passa a ser reversível: a linha fica guardada (com o histórico) e some das listas e dos conflitos de horário.
     */
    public function up(): void
    {
        if (Schema::hasColumn('preceptoria', 'deleted_at')) {
            return;
        }

        Schema::table('preceptoria', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('preceptoria', 'deleted_at')) {
            return;
        }

        Schema::table('preceptoria', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
