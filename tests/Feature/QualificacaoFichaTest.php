<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\CreateInteressado;
use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Filament\Resources\Interessados\Pages\ListInteressados;
use App\Models\Curso;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\InteressadoQualificacao;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Qualificacao\QualificacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Roteiro de qualificação do lead (SDR, Lote 1) nas telas: aba da ficha, ação de mover para a etapa sugerida, coluna e
 * filtro da listagem e o comando que reavalia os leads já cadastrados.
 */
class QualificacaoFichaTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private User $admin;

    private StatusInteressado $novo;

    private OrigemInteressado $origem;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['activated_at' => now()]);
        $this->admin->assignRole('super_admin');

        $this->novo = StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $this->origem = OrigemInteressado::create(['nome' => 'Site']);
    }

    private function lead(): Interessado
    {
        return Interessado::create([
            'pessoa_id' => Pessoa::factory()->create()->id,
            'usuario_id' => $this->admin->id,
            'origem_interessado_id' => $this->origem->id,
            'status_interessado_id' => $this->novo->id,
        ]);
    }

    private function serie(): Serie
    {
        $curso = Curso::first() ?? Curso::create([
            'nome_externo' => 'Ensino Fundamental',
            'nome_interno' => 'Ensino Fundamental',
            'unidade_id' => Unidade::create(['nome' => 'Unidade Sede'])->id,
        ]);

        return Serie::create(['nome' => '2º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
    }

    /** Responde todo o gate do roteiro. */
    private function qualificar(Interessado $lead): void
    {
        InteressadoDependente::create([
            'interessado_id' => $lead->id,
            'nome_crianca' => 'Lucas',
            'serie_id' => $this->serie()->id,
            'data_nascimento' => '2019-05-10',
            'turno_preferencia' => 'Manhã',
            'situacao_atual' => 'primeira_escola',
        ]);

        $servico = app(QualificacaoService::class);

        foreach (['bairro' => 'Centro', 'motivo_busca' => 'primeira_escola', 'decisor_tipo' => 'sozinho', 'decisor_vai_visita' => 'sim', 'prazo_decisao' => 'este_mes'] as $chave => $valor) {
            $servico->registrar($lead->fresh(), $chave, $valor, InteressadoQualificacao::FONTE_CONSULTOR);
        }
    }

    public function test_aba_do_roteiro_aparece_na_edicao_e_nao_na_criacao(): void
    {
        $lead = $this->lead();

        Livewire::actingAs($this->admin)
            ->test(EditInteressado::class, ['record' => $lead->id])
            ->assertSee('Roteiro de qualificação')
            ->assertSee('O que a família disse')
            ->assertSee('Quem decide');

        Livewire::actingAs($this->admin)
            ->test(CreateInteressado::class)
            ->assertDontSee('Roteiro de qualificação');
    }

    public function test_salvar_a_aba_grava_as_respostas_com_procedencia_e_reavalia_o_lead(): void
    {
        $lead = $this->lead();
        InteressadoDependente::create(['interessado_id' => $lead->id, 'nome_crianca' => 'Lucas']);

        Livewire::actingAs($this->admin)
            ->test(EditInteressado::class, ['record' => $lead->id])
            ->fillForm(['qualificacao' => ['bairro' => 'Savassi', 'decisor_tipo' => 'com_conjuge', 'escolas_em_comparacao' => ['Colégio Alfa']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $qualificacao = $lead->qualificacao()->first();

        $this->assertSame('Savassi', $qualificacao->bairro);
        $this->assertSame('com_conjuge', $qualificacao->decisor_tipo);
        $this->assertSame(['Colégio Alfa'], $qualificacao->escolas_em_comparacao);
        $this->assertSame('consultor', $qualificacao->campos['bairro']['fonte']);
        $this->assertSame($this->admin->id, $qualificacao->campos['decisor_tipo']['atualizado_por']);
        $this->assertSame(InteressadoQualificacao::RESULTADO_EM_QUALIFICACAO, $qualificacao->resultado);
    }

    public function test_painel_do_roteiro_mostra_o_estado_dos_campos(): void
    {
        $lead = $this->lead();
        app(QualificacaoService::class)->registrar($lead, 'prazo_decisao', 'esta_semana', InteressadoQualificacao::FONTE_CONSULTOR);

        Livewire::actingAs($this->admin)
            ->test(EditInteressado::class, ['record' => $lead->id])
            ->assertSee('Em qualificação')
            ->assertSee('Prazo de decisão')
            ->assertSee('Esta semana')
            ->assertSee('Respondido · Consultor');
    }

    public function test_resumo_do_lead_traz_o_progresso_do_roteiro(): void
    {
        $lead = $this->lead();
        $this->qualificar($lead);

        Livewire::actingAs($this->admin)
            ->test(EditInteressado::class, ['record' => $lead->id])
            ->assertSee('Roteiro de qualificação')
            ->assertSee('9/9')
            ->assertSee('pronto p/ visita');
    }

    public function test_dependentes_mostram_a_situacao_atual(): void
    {
        $lead = $this->lead();
        InteressadoDependente::create(['interessado_id' => $lead->id, 'nome_crianca' => 'Lucas']);

        Livewire::actingAs($this->admin)
            ->test(EditInteressado::class, ['record' => $lead->id])
            ->assertSee('Situação atual');
    }

    public function test_acao_de_mover_para_a_etapa_sugerida(): void
    {
        $visita = StatusInteressado::create(['nome' => 'Visita Agendada', 'cor' => 'primary', 'ordem' => 3, 'is_final' => false, 'is_ganho' => false]);
        config(['crm.qualificacao.etapa_qualificado' => 'Visita Agendada']);

        $lead = $this->lead();

        // Ainda não qualificado: a ação não aparece.
        Livewire::actingAs($this->admin)
            ->test(EditInteressado::class, ['record' => $lead->id])
            ->assertActionHidden('moverParaEtapaQualificada');

        $this->qualificar($lead);

        Livewire::actingAs($this->admin)
            ->test(EditInteressado::class, ['record' => $lead->id])
            ->assertActionVisible('moverParaEtapaQualificada')
            ->callAction('moverParaEtapaQualificada')
            ->assertNotified('Lead movido para "Visita Agendada".');

        $this->assertSame($visita->id, $lead->fresh()->status_interessado_id);

        // Já na etapa: some.
        Livewire::actingAs($this->admin)
            ->test(EditInteressado::class, ['record' => $lead->id])
            ->assertActionHidden('moverParaEtapaQualificada');
    }

    public function test_sem_etapa_configurada_a_acao_de_mover_nao_aparece(): void
    {
        config(['crm.qualificacao.etapa_qualificado' => null]);
        $lead = $this->lead();
        $this->qualificar($lead);

        Livewire::actingAs($this->admin)
            ->test(EditInteressado::class, ['record' => $lead->id])
            ->assertActionHidden('moverParaEtapaQualificada');
    }

    public function test_listagem_filtra_pelo_roteiro_de_qualificacao(): void
    {
        $qualificado = $this->lead();
        $this->qualificar($qualificado);

        $emQualificacao = $this->lead();
        app(QualificacaoService::class)->registrar($emQualificacao, 'bairro', 'Centro', InteressadoQualificacao::FONTE_CONSULTOR);

        $naoIniciado = $this->lead();

        Livewire::actingAs($this->admin)
            ->test(ListInteressados::class)
            ->assertCanSeeTableRecords([$qualificado, $emQualificacao, $naoIniciado])
            ->assertSee('Qualificado para a visita')
            ->filterTable('roteiro_qualificacao', InteressadoQualificacao::RESULTADO_QUALIFICADO)
            ->assertCanSeeTableRecords([$qualificado])
            ->assertCanNotSeeTableRecords([$emQualificacao, $naoIniciado])
            ->filterTable('roteiro_qualificacao', InteressadoQualificacao::RESULTADO_EM_QUALIFICACAO)
            ->assertCanSeeTableRecords([$emQualificacao])
            ->assertCanNotSeeTableRecords([$qualificado, $naoIniciado])
            ->filterTable('roteiro_qualificacao', InteressadoQualificacao::RESULTADO_NAO_INICIADA)
            ->assertCanSeeTableRecords([$naoIniciado])
            ->assertCanNotSeeTableRecords([$qualificado, $emQualificacao]);
    }

    public function test_comando_reavalia_leads_que_ja_tinham_dados_antes_do_roteiro(): void
    {
        $lead = $this->lead();
        $this->qualificar($lead);

        // Simula o estado de antes do deploy: aluno preenchido, nenhuma linha de qualificação.
        InteressadoQualificacao::query()->delete();
        $semDados = $this->lead();

        $this->artisan('crm:reavaliar-qualificacao')
            ->expectsOutputToContain('2 lead(s) reavaliado(s).')
            ->assertSuccessful();

        // Os campos da família foram apagados junto; sobra o que mora no aluno: em qualificação, não qualificado.
        $this->assertSame(InteressadoQualificacao::RESULTADO_EM_QUALIFICACAO, $lead->qualificacao()->first()->resultado);
        $this->assertNull($semDados->qualificacao()->first(), 'Lead sem nenhum dado não ganha linha.');
    }
}
