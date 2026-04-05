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
        // 1. Renomear se necessário (já vimos que chama tipo_documento agora)
        if (Schema::hasTable('documento_obrigatorio') && !Schema::hasTable('tipo_documento')) {
            Schema::rename('documento_obrigatorio', 'tipo_documento');
        }

        // 2. Modificar tipo_documento
        Schema::table('tipo_documento', function (Blueprint $table) {
            // Remover flag de ativo se existir
            if (Schema::hasColumn('tipo_documento', 'flag_ativo')) {
                $table->dropColumn('flag_ativo');
            }

            // flag_obrigatorio
            if (!Schema::hasColumn('tipo_documento', 'flag_obrigatorio')) {
                $table->boolean('flag_obrigatorio')->default(true);
            }

            // Remover curso_id (N:N agora)
            if (Schema::hasColumn('tipo_documento', 'curso_id')) {
                // Tentar remover a constraint pelo nome padrão ou apenas a coluna
                try {
                    $table->dropForeign(['curso_id']);
                } catch (\Exception $e) {}
                $table->dropColumn('curso_id');
            }
        });

        // 3. Criar pivôs
        if (!Schema::hasTable('tipo_documento_curso')) {
            Schema::create('tipo_documento_curso', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tipo_documento_id')->constrained('tipo_documento')->cascadeOnDelete();
                $table->foreignId('curso_id')->constrained('curso')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tipo_documento_turma')) {
            Schema::create('tipo_documento_turma', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tipo_documento_id')->constrained('tipo_documento')->cascadeOnDelete();
                $table->foreignId('turma_id')->constrained('turma')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tipo_documento_matricula')) {
            Schema::create('tipo_documento_matricula', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tipo_documento_id')->constrained('tipo_documento')->cascadeOnDelete();
                $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        // 4. Atualizar FK em documento_inserido
        if (Schema::hasTable('documento_inserido')) {
            Schema::table('documento_inserido', function (Blueprint $table) {
                if (Schema::hasColumn('documento_inserido', 'documento_obrigatorio_id')) {
                    $table->renameColumn('documento_obrigatorio_id', 'tipo_documento_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('documento_inserido')) {
            Schema::table('documento_inserido', function (Blueprint $table) {
                if (Schema::hasColumn('documento_inserido', 'tipo_documento_id')) {
                    $table->renameColumn('tipo_documento_id', 'documento_obrigatorio_id');
                }
            });
        }

        Schema::dropIfExists('tipo_documento_matricula');
        Schema::dropIfExists('tipo_documento_turma');
        Schema::dropIfExists('tipo_documento_curso');

        if (Schema::hasTable('tipo_documento')) {
            Schema::table('tipo_documento', function (Blueprint $table) {
                if (!Schema::hasColumn('tipo_documento', 'curso_id')) {
                    $table->foreignId('curso_id')->nullable()->constrained('curso');
                }
                if (!Schema::hasColumn('tipo_documento', 'flag_ativo')) {
                    $table->boolean('flag_ativo')->default(true);
                }
            });
            
            if (!Schema::hasTable('documento_obrigatorio')) {
                Schema::rename('tipo_documento', 'documento_obrigatorio');
            }
        }
    }
};
