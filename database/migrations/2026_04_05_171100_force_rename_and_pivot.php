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
        // 1. Renomear se necessário
        if (Schema::hasTable('documento_obrigatorio') && ! Schema::hasTable('tipo_documento')) {
            Schema::rename('documento_obrigatorio', 'tipo_documento');
        }

        // 2. Limpar tipo_documento
        Schema::table('tipo_documento', function (Blueprint $table) {
            if (Schema::hasColumn('tipo_documento', 'flag_ativo')) {
                $table->dropColumn('flag_ativo');
            }
            if (! Schema::hasColumn('tipo_documento', 'flag_obrigatorio')) {
                $table->boolean('flag_obrigatorio')->default(true);
            }
        });

        // 3. Remover curso_id de forma forçada
        if (Schema::hasColumn('tipo_documento', 'curso_id')) {
            if (config('database.default') !== 'sqlite') {
                try {
                    $dbname = DB::connection()->getDatabaseName();
                    $constraints = DB::select("
                        SELECT CONSTRAINT_NAME
                        FROM information_schema.KEY_COLUMN_USAGE
                        WHERE TABLE_SCHEMA = ?
                        AND TABLE_NAME = 'tipo_documento'
                        AND COLUMN_NAME = 'curso_id'
                        AND REFERENCED_TABLE_NAME IS NOT NULL
                    ", [$dbname]);

                    foreach ($constraints as $constraint) {
                        DB::statement('ALTER TABLE tipo_documento DROP FOREIGN KEY '.$constraint->CONSTRAINT_NAME);
                    }
                } catch (Exception $e) {
                }
            }

            Schema::table('tipo_documento', function (Blueprint $table) {
                $table->dropColumn('curso_id');
            });
        }

        // 4. Criar pivôs
        if (! Schema::hasTable('tipo_documento_curso')) {
            Schema::create('tipo_documento_curso', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tipo_documento_id')->constrained('tipo_documento')->cascadeOnDelete();
                $table->foreignId('curso_id')->constrained('curso')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tipo_documento_turma')) {
            Schema::create('tipo_documento_turma', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tipo_documento_id')->constrained('tipo_documento')->cascadeOnDelete();
                $table->foreignId('turma_id')->constrained('turma')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tipo_documento_matricula')) {
            Schema::create('tipo_documento_matricula', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tipo_documento_id')->constrained('tipo_documento')->cascadeOnDelete();
                $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        // 5. Atualizar documento_inserido
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
        Schema::dropIfExists('tipo_documento_matricula');
        Schema::dropIfExists('tipo_documento_turma');
        Schema::dropIfExists('tipo_documento_curso');
    }
};
