<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('tipo_documento') && ! Schema::hasColumn('tipo_documento', 'categoria_exigencia')) {
            Schema::table('tipo_documento', function (Blueprint $table) {
                $table->string('categoria_exigencia', 32)
                    ->default('obrigatorio_contrato')
                    ->after('flag_obrigatorio');
            });

            // Atualiza registros existentes com base em flag_obrigatorio
            DB::table('tipo_documento')
                ->where('flag_obrigatorio', false)
                ->update(['categoria_exigencia' => 'opcional']);

            DB::table('tipo_documento')
                ->where('flag_obrigatorio', true)
                ->update(['categoria_exigencia' => 'obrigatorio_contrato']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tipo_documento') && Schema::hasColumn('tipo_documento', 'categoria_exigencia')) {
            Schema::table('tipo_documento', function (Blueprint $table) {
                $table->dropColumn('categoria_exigencia');
            });
        }
    }
};
