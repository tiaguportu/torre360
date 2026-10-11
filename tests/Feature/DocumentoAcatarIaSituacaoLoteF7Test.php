<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoDocumento;
use App\Filament\Resources\DocumentoInseridos\Pages\ListDocumentoInseridos;
use App\Models\DocumentoInserido;
use App\Models\Matricula;
use App\Models\TipoDocumento;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DocumentoAcatarIaSituacaoLoteF7Test extends TestCase
{
    use RefreshDatabase;

    private User $usuarioComPermissao;

    private User $usuarioSemPermissao;

    private TipoDocumento $tipoDocumento;

    private Matricula $matricula;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'ViewAny:DocumentoInserido', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:DocumentoInserido', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:DocumentoInserido', 'guard_name' => 'web']);

        $this->usuarioComPermissao = User::factory()->create([
            'name' => 'Secretário Aprovador',
            'activated_at' => now(),
            'deactivated_at' => null,
        ]);
        $this->usuarioComPermissao->givePermissionTo('ViewAny:DocumentoInserido', 'View:DocumentoInserido', 'Update:DocumentoInserido');

        $this->usuarioSemPermissao = User::factory()->create([
            'name' => 'Usuário Apenas Visualizador',
            'activated_at' => now(),
            'deactivated_at' => null,
        ]);
        $this->usuarioSemPermissao->givePermissionTo('ViewAny:DocumentoInserido', 'View:DocumentoInserido');

        $this->tipoDocumento = TipoDocumento::create(['nome' => 'Certidão de Nascimento']);
        $this->matricula = Matricula::factory()->create();
    }

    private function criarDocumento(string $status = 'em_analise', ?string $sugerido = 'verificado'): DocumentoInserido
    {
        return DocumentoInserido::create([
            'tipo_documento_id' => $this->tipoDocumento->id,
            'matricula_id' => $this->matricula->id,
            'status' => SituacaoDocumento::from($status),
            'status_sugerido' => $sugerido,
            'arquivo_path' => 'documentos_alunos/teste.pdf',
            'nome_arquivo_original' => 'teste.pdf',
        ]);
    }

    public function test_usuario_sem_permissao_update_documento_inserido_nao_consegue_acatar_ia(): void
    {
        $doc = $this->criarDocumento('em_analise', 'verificado');

        Livewire::actingAs($this->usuarioSemPermissao)
            ->test(ListDocumentoInseridos::class)
            ->assertActionHidden(TestAction::make('acatarSugestaoIa')->table($doc));

        $doc->refresh();
        $this->assertSame(SituacaoDocumento::EM_ANALISE, $doc->status);
    }

    public function test_rejeicao_sugerida_pela_ia_exige_motivo_no_formulario(): void
    {
        $doc = $this->criarDocumento('em_analise', 'rejeitado');

        Livewire::actingAs($this->usuarioComPermissao)
            ->test(ListDocumentoInseridos::class)
            ->callAction(TestAction::make('acatarSugestaoIa')->table($doc), [
                'motivo' => '',
            ])
            ->assertHasActionErrors(['motivo' => ['required']]);

        $doc->refresh();
        $this->assertSame(SituacaoDocumento::EM_ANALISE, $doc->status);
    }

    public function test_acatar_sugestao_rejeitado_com_motivo_aplica_situacao_e_grava_activitylog(): void
    {
        $doc = $this->criarDocumento('em_analise', 'rejeitado');

        Livewire::actingAs($this->usuarioComPermissao)
            ->test(ListDocumentoInseridos::class)
            ->callAction(TestAction::make('acatarSugestaoIa')->table($doc), [
                'motivo' => 'Documento ilegível e cortado na foto',
            ])
            ->assertHasNoActionErrors();

        $doc->refresh();
        $this->assertSame(SituacaoDocumento::REJEITADO, $doc->status);
        $this->assertSame('Documento ilegível e cortado na foto', $doc->observacoes);

        $activity = Activity::where('subject_type', $doc->getMorphClass())
            ->where('subject_id', $doc->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($this->usuarioComPermissao->id, $activity->causer_id);
        $this->assertTrue((bool) ($activity->properties['origem_ia'] ?? false));
        $this->assertSame('rejeitado', $activity->properties['status_novo'] ?? null);
    }

    public function test_acatar_sugestao_verificado_limpa_observacoes_e_grava_log(): void
    {
        $doc = $this->criarDocumento('em_analise', 'verificado');
        $doc->update(['observacoes' => 'Observação antiga']);

        Livewire::actingAs($this->usuarioComPermissao)
            ->test(ListDocumentoInseridos::class)
            ->callAction(TestAction::make('acatarSugestaoIa')->table($doc))
            ->assertHasNoActionErrors();

        $doc->refresh();
        $this->assertSame(SituacaoDocumento::VERIFICADO, $doc->status);
        $this->assertNull($doc->observacoes);

        $activity = Activity::where('subject_type', $doc->getMorphClass())
            ->where('subject_id', $doc->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($this->usuarioComPermissao->id, $activity->causer_id);
        $this->assertTrue((bool) ($activity->properties['origem_ia'] ?? false));
        $this->assertSame('verificado', $activity->properties['status_novo'] ?? null);
    }

    public function test_metodo_aplicar_situacao_lanca_excecao_se_rejeitar_sem_motivo(): void
    {
        $doc = $this->criarDocumento('em_analise', 'rejeitado');

        $this->expectException(\InvalidArgumentException::class);
        $doc->aplicarSituacao(SituacaoDocumento::REJEITADO, '');
    }
}
