<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Remover a chave estrangeira (se existir)
        if (Schema::hasTable('habilidades') && Schema::hasColumn('habilidades', 'disciplina_id')) {
            try {
                Schema::table('habilidades', function (Blueprint $table) {
                    $table->dropForeign(['disciplina_id']);
                });
            } catch (\Exception $e) {
                // Silencia se a chave não existir com o nome padrão
            }
        }

        // 2. Remover a coluna
        if (Schema::hasTable('habilidades') && Schema::hasColumn('habilidades', 'disciplina_id')) {
            try {
                Schema::table('habilidades', function (Blueprint $table) {
                    $table->dropColumn('disciplina_id');
                });
            } catch (\Exception $e) {
                // Silencia erros de remoção de coluna
            }
        }

        // 3. Adicionar campo de experiência
        if (Schema::hasTable('habilidades') && !Schema::hasColumn('habilidades', 'campo_experiencia_id')) {
            Schema::table('habilidades', function (Blueprint $table) {
                $table->foreignId('campo_experiencia_id')->nullable()->constrained('campo_experiencias')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('habilidades', function (Blueprint $table) {
            if (Schema::hasColumn('habilidades', 'campo_experiencia_id')) {
                try {
                    $table->dropForeign(['campo_experiencia_id']);
                } catch (\Exception $e) {}
                $table->dropColumn('campo_experiencia_id');
            }
            $table->foreignId('disciplina_id')->nullable()->constrained('disciplina')->onDelete('cascade');
        });
    }
};
