<?php

namespace Tests\Feature;

use App\Enums\StatusAcordoInadimplencia;
use App\Filament\Resources\AcordoInadimplencias\Pages\ListAcordoInadimplencias;
use App\Models\AcordoInadimplencia;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use App\Services\AcordoInadimplenciaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AcordoInadimplenciaTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComo(string $roleName = 'admin'): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $perms = [
            'ViewAny:AcordoInadimplencia',
            'View:AcordoInadimplencia',
            'Create:AcordoInadimplencia',
            'Update:AcordoInadimplencia',
            'Delete:AcordoInadimplencia',
            'DeleteAny:AcordoInadimplencia',
            'Aprovar:AcordoInadimplencia',
        ];

        foreach ($perms as $p) {
            $perm = Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $role->givePermissionTo($perm);
        }

        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $user->assignRole($roleName);
        $this->actingAs($user);
        session(['active_role' => $roleName]);

        return $user;
    }

    private function criarMatriculaComResponsavel(): array
    {
        $aluno = Pessoa::create([
            'nome' => 'Aluno Inadimplente Teste',
            'data_nascimento' => '2012-05-10',
        ]);

        $responsavel = Pessoa::create([
            'nome' => 'Responsável Financeiro Teste',
            'cpf' => '12345678901',
            'telefone' => '11988887777',
            'email' => 'responsavel@exemplo.com',
        ]);

        $unidade = Unidade::create(['nome' => 'Unidade Teste', 'situacao_funcionamento' => '1']);
        $curso = Curso::create(['unidade_id' => $unidade->id, 'nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'EF']);
        $serie = Serie::create(['curso_id' => $curso->id, 'nome' => '7º Ano', 'sistema_avaliacao' => 'Nota']);
        $turno = Turno::create(['nome' => 'Manhã', 'hora_inicio' => '07:00:00', 'hora_fim' => '12:00:00']);
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'ano' => 2026, 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);

        $turma = Turma::create([
            'nome' => 'Turma 701',
            'serie_id' => $serie->id,
            'turno_id' => $turno->id,
            'vagas_maximas' => 30,
        ]);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'serie_id' => $serie->id,
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $periodo->id,
            'situacao' => 'ativa',
        ]);

        return [$matricula, $responsavel];
    }

    public function test_simulacao_de_acordo_calcula_valores_e_parcelas_corretamente(): void
    {
        $service = app(AcordoInadimplenciaService::class);

        // R$ 3.000 original + R$ 300 juros/multa, com 100% de desconto sobre encargos e entrada de R$ 600 em 4x
        $simulacao = $service->simularAcordo(
            valorOriginal: 3000.00,
            valorMulta: 100.00,
            valorJuros: 200.00,
            percentualDesconto: 100.0,
            valorEntrada: 600.00,
            quantidadeParcelas: 4,
            diaVencimento: 10,
            primeiroVencimento: '2026-11-10'
        );

        // Desconto = 300 (100% dos encargos)
        $this->assertEquals(300.00, $simulacao['valor_desconto']);
        // Valor final = 3000
        $this->assertEquals(3000.00, $simulacao['valor_total_acordo']);
        // Saldo a parcelar = 3000 - 600 = 2400 / 4 parcelas = 600 cada
        $this->assertEquals(600.00, $simulacao['valor_parcela']);
        $this->assertCount(5, $simulacao['cronograma_parcelas']); // 1 entrada + 4 parcelas
    }

    public function test_criacao_de_acordo_persiste_parcelas_e_minuta_juridica(): void
    {
        $user = $this->autenticarComo('admin');
        [$matricula, $responsavel] = $this->criarMatriculaComResponsavel();

        $service = app(AcordoInadimplenciaService::class);

        $acordo = $service->criarAcordo([
            'matricula_id' => $matricula->id,
            'responsavel_pessoa_id' => $responsavel->id,
            'valor_original_total' => 2000.00,
            'valor_multa_original' => 100.00,
            'valor_juros_original' => 100.00,
            'percentual_desconto_concedido' => 50.0,
            'valor_entrada' => 500.00,
            'quantidade_parcelas' => 3,
            'dia_vencimento_parcelas' => 10,
            'primeiro_vencimento' => '2026-11-10',
        ], $user);

        $this->assertNotNull($acordo->id);
        $this->assertEquals(StatusAcordoInadimplencia::AguardandoAceite, $acordo->status);
        $this->assertStringContainsString('Art. 784, inciso III', $acordo->termo_confissao_texto);
        $this->assertStringContainsString($responsavel->nome, $acordo->termo_confissao_texto);

        // 1 entrada + 3 parcelas = 4 registros
        $this->assertCount(4, $acordo->parcelas);
    }

    public function test_aceite_online_do_acordo_pela_familia(): void
    {
        $user = $this->autenticarComo('admin');
        [$matricula, $responsavel] = $this->criarMatriculaComResponsavel();

        $service = app(AcordoInadimplenciaService::class);

        $acordo = $service->criarAcordo([
            'matricula_id' => $matricula->id,
            'responsavel_pessoa_id' => $responsavel->id,
            'valor_original_total' => 1500.00,
            'quantidade_parcelas' => 2,
            'primeiro_vencimento' => '2026-11-10',
        ], $user);

        // Página pública carrega com sucesso
        $this->get(route('acordo.publico.show', ['token' => $acordo->token_publico]))
            ->assertSuccessful()
            ->assertSee($acordo->codigo)
            ->assertSee($responsavel->nome);

        // Aceite do acordo
        $response = $this->post(route('acordo.publico.aceitar', ['token' => $acordo->token_publico]), [
            'concordo' => '1',
        ]);

        $response->assertRedirect(route('acordo.publico.show', ['token' => $acordo->token_publico]));

        $acordo->refresh();
        $this->assertEquals(StatusAcordoInadimplencia::Ativo, $acordo->status);
        $this->assertNotNull($acordo->aceito_em);
    }

    public function test_baixa_de_parcelas_e_quitacao_integral_do_acordo(): void
    {
        $user = $this->autenticarComo('admin');
        [$matricula, $responsavel] = $this->criarMatriculaComResponsavel();

        $service = app(AcordoInadimplenciaService::class);

        $acordo = $service->criarAcordo([
            'matricula_id' => $matricula->id,
            'responsavel_pessoa_id' => $responsavel->id,
            'valor_original_total' => 1000.00,
            'valor_entrada' => 0.00,
            'quantidade_parcelas' => 2,
            'primeiro_vencimento' => '2026-11-10',
        ], $user);

        $parcelas = $acordo->parcelas;
        $this->assertCount(2, $parcelas);

        // Paga a primeira parcela
        $service->registrarPagamentoParcela($parcelas[0], (float) $parcelas[0]->valor, 'pix');
        $acordo->refresh();
        $this->assertNotEquals(StatusAcordoInadimplencia::Cumprido, $acordo->status);

        // Paga a segunda parcela
        $service->registrarPagamentoParcela($parcelas[1], (float) $parcelas[1]->valor, 'pix');
        $acordo->refresh();
        $this->assertEquals(StatusAcordoInadimplencia::Cumprido, $acordo->status);
    }

    public function test_listagem_de_acordos_no_filament_carrega_com_sucesso(): void
    {
        $user = $this->autenticarComo('admin');
        [$matricula, $responsavel] = $this->criarMatriculaComResponsavel();

        $acordo = AcordoInadimplencia::create([
            'codigo' => AcordoInadimplencia::gerarCodigo(),
            'matricula_id' => $matricula->id,
            'responsavel_pessoa_id' => $responsavel->id,
            'criado_por_user_id' => $user->id,
            'valor_original_total' => 1200.00,
            'valor_total_acordo' => 1200.00,
            'quantidade_parcelas' => 3,
            'valor_parcela' => 400.00,
            'primeiro_vencimento' => '2026-11-10',
            'token_publico' => AcordoInadimplencia::gerarTokenPublico(),
            'status' => StatusAcordoInadimplencia::AguardandoAceite,
        ]);

        Livewire::test(ListAcordoInadimplencias::class)
            ->assertSuccessful()
            ->assertSee($acordo->codigo);
    }

    /**
     * @param  array<int, array{numero_parcela: int, vencimento: string}>  $cronograma
     * @return list<string>
     */
    private function vencimentosDasParcelas(array $cronograma): array
    {
        return array_values(array_map(
            fn (array $p): string => $p['vencimento'],
            array_filter($cronograma, fn (array $p): bool => $p['numero_parcela'] > 0),
        ));
    }

    public function test_cronograma_com_primeiro_vencimento_no_fim_do_mes_nao_pula_nem_repete_meses(): void
    {
        $service = app(AcordoInadimplenciaService::class);

        $simulacao = $service->simularAcordo(1200.0, 0.0, 0.0, 0.0, 0.0, 4, 10, '2027-01-31');

        // Antes: 31/01, 10/03, 10/03, 10/05 (fevereiro pulado, março repetido).
        $this->assertSame(
            ['2027-01-31', '2027-02-10', '2027-03-10', '2027-04-10'],
            $this->vencimentosDasParcelas($simulacao['cronograma_parcelas']),
        );
    }

    public function test_cronograma_cruza_fevereiro_e_virada_de_ano_com_dia_28(): void
    {
        $service = app(AcordoInadimplenciaService::class);

        $simulacao = $service->simularAcordo(1200.0, 0.0, 0.0, 0.0, 0.0, 5, 28, '2026-12-29');

        $this->assertSame(
            ['2026-12-29', '2027-01-28', '2027-02-28', '2027-03-28', '2027-04-28'],
            $this->vencimentosDasParcelas($simulacao['cronograma_parcelas']),
        );
    }

    public function test_primeira_parcela_vence_na_data_informada_e_as_demais_no_dia_de_vencimento(): void
    {
        $service = app(AcordoInadimplenciaService::class);

        $simulacao = $service->simularAcordo(900.0, 0.0, 0.0, 0.0, 0.0, 3, 10, '2026-11-20');

        // O termo imprime "primeiro vencimento em 20/11/2026": a 1ª parcela não pode vencer em 10/11.
        $this->assertSame('2026-11-20', $simulacao['primeiro_vencimento']);
        $this->assertSame(
            ['2026-11-20', '2026-12-10', '2027-01-10'],
            $this->vencimentosDasParcelas($simulacao['cronograma_parcelas']),
        );
    }

    public function test_sem_primeiro_vencimento_o_padrao_e_o_proximo_mes_mesmo_no_dia_31(): void
    {
        $this->travelTo(Carbon::parse('2027-01-31 10:00:00'));
        $service = app(AcordoInadimplenciaService::class);

        $simulacao = $service->simularAcordo(1200.0, 0.0, 0.0, 0.0, 0.0, 3, 10, null);

        // Antes: 31/01 + 1 mês estourava para março.
        $this->assertSame('2027-02-10', $simulacao['primeiro_vencimento']);
        $this->assertSame(
            ['2027-02-10', '2027-03-10', '2027-04-10'],
            $this->vencimentosDasParcelas($simulacao['cronograma_parcelas']),
        );
    }

    public function test_parcelas_persistidas_seguem_o_cronograma_sem_meses_repetidos(): void
    {
        $user = $this->autenticarComo('admin');
        [$matricula, $responsavel] = $this->criarMatriculaComResponsavel();

        $acordo = app(AcordoInadimplenciaService::class)->criarAcordo([
            'matricula_id' => $matricula->id,
            'responsavel_pessoa_id' => $responsavel->id,
            'valor_original_total' => 900.00,
            'quantidade_parcelas' => 3,
            'dia_vencimento_parcelas' => 10,
            'primeiro_vencimento' => '2027-01-30',
        ], $user);

        $datas = $acordo->parcelas->map(fn ($p) => $p->data_vencimento->toDateString())->all();

        $this->assertSame(['2027-01-30', '2027-02-10', '2027-03-10'], $datas);
        $this->assertSame('2027-01-30', $acordo->primeiro_vencimento->toDateString());
    }

    public function test_termo_de_confissao_imprime_valores_formatados_sem_codigo_php_literal(): void
    {
        $user = $this->autenticarComo('admin');
        [$matricula, $responsavel] = $this->criarMatriculaComResponsavel();

        $acordo = app(AcordoInadimplenciaService::class)->criarAcordo([
            'matricula_id' => $matricula->id,
            'responsavel_pessoa_id' => $responsavel->id,
            'valor_original_total' => 2000.00,
            'valor_multa_original' => 100.00,
            'valor_juros_original' => 100.00,
            'percentual_desconto_concedido' => 50.0,
            'valor_entrada' => 500.00,
            'quantidade_parcelas' => 3,
            'dia_vencimento_parcelas' => 10,
            'primeiro_vencimento' => '2026-11-10',
        ], $user);

        $termo = $acordo->termo_confissao_texto;

        // 2.200 bruto - 100 de desconto (50% dos encargos) = 2.100; (2.100 - 500 de entrada) / 3 = 533,33.
        $this->assertStringContainsString('3 parcela(s) no valor de R$ 533,33;', $termo);
        $this->assertStringContainsString('Entrada: R$ 500,00;', $termo);
        $this->assertStringContainsString('com primeiro vencimento em 10/11/2026.', $termo);
        $this->assertStringContainsString('fixando-se o valor final e consolidado do acordo em R$ 2.100,00', $termo);

        $this->assertStringNotContainsString('number_format', $termo);
        $this->assertStringNotContainsString('{$', $termo);
        $this->assertDoesNotMatchRegularExpression('/\{[^}]*\(/', $termo);
    }

    public function test_termo_sem_entrada_mostra_entrada_zerada(): void
    {
        $user = $this->autenticarComo('admin');
        [$matricula, $responsavel] = $this->criarMatriculaComResponsavel();

        $acordo = app(AcordoInadimplenciaService::class)->criarAcordo([
            'matricula_id' => $matricula->id,
            'responsavel_pessoa_id' => $responsavel->id,
            'valor_original_total' => 1500.00,
            'quantidade_parcelas' => 2,
            'primeiro_vencimento' => '2026-11-10',
        ], $user);

        $this->assertStringContainsString('2 parcela(s) no valor de R$ 750,00;', $acordo->termo_confissao_texto);
        $this->assertStringContainsString('Entrada: R$ 0,00;', $acordo->termo_confissao_texto);
    }

    /**
     * Reproduz um acordo criado antes da correção: o texto gravado traz o código PHP literal.
     */
    private function criarAcordoComTermoLegadoDefeituoso(): AcordoInadimplencia
    {
        $user = $this->autenticarComo('admin');
        [$matricula, $responsavel] = $this->criarMatriculaComResponsavel();

        $acordo = app(AcordoInadimplenciaService::class)->criarAcordo([
            'matricula_id' => $matricula->id,
            'responsavel_pessoa_id' => $responsavel->id,
            'valor_original_total' => 1500.00,
            'quantidade_parcelas' => 2,
            'primeiro_vencimento' => '2026-11-10',
        ], $user);

        $acordo->update(['termo_confissao_texto' => "Entrada: R$ {number_format((float) 0.00, 2, ',', '.')};"]);

        return $acordo->fresh();
    }

    public function test_pagina_publica_mostra_termo_regerado_para_acordo_criado_com_texto_defeituoso(): void
    {
        $acordo = $this->criarAcordoComTermoLegadoDefeituoso();

        $this->get(route('acordo.publico.show', ['token' => $acordo->token_publico]))
            ->assertSuccessful()
            ->assertSee('2 parcela(s) no valor de R$ 750,00;', false)
            ->assertDontSee('number_format', false);
    }

    public function test_aceite_grava_o_termo_corrigido_e_o_congela(): void
    {
        $acordo = $this->criarAcordoComTermoLegadoDefeituoso();
        $service = app(AcordoInadimplenciaService::class);

        $this->post(route('acordo.publico.aceitar', ['token' => $acordo->token_publico]), ['concordo' => '1'])
            ->assertRedirect();

        $aceito = $acordo->fresh();
        $this->assertSame(StatusAcordoInadimplencia::Ativo, $aceito->status);
        $this->assertStringContainsString('2 parcela(s) no valor de R$ 750,00;', $aceito->termo_confissao_texto);
        $this->assertStringNotContainsString('number_format', $aceito->termo_confissao_texto);

        // Depois do aceite o texto vale como gravado, mesmo que os dados do acordo mudem.
        $aceito->update(['valor_parcela' => 999.99]);
        $this->assertSame($aceito->termo_confissao_texto, $service->termoParaExibicao($aceito->fresh()));
        $this->assertStringContainsString('R$ 750,00;', $service->termoParaExibicao($aceito->fresh()));
    }

    public function test_antes_do_aceite_o_termo_acompanha_alteracoes_nos_valores(): void
    {
        $acordo = $this->criarAcordoComTermoLegadoDefeituoso();
        $acordo->update(['valor_parcela' => 612.34]);

        $termo = app(AcordoInadimplenciaService::class)->termoParaExibicao($acordo->fresh());

        $this->assertStringContainsString('2 parcela(s) no valor de R$ 612,34;', $termo);
    }
}
