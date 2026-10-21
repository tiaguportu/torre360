<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModoPonto;
use App\Exceptions\PontoException;
use App\Models\Funcionario;
use App\Models\Pessoa;
use App\Models\PontoConfiguracao;
use App\Models\PontoRegistro;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Ponto\AssinadorPonto;
use App\Services\Ponto\ComprovantePontoService;
use App\Services\Ponto\ConformidadePontoService;
use App\Services\Ponto\HoraLegalProvider;
use App\Services\Ponto\NtpHoraLegalProvider;
use App\Services\Ponto\PontoIntegridadeService;
use App\Services\Ponto\PontoRegistroService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Registro de ponto (Onda 41): marcação com NSR e hash encadeados, imutabilidade, verificação de integridade,
 * conformidade com a Portaria MTP 671/2021 (portões do modo oficial) e comprovante.
 */
class PontoRegistroTest extends TestCase
{
    use RefreshDatabase;

    private ?int $desvioDaHoraLegal = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-20 09:00:00'));
        $this->usarHoraLegal(0);
    }

    /**
     * Troca a fonte da hora por uma controlável (nenhum teste consulta a rede).
     */
    private function usarHoraLegal(?int $desvio, bool $verifica = true): void
    {
        $this->desvioDaHoraLegal = $desvio;

        $this->app->instance(HoraLegalProvider::class, new class($desvio, $verifica) implements HoraLegalProvider
        {
            public function __construct(private ?int $desvio, private bool $verifica) {}

            public function desvioSegundos(): ?int
            {
                return $this->desvio;
            }

            public function verificaHoraLegal(): bool
            {
                return $this->verifica;
            }
        });
    }

    private function unidadeComPonto(array $config = []): Unidade
    {
        $unidade = Unidade::create(['nome' => 'Unidade '.uniqid(), 'cnpj' => '12.345.678/0001-90', 'situacao_funcionamento' => '1']);
        PontoConfiguracao::factory()->create(array_merge(['unidade_id' => $unidade->id], $config));

        return $unidade;
    }

    private function funcionario(Unidade $unidade, array $atributos = []): Funcionario
    {
        return Funcionario::factory()->create(array_merge([
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Marta Souza'])->id,
            'unidade_id' => $unidade->id,
            'data_admissao' => '2025-01-10',
        ], $atributos));
    }

    private function registrar(Funcionario $funcionario, ?string $momento = null): PontoRegistro
    {
        if ($momento) {
            $this->travelTo(Carbon::parse($momento));
        }

        return app(PontoRegistroService::class)->registrar($funcionario);
    }

    // ─── Registro: NSR, hash e horário ───────────────────────────────

    public function test_nsr_e_sequencial_por_unidade(): void
    {
        $a = $this->unidadeComPonto();
        $b = $this->unidadeComPonto();
        $ana = $this->funcionario($a);
        $beto = $this->funcionario($b);

        $this->assertSame(1, $this->registrar($ana, '2026-10-20 08:00:00')->nsr);
        $this->assertSame(1, $this->registrar($beto, '2026-10-20 08:01:00')->nsr);
        $this->assertSame(2, $this->registrar($ana, '2026-10-20 12:00:00')->nsr);
        $this->assertSame(3, $this->registrar($ana, '2026-10-20 13:00:00')->nsr);
        $this->assertSame(3, PontoConfiguracao::where('unidade_id', $a->id)->value('ultimo_nsr'));
        $this->assertSame(1, PontoConfiguracao::where('unidade_id', $b->id)->value('ultimo_nsr'));
    }

    public function test_hash_encadeia_com_o_registro_anterior_e_confere(): void
    {
        $unidade = $this->unidadeComPonto();
        $func = $this->funcionario($unidade);

        $primeiro = $this->registrar($func, '2026-10-20 08:00:00');
        $segundo = $this->registrar($func, '2026-10-20 12:00:00');

        $this->assertNull($primeiro->hash_anterior);
        $this->assertSame($primeiro->hash, $segundo->hash_anterior);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $segundo->hash);
        $this->assertTrue($primeiro->fresh()->hashConfere());
        $this->assertTrue($segundo->fresh()->hashConfere());
        $this->assertSame($segundo->hash, PontoConfiguracao::where('unidade_id', $unidade->id)->value('hash_ultimo_registro'));
    }

    public function test_horario_e_sempre_o_do_servidor_com_fuso(): void
    {
        $func = $this->funcionario($this->unidadeComPonto());

        $registro = $this->registrar($func, '2026-10-20 08:03:27');

        $this->assertSame('2026-10-20 08:03:27', $registro->momento->format('Y-m-d H:i:s'));
        $this->assertSame('-03:00', $registro->fuso);
        $this->assertSame('web', $registro->origem->value);
    }

    public function test_segundo_toque_em_ate_dez_segundos_devolve_o_mesmo_registro(): void
    {
        $func = $this->funcionario($this->unidadeComPonto());

        $primeiro = $this->registrar($func, '2026-10-20 08:00:00');
        $duplo = $this->registrar($func, '2026-10-20 08:00:05');
        $novo = $this->registrar($func, '2026-10-20 08:00:20');

        $this->assertSame($primeiro->id, $duplo->id);
        $this->assertNotSame($primeiro->id, $novo->id);
        $this->assertSame(2, PontoRegistro::count());
    }

    public function test_hora_legal_dentro_do_limite_fica_confirmada_e_fora_ou_indisponivel_apenas_sinaliza(): void
    {
        $func = $this->funcionario($this->unidadeComPonto());

        $this->usarHoraLegal(2);
        $ok = $this->registrar($func, '2026-10-20 08:00:00');
        $this->assertTrue($ok->hora_legal_ok);
        $this->assertSame(2, $ok->desvio_segundos);

        $this->usarHoraLegal(-45);
        $fora = $this->registrar($func, '2026-10-20 12:00:00');
        $this->assertFalse($fora->hora_legal_ok);
        $this->assertSame(-45, $fora->desvio_segundos);

        $this->usarHoraLegal(null);
        $indisponivel = $this->registrar($func, '2026-10-20 13:00:00');
        $this->assertFalse($indisponivel->hora_legal_ok);
        $this->assertNull($indisponivel->desvio_segundos);

        // Nunca bloqueia o trabalhador: as três marcações foram gravadas.
        $this->assertSame(3, PontoRegistro::count());
    }

    public function test_recusa_funcionario_desligado_sem_unidade_e_unidade_sem_ponto_ativo(): void
    {
        $unidade = $this->unidadeComPonto();

        try {
            app(PontoRegistroService::class)->registrar($this->funcionario($unidade, ['data_desligamento' => '2026-09-30']));
            $this->fail('Funcionário desligado não registra ponto.');
        } catch (PontoException $e) {
            $this->assertStringContainsString('desligado', $e->getMessage());
        }

        try {
            app(PontoRegistroService::class)->registrar($this->funcionario($unidade, ['unidade_id' => null]));
            $this->fail('Sem unidade não registra.');
        } catch (PontoException $e) {
            $this->assertStringContainsString('unidade de lotação', $e->getMessage());
        }

        $semConfig = Unidade::create(['nome' => 'Sem ponto', 'situacao_funcionamento' => '1']);
        try {
            app(PontoRegistroService::class)->registrar($this->funcionario($semConfig));
            $this->fail('Unidade sem configuração não registra.');
        } catch (PontoException $e) {
            $this->assertStringContainsString('não foi configurado', $e->getMessage());
        }

        PontoConfiguracao::where('unidade_id', $unidade->id)->update(['ativo' => false]);
        $this->expectException(PontoException::class);
        app(PontoRegistroService::class)->registrar($this->funcionario($unidade));
    }

    // ─── Imutabilidade ───────────────────────────────────────────────

    public function test_model_recusa_alterar_e_apagar_registro(): void
    {
        $registro = $this->registrar($this->funcionario($this->unidadeComPonto()), '2026-10-20 08:00:00');

        try {
            $registro->update(['momento' => '2026-10-20 07:00:00']);
            $this->fail('Registro não pode ser alterado.');
        } catch (PontoException $e) {
            $this->assertStringContainsString('não pode ser alterado', $e->getMessage());
        }

        $this->expectException(PontoException::class);
        $registro->delete();
    }

    public function test_banco_de_dados_tambem_recusa_alterar_e_apagar_registro(): void
    {
        $registro = $this->registrar($this->funcionario($this->unidadeComPonto()), '2026-10-20 08:00:00');

        try {
            DB::table('ponto_registros')->where('id', $registro->id)->update(['momento' => '2026-10-20 07:00:00']);
            $this->fail('O gatilho de UPDATE deveria bloquear.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('apenas insercao', $e->getMessage());
        }

        $this->expectException(QueryException::class);
        DB::table('ponto_registros')->where('id', $registro->id)->delete();
    }

    public function test_apagar_funcionario_com_registros_e_barrado(): void
    {
        $func = $this->funcionario($this->unidadeComPonto());
        $this->registrar($func, '2026-10-20 08:00:00');

        $this->expectException(QueryException::class);
        DB::table('funcionarios')->where('id', $func->id)->delete();
    }

    // ─── Integridade ─────────────────────────────────────────────────

    private function removerGatilhos(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS ponto_registros_bloqueia_UPDATE');
        DB::unprepared('DROP TRIGGER IF EXISTS ponto_registros_bloqueia_DELETE');
    }

    private function cadeiaDeTres(): array
    {
        $unidade = $this->unidadeComPonto();
        $func = $this->funcionario($unidade);

        return [$unidade, [
            $this->registrar($func, '2026-10-20 08:00:00'),
            $this->registrar($func, '2026-10-20 12:00:00'),
            $this->registrar($func, '2026-10-20 13:00:00'),
        ]];
    }

    public function test_cadeia_intacta_passa_na_verificacao(): void
    {
        [$unidade] = $this->cadeiaDeTres();

        $resultado = app(PontoIntegridadeService::class)->verificar($unidade->id);

        $this->assertTrue($resultado['ok']);
        $this->assertSame(3, $resultado['total']);
        $this->assertSame([], $resultado['quebras']);
    }

    public function test_verificacao_detecta_conteudo_adulterado(): void
    {
        [$unidade, $registros] = $this->cadeiaDeTres();
        $this->removerGatilhos();
        DB::table('ponto_registros')->where('id', $registros[1]->id)->update(['momento' => '2026-10-20 11:00:00']);

        $resultado = app(PontoIntegridadeService::class)->verificar($unidade->id);

        $this->assertFalse($resultado['ok']);
        $this->assertSame(['hash_invalido'], collect($resultado['quebras'])->pluck('tipo')->all());
        $this->assertSame(2, $resultado['quebras'][0]['nsr']);
    }

    public function test_verificacao_detecta_registro_removido_do_meio_e_do_fim(): void
    {
        [$unidade, $registros] = $this->cadeiaDeTres();
        $this->removerGatilhos();

        DB::table('ponto_registros')->where('id', $registros[1]->id)->delete();
        $meio = app(PontoIntegridadeService::class)->verificar($unidade->id);
        $this->assertContains('lacuna_nsr', collect($meio['quebras'])->pluck('tipo')->all());
        $this->assertContains('encadeamento', collect($meio['quebras'])->pluck('tipo')->all());

        DB::table('ponto_registros')->where('id', $registros[2]->id)->delete();
        $fim = app(PontoIntegridadeService::class)->verificar($unidade->id);
        $this->assertContains('cauda', collect($fim['quebras'])->pluck('tipo')->all());
    }

    public function test_comando_de_integridade_falha_quando_ha_quebra(): void
    {
        [$unidade, $registros] = $this->cadeiaDeTres();

        $this->artisan('ponto:verificar-integridade')->assertSuccessful();

        $this->removerGatilhos();
        DB::table('ponto_registros')->where('id', $registros[2]->id)->delete();

        $this->artisan('ponto:verificar-integridade', ['--unidade' => $unidade->id])->assertFailed();
    }

    // ─── Conformidade (portões do modo oficial) ──────────────────────

    private function conformidade(): ConformidadePontoService
    {
        return app(ConformidadePontoService::class);
    }

    private function codigos(array $pendencias): array
    {
        return collect($pendencias)->pluck('codigo')->all();
    }

    public function test_controle_interno_nao_tem_pendencias(): void
    {
        $config = PontoConfiguracao::factory()->create();

        $this->assertSame([], $this->conformidade()->pendencias($config));
    }

    public function test_rep_p_lista_todas_as_pendencias_com_como_resolver(): void
    {
        $config = PontoConfiguracao::factory()->make(['modo' => ModoPonto::RepP]);

        $pendencias = $this->conformidade()->pendencias($config);

        $this->assertEqualsCanonicalizing(
            ['registro_inpi', 'atestado_tecnico', 'assinatura_icp_brasil', 'afd_oficial', 'aej_oficial', 'anexo_ix'],
            $this->codigos($pendencias),
        );

        foreach ($pendencias as $pendencia) {
            $this->assertNotSame('', trim($pendencia['como_resolver']), "Pendência {$pendencia['codigo']} sem orientação.");
        }
    }

    public function test_rep_p_continua_bloqueado_mesmo_com_tudo_do_cliente_preenchido_porque_falta_o_desenvolvedor(): void
    {
        $config = PontoConfiguracao::factory()->make([
            'modo' => ModoPonto::RepP,
            'registro_inpi' => 'BR 51 2024 000000-0',
            'atestado_tecnico_path' => 'ponto/atestado.pdf',
            'atestado_tecnico_data' => '2026-09-01',
            'atestado_assinatura_conferida' => true,
        ]);

        $codigos = $this->codigos($this->conformidade()->pendencias($config));

        $this->assertEqualsCanonicalizing(['assinatura_icp_brasil', 'afd_oficial', 'aej_oficial', 'anexo_ix'], $codigos);
    }

    public function test_atestado_enviado_sem_conferir_assinatura_gera_pendencia_propria(): void
    {
        $config = PontoConfiguracao::factory()->make([
            'modo' => ModoPonto::RepP,
            'registro_inpi' => 'BR 51 2024 000000-0',
            'atestado_tecnico_path' => 'ponto/atestado.pdf',
            'atestado_tecnico_data' => '2026-09-01',
            'atestado_assinatura_conferida' => false,
        ]);

        $this->assertContains('atestado_assinatura', $this->codigos($this->conformidade()->pendencias($config)));
    }

    public function test_hora_legal_desativada_e_pendencia_do_rep_p(): void
    {
        $this->usarHoraLegal(null, verifica: false);
        $config = PontoConfiguracao::factory()->make(['modo' => ModoPonto::RepP]);

        $this->assertContains('hora_legal', $this->codigos($this->conformidade()->pendencias($config)));
    }

    public function test_rep_a_exige_acordo_coletivo_vigente(): void
    {
        $semAcordo = PontoConfiguracao::factory()->make(['modo' => ModoPonto::RepA]);
        $vencido = PontoConfiguracao::factory()->make(['modo' => ModoPonto::RepA, 'acordo_coletivo_referencia' => 'ACT 2025', 'acordo_coletivo_vigencia_fim' => '2026-06-30']);
        $vigente = PontoConfiguracao::factory()->make(['modo' => ModoPonto::RepA, 'acordo_coletivo_referencia' => 'ACT 2026', 'acordo_coletivo_vigencia_fim' => '2027-06-30']);

        $this->assertContains('acordo_coletivo', $this->codigos($this->conformidade()->pendencias($semAcordo)));
        $this->assertContains('acordo_coletivo', $this->codigos($this->conformidade()->pendencias($vencido)));
        $this->assertNotContains('acordo_coletivo', $this->codigos($this->conformidade()->pendencias($vigente)));
        // O REP-A também depende dos arquivos oficiais (desenvolvedor).
        $this->assertEqualsCanonicalizing(['afd_oficial', 'aej_oficial'], $this->codigos($this->conformidade()->pendencias($vigente)));
    }

    public function test_nao_e_possivel_salvar_modo_oficial_com_pendencias(): void
    {
        $config = PontoConfiguracao::factory()->create();

        try {
            $config->update(['modo' => ModoPonto::RepP]);
            $this->fail('Não pode ativar REP-P com pendências.');
        } catch (PontoException $e) {
            $this->assertStringContainsString('ainda não pode ser ativado', $e->getMessage());
            $this->assertStringContainsString('INPI', $e->getMessage());
        }

        $this->assertSame(ModoPonto::ControleInterno, $config->fresh()->modo);
    }

    public function test_modo_oficial_so_ativa_quando_todos_os_requisitos_sao_cumpridos(): void
    {
        config(['ponto.requisitos_desenvolvedor' => ['afd_oficial' => true, 'aej_oficial' => true, 'anexo_ix_validado' => true, 'assinatura_icp_brasil' => true]]);
        $this->app->instance(AssinadorPonto::class, new class implements AssinadorPonto
        {
            public function nome(): string
            {
                return 'Assinador de teste ICP-Brasil';
            }

            public function assina(): bool
            {
                return true;
            }

            public function assinar(string $conteudo): ?string
            {
                return base64_encode(hash('sha256', $conteudo, true));
            }
        });

        $config = PontoConfiguracao::factory()->create();
        $config->update([
            'modo' => ModoPonto::RepP,
            'registro_inpi' => 'BR 51 2024 000000-0',
            'atestado_tecnico_path' => 'ponto/atestado.pdf',
            'atestado_tecnico_data' => '2026-09-01',
            'atestado_assinatura_conferida' => true,
        ]);

        $this->assertSame(ModoPonto::RepP, $config->fresh()->modo);
        $this->assertSame([], $this->conformidade()->pendencias($config->fresh()));
    }

    public function test_marcar_o_ponto_nunca_e_bloqueado_por_perda_de_conformidade(): void
    {
        $unidade = $this->unidadeComPonto();
        // Estado "perdido": o banco diz REP-P, mas os requisitos não são mais cumpridos (alteração fora do model).
        DB::table('ponto_configuracoes')->where('unidade_id', $unidade->id)->update(['modo' => 'rep_p']);

        $registro = $this->registrar($this->funcionario($unidade), '2026-10-20 08:00:00');

        $this->assertSame(1, $registro->nsr);
        $codigos = collect($this->conformidade()->alertas(PontoConfiguracao::where('unidade_id', $unidade->id)->first()))->pluck('codigo')->all();
        $this->assertContains('conformidade_perdida', $codigos);
    }

    public function test_alerta_de_obrigatoriedade_acima_de_vinte_trabalhadores_em_controle_interno(): void
    {
        $unidade = $this->unidadeComPonto();
        $config = PontoConfiguracao::where('unidade_id', $unidade->id)->first();

        for ($i = 0; $i < 20; $i++) {
            $this->funcionario($unidade);
        }
        $this->assertNotContains('obrigatoriedade', collect($this->conformidade()->alertas($config))->pluck('codigo')->all());

        $this->funcionario($unidade);
        $this->assertContains('obrigatoriedade', collect($this->conformidade()->alertas($config))->pluck('codigo')->all());

        // Desligados não contam.
        $this->funcionario($unidade, ['data_desligamento' => '2026-01-31']);
        $this->assertCount(1, array_filter($this->conformidade()->alertas($config), fn ($a) => $a['codigo'] === 'obrigatoriedade'));
    }

    public function test_alerta_de_hora_legal_conta_marcacoes_sem_confirmacao(): void
    {
        $unidade = $this->unidadeComPonto();
        $func = $this->funcionario($unidade);
        $config = PontoConfiguracao::where('unidade_id', $unidade->id)->first();

        $this->registrar($func, '2026-10-20 08:00:00');
        $this->assertNotContains('hora_legal', collect($this->conformidade()->alertas($config))->pluck('codigo')->all());

        $this->usarHoraLegal(120);
        $this->registrar($func, '2026-10-20 12:00:00');
        $this->assertContains('hora_legal', collect($this->conformidade()->alertas($config))->pluck('codigo')->all());
    }

    public function test_configuracao_registra_mudancas_no_log_sem_poluir_com_o_sequenciador(): void
    {
        $unidade = $this->unidadeComPonto();
        $func = $this->funcionario($unidade);
        Activity::query()->delete();

        $this->registrar($func, '2026-10-20 08:00:00');
        $this->assertSame(0, Activity::where('log_name', 'ponto')->count(), 'Marcar o ponto não gera log de configuração.');

        PontoConfiguracao::where('unidade_id', $unidade->id)->first()->update(['exige_hora_legal' => false]);
        $this->assertSame(1, Activity::where('log_name', 'ponto')->count());
    }

    // ─── NTP ─────────────────────────────────────────────────────────

    public function test_interpreta_o_pacote_ntp_e_calcula_o_desvio(): void
    {
        $agora = 1_790_000_000.0; // segundos Unix
        $transmissao = (int) $agora + 2_208_988_800 + 40; // servidor 40 s à frente
        $pacote = str_repeat("\0", 40).pack('N', $transmissao).pack('N', 0);

        $this->assertSame(40, NtpHoraLegalProvider::desvioDaResposta($pacote, $agora - 0.1, $agora + 0.1));
        $this->assertNull(NtpHoraLegalProvider::desvioDaResposta('curto', $agora, $agora));
        $this->assertNull(NtpHoraLegalProvider::desvioDaResposta(str_repeat("\0", 48), $agora, $agora));
    }

    // ─── Comprovante ─────────────────────────────────────────────────

    private function usuarioDe(Funcionario $funcionario, string $papel = 'professor', array $permissoes = []): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        foreach ($permissoes as $permissao) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole($papel);
        $user->pessoas()->attach($funcionario->pessoa_id);
        session(['active_role' => $papel]);

        return $user;
    }

    public function test_comprovante_sem_assinador_sai_marcado_como_nao_assinado(): void
    {
        $unidade = $this->unidadeComPonto();
        $registro = $this->registrar($this->funcionario($unidade, ['pessoa_id' => Pessoa::factory()->create(['nome' => 'Marta Souza', 'cpf' => '111.222.333-44'])->id]), '2026-10-20 08:00:00');

        $dados = app(ComprovantePontoService::class)->dados($registro);

        $this->assertFalse($dados['assinado']);
        $this->assertSame(1, $dados['nsr']);
        $this->assertSame('12.345.678/0001-90', $dados['cnpj']);
        $this->assertSame('Marta Souza', $dados['trabalhador']);
        $this->assertSame('111.222.333-44', $dados['cpf']);
        $this->assertSame($registro->hash, $dados['hash']);

        $html = view('ponto.comprovante', ['comprovante' => $dados])->render();
        $this->assertStringContainsString('NÃO ASSINADO', $html);
        $this->assertStringContainsString($registro->hash, $html);
    }

    public function test_comprovante_em_pdf_so_para_o_dono_ou_quem_pode_ver_todos(): void
    {
        $unidade = $this->unidadeComPonto();
        $marta = $this->funcionario($unidade);
        $outro = $this->funcionario($unidade);
        $registro = $this->registrar($marta, '2026-10-20 08:00:00');
        Activity::query()->delete();

        $dono = $this->usuarioDe($marta);
        $resposta = $this->actingAs($dono)->get(route('ponto.comprovante', $registro));
        $resposta->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $resposta->headers->get('Content-Type'));
        $this->assertSame(0, Activity::where('log_name', 'ponto')->count(), 'Acesso ao próprio comprovante não é registrado.');

        $colega = $this->usuarioDe($outro);
        $this->actingAs($colega)->get(route('ponto.comprovante', $registro))->assertForbidden();

        $rh = $this->usuarioDe($this->funcionario($unidade), 'admin', ['View:PontoRegistro']);
        $this->actingAs($rh)->get(route('ponto.comprovante', $registro))->assertOk();
        $log = Activity::where('log_name', 'ponto')->first();
        $this->assertNotNull($log, 'Acesso a comprovante de outra pessoa fica registrado.');
        $this->assertSame($marta->id, $log->properties['funcionario_id']);
    }

    public function test_espelho_em_pdf_segue_a_mesma_regra_de_acesso(): void
    {
        $unidade = $this->unidadeComPonto();
        $marta = $this->funcionario($unidade);
        $outro = $this->funcionario($unidade);

        $this->actingAs($this->usuarioDe($marta))->get(route('ponto.espelho', [$marta, 2026, 10]))->assertOk();
        $this->actingAs($this->usuarioDe($outro))->get(route('ponto.espelho', [$marta, 2026, 10]))->assertForbidden();
        $this->actingAs($this->usuarioDe($this->funcionario($unidade), 'admin', ['VerEspelhoDeTodos:PontoRegistro']))->get(route('ponto.espelho', [$marta, 2026, 10]))->assertOk();
        $this->actingAs($this->usuarioDe($marta))->get(route('ponto.espelho', [$marta, 2026, 13]))->assertNotFound();
    }
}
