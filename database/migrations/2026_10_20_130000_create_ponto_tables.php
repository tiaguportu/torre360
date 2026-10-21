<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabelas que nunca podem sofrer UPDATE nem DELETE (a Portaria MTP 671/2021, art. 74, proíbe alterar dados
     * registrados pelo empregado): a correção é sempre um novo registro (tratamento ou lançamento de ajuste).
     *
     * @var list<string>
     */
    private array $somenteInsercao = ['ponto_registros', 'ponto_banco_horas_lancamentos'];

    public function up(): void
    {
        Schema::create('ponto_configuracoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unidade_id')->unique()->constrained('unidade')->restrictOnDelete();
            $table->string('modo', 20)->default('controle_interno');
            $table->boolean('ativo')->default(true);

            // Requisitos do REP-P (Portaria MTP 671/2021, arts. 89 e 91) e do REP-A (art. 77).
            $table->string('registro_inpi', 60)->nullable();
            $table->string('atestado_tecnico_path')->nullable();
            $table->date('atestado_tecnico_data')->nullable();
            $table->boolean('atestado_assinatura_conferida')->default(false);
            $table->string('acordo_coletivo_referencia')->nullable();
            $table->date('acordo_coletivo_vigencia_fim')->nullable();

            $table->boolean('exige_hora_legal')->default(true);
            $table->unsignedTinyInteger('tolerancia_diaria_minutos')->default(10);

            // Sequenciador dos registros da unidade (NSR) e ponta da cadeia de hash; atualizados sob lock a cada marcação.
            $table->unsignedBigInteger('ultimo_nsr')->default(0);
            $table->char('hash_ultimo_registro', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('ponto_registros', function (Blueprint $table): void {
            $table->id();
            // restrictOnDelete: apagar a unidade ou o funcionário nunca pode levar os registros junto.
            $table->foreignId('unidade_id')->constrained('unidade')->restrictOnDelete();
            $table->foreignId('funcionario_id')->constrained('funcionarios')->restrictOnDelete();
            $table->unsignedBigInteger('nsr');
            $table->dateTime('momento');
            $table->string('fuso', 6)->default('-03:00');
            $table->string('origem', 20);
            $table->string('ip', 45)->nullable();
            $table->boolean('hora_legal_ok')->default(false);
            $table->integer('desvio_segundos')->nullable();
            $table->char('hash_anterior', 64)->nullable();
            $table->char('hash', 64);
            $table->timestamp('created_at')->nullable();

            $table->unique(['unidade_id', 'nsr'], 'uq_ponto_registro_unidade_nsr');
            $table->index(['funcionario_id', 'momento'], 'idx_ponto_registro_funcionario_momento');
        });

        Schema::create('ponto_tratamentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unidade_id')->constrained('unidade')->restrictOnDelete();
            $table->foreignId('funcionario_id')->constrained('funcionarios')->restrictOnDelete();
            $table->date('data');
            $table->string('tipo', 20);
            $table->dateTime('momento')->nullable();
            $table->foreignId('registro_id')->nullable()->constrained('ponto_registros')->restrictOnDelete();
            $table->unsignedSmallInteger('minutos_abono')->nullable();
            // Pode citar atestado médico (dado de saúde): vai criptografado.
            $table->longText('motivo');
            $table->string('status', 15)->default('pendente');
            $table->foreignId('solicitado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decidido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decidido_em')->nullable();
            $table->timestamps();

            $table->index(['funcionario_id', 'data'], 'idx_ponto_tratamento_funcionario_data');
            $table->index('status', 'idx_ponto_tratamento_status');
        });

        Schema::create('ponto_jornadas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funcionario_id')->constrained('funcionarios')->restrictOnDelete();
            $table->date('vigencia_inicio');
            $table->date('vigencia_fim')->nullable();
            // Minutos previstos por dia da semana (0 = folga).
            foreach (['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'] as $dia) {
                $table->unsignedSmallInteger("minutos_{$dia}")->default(0);
            }
            $table->timestamps();

            $table->index(['funcionario_id', 'vigencia_inicio'], 'idx_ponto_jornada_funcionario_vigencia');
        });

        Schema::create('ponto_acordos_banco_horas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funcionario_id')->constrained('funcionarios')->restrictOnDelete();
            $table->string('tipo', 25);
            $table->date('vigencia_inicio');
            $table->date('vigencia_fim')->nullable();
            $table->unsignedTinyInteger('prazo_compensacao_meses');
            $table->string('documento_referencia');
            $table->timestamps();

            $table->index(['funcionario_id', 'vigencia_inicio'], 'idx_ponto_acordo_funcionario_vigencia');
        });

        Schema::create('ponto_banco_horas_lancamentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funcionario_id')->constrained('funcionarios')->restrictOnDelete();
            $table->date('data');
            // Com sinal: crédito positivo, débito negativo.
            $table->integer('minutos');
            $table->string('origem', 25);
            $table->date('vence_em')->nullable();
            // Na expiração, o crédito específico que venceu.
            $table->foreignId('credito_origem_id')->nullable()->constrained('ponto_banco_horas_lancamentos')->restrictOnDelete();
            $table->string('observacao')->nullable();
            $table->foreignId('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['funcionario_id', 'data'], 'idx_ponto_lancamento_funcionario_data');
        });

        foreach ($this->somenteInsercao as $tabela) {
            $this->criarGatilhos($tabela);
        }
    }

    public function down(): void
    {
        // Apagar a tabela leva os gatilhos junto.
        Schema::dropIfExists('ponto_banco_horas_lancamentos');
        Schema::dropIfExists('ponto_acordos_banco_horas');
        Schema::dropIfExists('ponto_jornadas');
        Schema::dropIfExists('ponto_tratamentos');
        Schema::dropIfExists('ponto_registros');
        Schema::dropIfExists('ponto_configuracoes');
    }

    /**
     * Segunda barreira contra alteração: o banco recusa UPDATE e DELETE mesmo se alguém contornar o model. Se o
     * usuário do banco não tiver privilégio de criar gatilhos (ou o log binário exigir SUPER), a migration segue e
     * registra o aviso: o model continua protegendo e `ponto:verificar-integridade` detecta adulteração.
     */
    private function criarGatilhos(string $tabela): void
    {
        $driver = DB::getDriverName();
        $mensagem = "{$tabela} aceita apenas insercao";

        try {
            if ($driver === 'sqlite') {
                foreach (['UPDATE', 'DELETE'] as $operacao) {
                    DB::unprepared("CREATE TRIGGER {$tabela}_bloqueia_{$operacao} BEFORE {$operacao} ON {$tabela} BEGIN SELECT RAISE(ABORT, '{$mensagem}'); END;");
                }
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                foreach (['UPDATE', 'DELETE'] as $operacao) {
                    DB::unprepared("CREATE TRIGGER {$tabela}_bloqueia_{$operacao} BEFORE {$operacao} ON {$tabela} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensagem}'");
                }
            }
        } catch (Throwable $e) {
            Log::warning("Gatilhos de somente-inserção não criados em {$tabela}: ".$e->getMessage());
        }
    }
};
