<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resolução da pendência A3 de segurança (LGPD arts. 5º II, 11 e 46):
     * Criptografia em repouso dos dados de saúde de crianças e alunos
     * (tipo sanguíneo, alergias, plano de saúde, SUS, sintomas, medicamentos).
     */
    public function up(): void
    {
        // 1. Ampliar tamanho das colunas para suportar ciphertext
        Schema::table('ficha_medicas', function (Blueprint $table) {
            $table->text('tipo_sanguineo')->nullable()->change();
            $table->text('plano_saude')->nullable()->change();
            $table->text('numero_carteira_sus')->nullable()->change();
            $table->text('hospital_preferencia')->nullable()->change();
        });

        Schema::table('atendimento_enfermagems', function (Blueprint $table) {
            $table->text('medicamento_ministrado')->nullable()->change();
        });

        Schema::table('medicamento_alunos', function (Blueprint $table) {
            $table->text('nome_medicamento')->change();
            $table->text('dosagem')->nullable()->change();
            $table->text('horario_administracao')->nullable()->change();
        });

        // 2. Criptografar registros existentes em ficha_medicas
        $colunasFicha = [
            'tipo_sanguineo',
            'outras_alergias_alimentares',
            'observacoes_alimentares',
            'plano_saude',
            'numero_carteira_sus',
            'hospital_preferencia',
            'observacoes_gerais',
        ];

        DB::table('ficha_medicas')->orderBy('id')->each(function (object $ficha) use ($colunasFicha): void {
            $atualizacoes = [];

            foreach ($colunasFicha as $coluna) {
                $valor = $ficha->{$coluna} ?? null;
                if ($valor !== null && (string) $valor !== '') {
                    try {
                        Crypt::decryptString((string) $valor);
                    } catch (Throwable) {
                        // Não estava criptografado, criptografa agora
                        $atualizacoes[$coluna] = Crypt::encryptString((string) $valor);
                    }
                }
            }

            if (! empty($atualizacoes)) {
                DB::table('ficha_medicas')->where('id', $ficha->id)->update($atualizacoes);
            }
        });

        // 3. Criptografar registros existentes em atendimento_enfermagems
        $colunasAtendimento = [
            'sintomas_queixa',
            'procedimento_realizado',
            'medicamento_ministrado',
            'observacoes',
        ];

        DB::table('atendimento_enfermagems')->orderBy('id')->each(function (object $atendimento) use ($colunasAtendimento): void {
            $atualizacoes = [];

            foreach ($colunasAtendimento as $coluna) {
                $valor = $atendimento->{$coluna} ?? null;
                if ($valor !== null && (string) $valor !== '') {
                    try {
                        Crypt::decryptString((string) $valor);
                    } catch (Throwable) {
                        $atualizacoes[$coluna] = Crypt::encryptString((string) $valor);
                    }
                }
            }

            if (! empty($atualizacoes)) {
                DB::table('atendimento_enfermagems')->where('id', $atendimento->id)->update($atualizacoes);
            }
        });

        // 4. Criptografar registros existentes em medicamento_alunos
        $colunasMedicamento = [
            'nome_medicamento',
            'dosagem',
            'horario_administracao',
            'instrucoes',
        ];

        DB::table('medicamento_alunos')->orderBy('id')->each(function (object $medicamento) use ($colunasMedicamento): void {
            $atualizacoes = [];

            foreach ($colunasMedicamento as $coluna) {
                $valor = $medicamento->{$coluna} ?? null;
                if ($valor !== null && (string) $valor !== '') {
                    try {
                        Crypt::decryptString((string) $valor);
                    } catch (Throwable) {
                        $atualizacoes[$coluna] = Crypt::encryptString((string) $valor);
                    }
                }
            }

            if (! empty($atualizacoes)) {
                DB::table('medicamento_alunos')->where('id', $medicamento->id)->update($atualizacoes);
            }
        });
    }

    public function down(): void
    {
        // Reversão: descriptografar
        $colunasFicha = [
            'tipo_sanguineo',
            'outras_alergias_alimentares',
            'observacoes_alimentares',
            'plano_saude',
            'numero_carteira_sus',
            'hospital_preferencia',
            'observacoes_gerais',
        ];

        DB::table('ficha_medicas')->orderBy('id')->each(function (object $ficha) use ($colunasFicha): void {
            $atualizacoes = [];
            foreach ($colunasFicha as $coluna) {
                $valor = $ficha->{$coluna} ?? null;
                if ($valor !== null && (string) $valor !== '') {
                    try {
                        $atualizacoes[$coluna] = Crypt::decryptString((string) $valor);
                    } catch (Throwable) {
                    }
                }
            }
            if (! empty($atualizacoes)) {
                DB::table('ficha_medicas')->where('id', $ficha->id)->update($atualizacoes);
            }
        });

        $colunasAtendimento = [
            'sintomas_queixa',
            'procedimento_realizado',
            'medicamento_ministrado',
            'observacoes',
        ];

        DB::table('atendimento_enfermagems')->orderBy('id')->each(function (object $atendimento) use ($colunasAtendimento): void {
            $atualizacoes = [];
            foreach ($colunasAtendimento as $coluna) {
                $valor = $atendimento->{$coluna} ?? null;
                if ($valor !== null && (string) $valor !== '') {
                    try {
                        $atualizacoes[$coluna] = Crypt::decryptString((string) $valor);
                    } catch (Throwable) {
                    }
                }
            }
            if (! empty($atualizacoes)) {
                DB::table('atendimento_enfermagems')->where('id', $atendimento->id)->update($atualizacoes);
            }
        });

        $colunasMedicamento = [
            'nome_medicamento',
            'dosagem',
            'horario_administracao',
            'instrucoes',
        ];

        DB::table('medicamento_alunos')->orderBy('id')->each(function (object $medicamento) use ($colunasMedicamento): void {
            $atualizacoes = [];
            foreach ($colunasMedicamento as $coluna) {
                $valor = $medicamento->{$coluna} ?? null;
                if ($valor !== null && (string) $valor !== '') {
                    try {
                        $atualizacoes[$coluna] = Crypt::decryptString((string) $valor);
                    } catch (Throwable) {
                    }
                }
            }
            if (! empty($atualizacoes)) {
                DB::table('medicamento_alunos')->where('id', $medicamento->id)->update($atualizacoes);
            }
        });

        Schema::table('ficha_medicas', function (Blueprint $table) {
            $table->string('tipo_sanguineo')->nullable()->change();
            $table->string('plano_saude')->nullable()->change();
            $table->string('numero_carteira_sus')->nullable()->change();
            $table->string('hospital_preferencia')->nullable()->change();
        });

        Schema::table('atendimento_enfermagems', function (Blueprint $table) {
            $table->string('medicamento_ministrado')->nullable()->change();
        });

        Schema::table('medicamento_alunos', function (Blueprint $table) {
            $table->string('nome_medicamento')->change();
            $table->string('dosagem')->nullable()->change();
            $table->string('horario_administracao')->nullable()->change();
        });
    }
};
