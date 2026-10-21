<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\InformeAnualMensalidades;
use App\Filament\Portal\Pages\InformeAnual;
use App\Models\Banco;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\Fatura;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\TransacaoBancaria;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\InformeAnualMensalidadesService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Informe anual de mensalidades pagas (Onda 42): regime de caixa no ano-calendário, parte estimada de instrução,
 * repartição entre responsáveis, acesso da família e da equipe, PDF individual e em lote.
 *
 * "Hoje" é 21/10/2026; o informe de referência é o de 2026.
 */
class InformeAnualMensalidadesTest extends TestCase
{
    use RefreshDatabase;

    private Turma $turma;

    private Banco $banco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00:00'));

        $unidade = Unidade::create(['nome' => 'Sede', 'cnpj' => '12345678000190']);
        $curso = Curso::create(['nome_externo' => 'Fundamental', 'nome_interno' => 'Fundamental', 'unidade_id' => $unidade->id]);
        $serie = Serie::create(['nome' => '5º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $this->turma = Turma::create(['nome' => '5º Ano A', 'serie_id' => $serie->id, 'periodo_letivo_id' => $periodo->id]);
        $this->banco = Banco::create(['nome' => 'Banco da Escola']);
    }

    private function servico(): InformeAnualMensalidadesService
    {
        return app(InformeAnualMensalidadesService::class);
    }

    /**
     * Matrícula com contrato e responsáveis (nome => percentual).
     *
     * @param  array<string, float>  $responsaveis
     * @return array{matricula: Matricula, contrato: Contrato}
     */
    private function aluno(string $nome = 'Ana Lima', array $responsaveis = ['Carla Lima' => 100], ?Turma $turma = null, ?string $cpf = null): array
    {
        $matricula = Matricula::create([
            'pessoa_id' => Pessoa::create(['nome' => $nome, 'cpf' => $cpf])->id,
            'turma_id' => ($turma ?? $this->turma)->id,
            'situacao' => 'ativa',
        ]);
        $contrato = Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => 12000]);

        foreach ($responsaveis as $responsavel => $percentual) {
            $contrato->responsaveisFinanceiros()->create(['pessoa_id' => Pessoa::create(['nome' => $responsavel])->id, 'percentual' => $percentual]);
        }

