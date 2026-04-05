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
        // Renomear tabela principal
        Schema::rename('documento_obrigatorio', 'tipo_documento');

        Schema::table('tipo_documento', function (Blueprint $table) {
            // Remover flag de ativo
            if (Schema::hasColumn('tipo_documento', 'flag_ativo')) {
                $table->dropColumn('flag_ativo');
            }

            // A flag_obrigatorio já deve existir, garantimos que ela esteja lá
            if (!Schema::hasColumn('tipo_documento', 'flag_obrigatorio')) {
                $table->boolean('flag_obrigatorio')->default(true);
            }

            // Remover curso_id da tabela principal (agora é N:N)
            if (Schema::hasColumn('tipo_documento', 'curso_id')) {
                $table->dropForeign(['curso_id']);
                $table->dropColumn('curso_id');
            }
        });

        // Criar tabelas pivô
        Schema::create('tipo_documento_curso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_documento_id')->constrained('tipo_documento')->cascadeOnDelete();
            $table->foreignId('curso_id')->constrained('curso')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('tipo_documento_turma', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_documento_id')->constrained('tipo_documento')->cascadeOnDelete();
            $table->foreignId('turma_id')->constrained('turma')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('tipo_documento_matricula', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_documento_id')->constrained('tipo_documento')->cascadeOnDelete();
            $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
            $table->timestamps();
        });

        // Atualizar FK em documento_inserido se existir
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

        Schema::rename('tipo_documento', 'documento_obrigatorio');

        Schema::table('documento_obrigatorio', function (Blueprint $table) {
            $table->foreignId('curso_id')->nullable()->constrained('curso');
            $table->boolean('flag_ativo')->default(true);
        });
    }
};
