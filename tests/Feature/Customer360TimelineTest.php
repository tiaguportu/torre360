<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoDocumento;
use App\Enums\StatusVisitaInteressado;
use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Filament\Resources\Interessados\RelationManagers\TimelineRelationManager;
use App\Models\DocumentoInserido;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\PesquisaSatisfacaoVisita;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Models\VisitaInteressado;
use App\Services\Customer360TimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class Customer360TimelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Interessado $interessado;

    protected TipoContatoInteressado $tipoWhatsapp;

    protected TipoContatoInteressado $tipoLigacao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $statusNovo = StatusInteressado::create(['nome' => 'Novo', 'ordem' => 1]);
        $pessoa = Pessoa::factory()->create(['telefone' => '11988887777']);

        $this->interessado = Interessado::factory()->create([
            'pessoa_id' => $pessoa->id,
            'usuario_id' => $this->user->id,
            'status_interessado_id' => $statusNovo->id,
            'lead_score' => 20,
            'temperatura' => 'morno',
            'data_proximo_contato' => now()->addDays(2),
        ]);

        $this->tipoWhatsapp = TipoContatoInteressado::create(['nome' => 'WhatsApp']);
        $this->tipoLigacao = TipoContatoInteressado::create(['nome' => 'Ligação']);
    }

    public function test_service_agrega_eventos_de_contatos_visitas_documentos_e_atividades(): void
    {
        // 1. Contato
        HistoricoContato::create([
            'interessado_id' => $this->interessado->id,
            'usuario_id' => $this->user->id,
            'tipo_contato_interessado_id' => $this->tipoWhatsapp->id,
            'relato' => 'Conversado sobre bolsa de estudos no WhatsApp',
            'data_contato' => now()->subHours(3),
            'resultado' => 'agendou_visita',
        ]);

        // 2. Visita
        $visita = VisitaInteressado::create([
            'interessado_id' => $this->interessado->id,
            'usuario_id' => $this->user->id,
            'data_hora' => now()->subDay(),
            'status' => StatusVisitaInteressado::Realizada,
            'observacoes' => 'Família visitou a quadra e gostou muito',
        ]);

        PesquisaSatisfacaoVisita::create([
            'visita_interessado_id' => $visita->id,
            'interessado_id' => $this->interessado->id,
            'token' => 'token_teste_123',
            'nota_nps' => 10,
            'nota_atendimento' => 5,
            'nota_infraestrutura' => 5,
            'nota_proposta_pedagogica' => 5,
            'comentario' => 'Escola maravilhosa, fomos muito bem acolhidos',
            'respondido_em' => now()->subHours(10),
        ]);

        // 3. Documento com Análise IA
        $tipoDoc = TipoDocumento::create(['nome' => 'Certidão de Nascimento']);
        DocumentoInserido::create([
            'interessado_id' => $this->interessado->id,
            'tipo_documento_id' => $tipoDoc->id,
            'arquivo_path' => 'documentos_candidatos/certidao.pdf',
            'nome_arquivo_original' => 'certidao_aluno.pdf',
            'status' => SituacaoDocumento::VERIFICADO,
            'analisado_ia_em' => now()->subHours(2),
            'dados_ia' => [
                'documento_identificado' => 'Certidão de Nascimento',
                'score_confianca' => 98,
                'qualidade' => ['legivel' => true],
                'confere_com_solicitado' => true,
                'dados_extraidos' => [
                    'cpf' => '123.456.789-00',
                    'nome' => 'Lucas Silva',
                ],
            ],
        ]);

        // 4. ActivityLog
        Activity::create([
            'log_name' => 'crm',
            'description' => 'updated',
            'subject_type' => Interessado::class,
            'subject_id' => $this->interessado->id,
            'causer_type' => User::class,
            'causer_id' => $this->user->id,
            'properties' => [
                'old' => ['status_interessado_id' => 1],
                'attributes' => ['status_interessado_id' => 1],
            ],
            'created_at' => now()->subMinutes(30),
        ]);

        $service = app(Customer360TimelineService::class);
        $timeline = $service->obterTimeline($this->interessado, 'todos');

        $this->assertGreaterThanOrEqual(4, $timeline->count());

        $tiposColetados = $timeline->pluck('tipo')->all();
        $this->assertContains('contato', $tiposColetados);
        $this->assertContains('visita', $tiposColetados);
        $this->assertContains('documento', $tiposColetados);
        $this->assertContains('etapa', $tiposColetados);
    }

    public function test_service_filtra_por_categoria_corretamente(): void
    {
        HistoricoContato::create([
            'interessado_id' => $this->interessado->id,
            'usuario_id' => $this->user->id,
            'tipo_contato_interessado_id' => $this->tipoLigacao->id,
            'relato' => 'Ligação efetuada',
            'data_contato' => now(),
        ]);

        VisitaInteressado::create([
            'interessado_id' => $this->interessado->id,
            'usuario_id' => $this->user->id,
            'data_hora' => now()->addDay(),
            'status' => StatusVisitaInteressado::Agendada,
        ]);

        $service = app(Customer360TimelineService::class);

        $apenasContatos = $service->obterTimeline($this->interessado, 'contatos');
        $this->assertCount(1, $apenasContatos);
        $this->assertSame('contato', $apenasContatos->first()['tipo']);

        $apenasVisitas = $service->obterTimeline($this->interessado, 'visitas');
        $this->assertCount(1, $apenasVisitas);
        $this->assertSame('visita', $apenasVisitas->first()['tipo']);
    }

    public function test_service_filtra_por_busca_textual(): void
    {
        HistoricoContato::create([
            'interessado_id' => $this->interessado->id,
            'usuario_id' => $this->user->id,
            'tipo_contato_interessado_id' => $this->tipoWhatsapp->id,
            'relato' => 'Mãe perguntou sobre período integral e natação infantil',
            'data_contato' => now(),
        ]);

        HistoricoContato::create([
            'interessado_id' => $this->interessado->id,
            'usuario_id' => $this->user->id,
            'tipo_contato_interessado_id' => $this->tipoLigacao->id,
            'relato' => 'Recado com secretária eletrônica',
            'data_contato' => now()->subDay(),
        ]);

        $service = app(Customer360TimelineService::class);

        $resultadoNatacao = $service->obterTimeline($this->interessado, 'todos', 'natação');
        $this->assertCount(1, $resultadoNatacao);
        $this->assertStringContainsString('natação', $resultadoNatacao->first()['conteudo']);

        $resultadoVazio = $service->obterTimeline($this->interessado, 'todos', 'palavra-inexistente-xyz');
        $this->assertCount(0, $resultadoVazio);
    }

    public function test_service_calcula_resumo_de_metricas_com_atraso_de_contato(): void
    {
        // Define lead com próximo contato em atraso (ontem)
        $this->interessado->update([
            'data_proximo_contato' => now()->subDays(3),
        ]);

        HistoricoContato::create([
            'interessado_id' => $this->interessado->id,
            'usuario_id' => $this->user->id,
            'tipo_contato_interessado_id' => $this->tipoWhatsapp->id,
            'relato' => 'Contato teste',
            'data_contato' => now()->subDays(4),
        ]);

        $service = app(Customer360TimelineService::class);
        $metricas = $service->obterResumoMetricas($this->interessado);

        $this->assertTrue($metricas['esta_em_atraso']);
        $this->assertGreaterThanOrEqual(2, $metricas['dias_atraso']);
        $this->assertSame(1, $metricas['total_contatos']);
        $this->assertSame(1, $metricas['total_interacoes']);
    }

    public function test_livewire_registra_interacao_rapida_e_recalcula_lead_score(): void
    {
        Livewire::test(TimelineRelationManager::class, [
            'ownerRecord' => $this->interessado,
            'pageClass' => EditInteressado::class,
        ])
            ->set('novoTipoContatoId', $this->tipoWhatsapp->id)
            ->set('novoRelato', 'Reunião online realizada. Família solicitou proposta formal.')
            ->set('novoResultado', 'agendou_visita')
            ->set('novaDataProximoContato', now()->addDays(3)->format('Y-m-d\TH:i'))
            ->set('novaDuracaoMinutos', 25)
            ->call('registrarContatoRapido')
            ->assertHasNoErrors()
            ->assertNotified('⚡ Interação registrada com sucesso!');

        // Verifica gravação no banco
        $this->assertDatabaseHas('historico_contato', [
            'interessado_id' => $this->interessado->id,
            'tipo_contato_interessado_id' => $this->tipoWhatsapp->id,
            'resultado' => 'agendou_visita',
            'duracao_minutos' => 25,
        ]);

        // Verifica que o interessado teve sua data de próximo contato atualizada
        $this->interessado->refresh();
        $this->assertNotNull($this->interessado->data_proximo_contato);
        $this->assertTrue($this->interessado->data_proximo_contato->isFuture());

        // Verifica que o Lead Score foi recalculado
        $this->assertNotNull($this->interessado->lead_score_atualizado_em);
    }

    public function test_livewire_valida_campos_obrigatorios_no_registro_rapido(): void
    {
        Livewire::test(TimelineRelationManager::class, [
            'ownerRecord' => $this->interessado,
            'pageClass' => EditInteressado::class,
        ])
            ->set('novoTipoContatoId', null)
            ->set('novoRelato', '')
            ->call('registrarContatoRapido')
            ->assertHasErrors(['novoTipoContatoId', 'novoRelato']);
    }
}