        return ['matricula' => $matricula, 'contrato' => $contrato];
    }

    /**
     * Fatura com itens (descrição => valor) e os pagamentos (data => valor).
     *
     * @param  array<string, float>  $itens
     * @param  array<string, float>  $pagamentos
     */
    private function fatura(Contrato $contrato, array $itens, array $pagamentos, float $descontoPercentual = 0): Fatura
    {
        $fatura = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-03-10', 'status' => 'pago']);

        foreach ($itens as $descricao => $valor) {
            ItemFatura::create([
                'fatura_id' => $fatura->id,
                'descricao' => $descricao,
                'valor_unitario' => $valor,
                'quantidade' => 1,
                'desconto' => $descontoPercentual,
                'tipo_desconto' => 'percentual',
            ]);
        }

        foreach ($pagamentos as $data => $valor) {
            TransacaoBancaria::create(['banco_id' => $this->banco->id, 'fatura_id' => $fatura->id, 'tipo' => 'entrada', 'valor' => $valor, 'data_transacao' => $data]);
        }

        return $fatura;
    }

    // ─── Cálculo ─────────────────────────────────────────────────────

    public function test_soma_so_o_que_foi_recebido_no_ano_calendario_mes_a_mes(): void
    {
        ['contrato' => $contrato] = $this->aluno();
        $this->fatura($contrato, ['Parcela 12 de 12 (2025)' => 1000], ['2025-12-30' => 1000]);
        $this->fatura($contrato, ['Parcela 1 de 12' => 1000], ['2026-01-10' => 1000]);
        $this->fatura($contrato, ['Parcela 2 de 12' => 1000], ['2026-03-15' => 1000]);
        $this->fatura($contrato, ['Parcela 3 de 12' => 1000], ['2026-03-28' => 500]); // pagamento parcial

        $informe = $this->servico()->gerar(2026)->first();

        $this->assertSame(2500.0, $informe['total_pago']);
        $this->assertSame(1000.0, $informe['por_mes'][1]);
        $this->assertSame(1500.0, $informe['por_mes'][3]);
        $this->assertSame(0.0, $informe['por_mes'][12]);
        $this->assertCount(3, $informe['pagamentos']);
        $this->assertSame('12.345.678/0001-90', $informe['escola']['cnpj']);
    }

    public function test_estima_a_parte_de_instrucao_pelos_itens_da_fatura(): void
    {
        ['contrato' => $contrato] = $this->aluno();
        $this->fatura($contrato, ['Parcela 1 de 12' => 900, 'Material didático' => 100], ['2026-02-10' => 1000]);
        $this->fatura($contrato, ['Parcela 2 de 12' => 900, 'Uniforme' => 100], ['2026-03-10' => 500]); // parcial: proporção mantida
        $this->fatura($contrato, ['Parcela 3 de 12' => 1000, 'Lanche' => 0], ['2026-04-10' => 900], descontoPercentual: 10); // desconto de bolsa

        $informe = $this->servico()->gerar(2026)->first();

        $this->assertSame(2400.0, $informe['total_pago']);
        $this->assertSame(900.0 + 450.0 + 900.0, $informe['total_dedutivel']);
        $this->assertSame(150.0, $informe['total_nao_dedutivel']);
    }

    public function test_ignora_saidas_e_transacoes_sem_fatura(): void
    {
        ['contrato' => $contrato] = $this->aluno();
        $fatura = $this->fatura($contrato, ['Parcela 1 de 12' => 1000], ['2026-02-10' => 1000]);
        TransacaoBancaria::create(['banco_id' => $this->banco->id, 'fatura_id' => $fatura->id, 'tipo' => 'saida', 'valor' => 1000, 'data_transacao' => '2026-02-11']); // estorno
        TransacaoBancaria::create(['banco_id' => $this->banco->id, 'fatura_id' => null, 'tipo' => 'entrada', 'valor' => 5000, 'data_transacao' => '2026-02-12']);

        $this->assertSame(1000.0, $this->servico()->gerar(2026)->first()['total_pago']);
    }

    public function test_reparte_entre_responsaveis_pelo_percentual_e_formata_cpf(): void
    {
        ['contrato' => $contrato] = $this->aluno('Ana Lima', ['Carla Lima' => 60, 'Davi Lima' => 40], cpf: '12345678901');
        $this->fatura($contrato, ['Parcela 1 de 12' => 2500], ['2026-02-10' => 2500]);

        $informe = $this->servico()->gerar(2026)->first();

        $this->assertSame('123.456.789-01', $informe['aluno']['cpf']);
        $this->assertSame([1500.0, 1000.0], array_column($informe['responsaveis'], 'valor'));
    }

    public function test_aluno_sem_pagamento_no_ano_nao_aparece(): void
    {
        $this->aluno();

        $this->assertCount(0, $this->servico()->gerar(2026));
    }

    // ─── Acesso ──────────────────────────────────────────────────────

    /**
     * Usuário da família ligado ao aluno (como responsável com dependente).
     */
    private function familiaDe(Matricula $matricula): User
    {
        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);
        $pessoa = Pessoa::create(['nome' => 'Familiar '.uniqid()]);
        $user = User::factory()->create(['activated_at' => now(), 'email_verified_at' => now()]);
        $pessoa->users()->save($user);
        $user->assignRole('responsavel');
        $pessoa->alunos()->attach($matricula->pessoa_id);

        return $user;
    }

    private function equipe(string $papel = 'secretaria', array $permissoes = ['GerarInformeAnual:Fatura']): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $role = Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        foreach ($permissoes as $permissao) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole($papel);
        session(['active_role' => $papel]);

        return $user;
    }

    public function test_familia_ve_os_proprios_alunos_inclusive_como_responsavel_financeiro(): void
    {
        ['matricula' => $ana] = $this->aluno('Ana Lima');
        ['matricula' => $bia, 'contrato' => $contratoBia] = $this->aluno('Bia Souza', []);
        ['matricula' => $outro] = $this->aluno('Outro Aluno');

        $familia = $this->familiaDe($ana);
        // A família também é responsável financeira do contrato da Bia (sem ser "dependente").
        $contratoBia->responsaveisFinanceiros()->create(['pessoa_id' => $familia->pessoas()->first()->id, 'percentual' => 100]);

        $ids = $this->servico()->matriculasDaFamilia($familia)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$ana->id, $bia->id], $ids);
        $this->assertTrue($this->servico()->podeVer($familia, $ana));
        $this->assertFalse($this->servico()->podeVer($familia, $outro));
        $this->assertTrue($this->servico()->podeVer($this->equipe(), $outro));
    }

    public function test_pdf_do_aluno_para_a_familia_e_bloqueio_para_outra(): void
    {
        ['matricula' => $ana, 'contrato' => $contrato] = $this->aluno('Ana Lima');
        ['matricula' => $outro] = $this->aluno('Outro Aluno');
        $this->fatura($contrato, ['Parcela 1 de 12' => 1000], ['2026-02-10' => 1000]);
        $familia = $this->familiaDe($ana);

        $resposta = $this->actingAs($familia)->get(route('informe-anual.aluno', ['ano' => 2026, 'matricula' => $ana->id]));
        $resposta->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $resposta->headers->get('Content-Type'));

        $this->actingAs($familia)->get(route('informe-anual.aluno', ['ano' => 2026, 'matricula' => $outro->id]))->assertForbidden();
        $this->actingAs($familia)->get(route('informe-anual.aluno', ['ano' => 2027, 'matricula' => $ana->id]))->assertNotFound();
        $this->actingAs($familia)->get(route('informe-anual.lote', ['ano' => 2026]))->assertForbidden();

        // Aluno sem pagamento no ano: o PDF sai com o aviso, não com erro.
        $this->actingAs($familia)->get(route('informe-anual.aluno', ['ano' => 2025, 'matricula' => $ana->id]))->assertOk();
    }

    public function test_equipe_emite_individual_e_em_lote_e_a_emissao_fica_no_log(): void
    {
        ['matricula' => $ana, 'contrato' => $contrato] = $this->aluno('Ana Lima');
        $this->fatura($contrato, ['Parcela 1 de 12' => 1000], ['2026-02-10' => 1000]);
        $secretaria = $this->equipe();
        Activity::query()->delete();

        $this->actingAs($secretaria)->get(route('informe-anual.aluno', ['ano' => 2026, 'matricula' => $ana->id]))->assertOk();
        $this->actingAs($secretaria)->get(route('informe-anual.lote', ['ano' => 2026, 'turma' => $this->turma->id]))->assertOk();

        $logs = Activity::where('log_name', 'informe_anual')->get();
        $this->assertCount(2, $logs);
        $this->assertSame(2026, $logs->first()->properties['ano']);
        $this->assertStringNotContainsString('1000', $logs->toJson(), 'O log não guarda valores.');
    }

    public function test_lote_acima_do_limite_pede_para_filtrar(): void
    {
        config(['financeiro.informe_anual.limite_lote' => 1]);
        foreach (['Ana Lima', 'Bia Souza'] as $nome) {
            ['contrato' => $contrato] = $this->aluno($nome);
            $this->fatura($contrato, ['Parcela 1 de 12' => 1000], ['2026-02-10' => 1000]);
        }

        $this->actingAs($this->equipe())->get(route('informe-anual.lote', ['ano' => 2026]))->assertStatus(422);
    }

    // ─── Telas ───────────────────────────────────────────────────────

    public function test_portal_mostra_o_informe_do_ano_escolhido(): void
    {
        ['matricula' => $ana, 'contrato' => $contrato] = $this->aluno('Ana Lima');
        $this->fatura($contrato, ['Parcela 1 de 12' => 1000], ['2026-02-10' => 1000]);
        ['contrato' => $contratoOutro] = $this->aluno('Outro Aluno');
        $this->fatura($contratoOutro, ['Parcela 1 de 12' => 777], ['2026-02-10' => 777]);
        $familia = $this->familiaDe($ana);

        Livewire::actingAs($familia)
            ->test(InformeAnual::class)
            ->set('ano', 2026)
            ->assertSee('Ana Lima')
            ->assertSee('R$ 1.000,00')
            ->assertDontSee('Outro Aluno')
            ->set('ano', 2024)
            ->assertSee('Não encontramos pagamentos');
    }

    public function test_tela_da_equipe_filtra_por_turma_e_nome_e_nega_quem_nao_tem_permissao(): void
    {
        $outraTurma = Turma::create(['nome' => '6º Ano A', 'serie_id' => $this->turma->serie_id, 'periodo_letivo_id' => $this->turma->periodo_letivo_id]);
        ['contrato' => $c1] = $this->aluno('Ana Lima');
        ['contrato' => $c2] = $this->aluno('Bruno Dias', turma: $outraTurma);
        $this->fatura($c1, ['Parcela 1 de 12' => 1000], ['2026-02-10' => 1000]);
        $this->fatura($c2, ['Parcela 1 de 12' => 1000], ['2026-02-10' => 1000]);

        Livewire::actingAs($this->equipe())
            ->test(InformeAnualMensalidades::class)
            ->set('ano', 2026)
            ->assertSee('Ana Lima')
            ->assertSee('Bruno Dias')
            ->set('turmaId', $outraTurma->id)
            ->assertDontSee('Ana Lima')
            ->set('turmaId', null)
            ->set('busca', 'Ana')
            ->assertSee('Ana Lima')
            ->assertDontSee('Bruno Dias');

        Livewire::actingAs($this->equipe('professor', []))->test(InformeAnualMensalidades::class)->assertForbidden();
    }
}
