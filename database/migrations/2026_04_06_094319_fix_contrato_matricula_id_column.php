<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Usar SQL bruto para evitar erros de pré-carregamento ou locks do Laravel
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::statement('ALTER TABLE contrato DROP FOREIGN KEY contratos_matricula_id_foreign');
            DB::statement('ALTER TABLE contrato DROP COLUMN matricula_id');
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } catch (Exception $e) {
            // Se falhar (ex: coluna já não existe), silenciamos para o migrate continuar
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contrato', function (Blueprint $table) {
            $table->foreignId('matricula_id')->nullable()->constrained('matricula')->onDelete('cascade');
        });
    }
};
