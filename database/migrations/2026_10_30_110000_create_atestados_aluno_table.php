<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Atestado do aluno (comprovante + período de afastamento), o vínculo de cada falta com o atestado que a justificou e o
     * abono de falta.
     *
     * Justificar e abonar são coisas diferentes: a falta justificada continua contando na frequência; a abonada (que exige
     * estar justificada) deixa de contar. O atestado diz se abona (`abona_faltas`) e cada falta guarda o resultado
     * (`frequencia_escolar.abonada`), então também dá para abonar uma falta isolada, sem atestado.
     *
     * O vínculo (`frequencia_escolar.atestado_aluno_id`) existe para desfazer só o que o atestado fez: ao cancelar ou
     * corrigir o período, as faltas justificadas por ele voltam a injustificadas; a justificativa lançada à mão (sem vínculo)
     * nunca é tocada. As faltas que já existem ficam sem vínculo e sem abono: não há backfill.
     */
    public function up(): void
    {
        if (! Schema::hasTable('atestados_aluno')) {
            Schema::create('atestados_aluno', function (Blueprint $table) {
                $table->id();
                $table->foreignId('matricula_id')->constrained('matricula')->cascadeOnDelete();
                $table->date('data_inicio');
                $table->date('data_fim');
                $table->string('arquivo_path')->nullable();
                $table->text('observacao')->nullable();
                $table->boolean('abona_faltas')->default(false);
                $table->string('status', 20)->default('vigente')->index();
                $table->string('motivo_cancelamento')->nullable();
                $table->timestamp('cancelado_em')->nullable();
                $table->foreignId('cancelado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['matricula_id', 'data_inicio', 'data_fim']);
            });
        }

        if (! Schema::hasColumn('frequencia_escolar', 'abonada')) {
            Schema::table('frequencia_escolar', function (Blueprint $table) {
                $table->boolean('abonada')->default(false)->after('justificada');
            });
        }

        if (! Schema::hasColumn('frequencia_escolar', 'atestado_aluno_id')) {
            Schema::table('frequencia_escolar', function (Blueprint $table) {
                $table->foreignId('atestado_aluno_id')->nullable()->after('motivo_justificativa')->constrained('atestados_aluno')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('frequencia_escolar', 'atestado_aluno_id')) {
            Schema::table('frequencia_escolar', function (Blueprint $table) {
                $table->dropConstrainedForeignId('atestado_aluno_id');
            });
        }

        if (Schema::hasColumn('frequencia_escolar', 'abonada')) {
            Schema::table('frequencia_escolar', function (Blueprint $table) {
                $table->dropColumn('abonada');
            });
        }

        Schema::dropIfExists('atestados_aluno');
    }
};
