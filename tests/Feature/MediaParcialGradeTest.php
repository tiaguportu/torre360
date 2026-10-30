<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoFinal;
use App\Filament\Pages\LancamentoNotasGrade;
use App\Models\Avaliacao;
use App\Models\CategoriaAvaliacao;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use App\Services\MediaParcialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Média parcial na grade de lançamento: a conta do boletim com a nota digitada no lugar da salva, e a cor da faixa
 * pela regra do fechamento do ciclo (aprovação 7,0 e recuperação mínima 5,0 do período letivo).
 */
class MediaParcialGradeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Turma $turma;

    private Disciplina $disciplina;

    private EtapaAvaliativa $etapa;

    private Matricula $ana;

    private Matricula $bia;

    private Avaliacao $prova;

    private Avaliacao $trabalho;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['activated_at' => now(), 'email_verified_at' => now()]);
        $this->admin->assignRole('super_admin');

        $periodo = PeriodoLetivo::factory()->create(['nota_aprovacao' => 7, 'nota_recuperacao_minima' => 5]);
        $this->turma = Turma::factory()->create(['periodo_letivo_id' => $periodo->id]);
        $this->disciplina = Disciplina::factory()->create(['nome' => 'Matemática']);
        $this->ana = $this->matricula('Ana Lima');
        $this->bia = $this->matricula('Bia Souza');

        $this->etapa = EtapaAvaliativa::create([
            'nome' => '1º Bimestre',
            'periodo_letivo_id' => $periodo->id,
            'turma_id' => $this->turma->id,
            'data_inicio' => now()->subMonth()->toDateString(),
            'data_fim' => now()->addMonth()->toDateString(),
        ]);

        $professor = Pessoa::factory()->create();
        $this->prova = $this->avaliacao('Prova', $professor, 1);
        $this->trabalho = $this->avaliacao('Trabalho', $professor, 1);
    }

    private function matricula(string $nome): Matricula
    {
        return Matricula::factory()->create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => $nome])->id,
            'turma_id' => $this->turma->id,
            'situacao' => 'ativa',
        ]);
    }

    private function avaliacao(string $categoria, Pessoa $professor, float $peso, float $maxima = 10): Avaliacao
    {
        return Avaliacao::create([
            'turma_id' => $this->turma->id,
            'disciplina_id' => $this->disciplina->id,
            'etapa_avaliativa_id' => $this->etapa->id,
            'categoria_avaliacao_id' => CategoriaAvaliacao::create(['nome' => $categoria])->id,
            'professor_id' => $professor->id,
            'data_prevista' => now(),
            'data_ocorrencia' => now(),
            'data_limite_lancamento' => now()->addDays(20),
            'nota_maxima' => $maxima,
            'peso_etapa_avaliativa' => $peso,
        ]);
    }

    private function nota(Avaliacao $avaliacao, Matricula $matricula, float $valor): void
    {
        Nota::create(['avaliacao_id' => $avaliacao->id, 'matricula_id' => $matricula->id, 'valor' => $valor]);
    }

    private function servico(): MediaParcialService
    {
        return app(MediaParcialService::class);
    }

    /**
     * @return array{0: ?float, 1: ?SituacaoFinal}
     */
    private function media(Matricula $matricula, mixed $digitado, ?Avaliacao $aberta = null): array
    {
        $contexto = $this->servico()->contexto($aberta ?? $this->prova->fresh());
        $media = $this->servico()->media($contexto, $matricula->id, $digitado);

        return [$media, $this->servico()->situacao($contexto, $media)];
    }

    // --- A conta -------------------------------------------------------------------------------------------------------

    public function test_media_junta_a_nota_digitada_com_as_outras_avaliacoes_da_etapa(): void
    {
        $this->nota($this->trabalho, $this->ana, 4);

        [$media, $situacao] = $this->media($this->ana, '9');

        $this->assertEqualsWithDelta(6.5, $media, 0.001);
        $this->assertSame(SituacaoFinal::RECUPERACAO, $situacao, 'âmbar: abaixo de 7,0 e a partir de 5,0');
    }

    public function test_faixas_de_cor_seguem_o_periodo_letivo(): void
    {
        $this->nota($this->trabalho, $this->ana, 4);

        $this->assertSame(SituacaoFinal::APROVADO, $this->media($this->ana, '10')[1], '(10 + 4) / 2 = 7,0: verde');
        $this->assertSame(SituacaoFinal::RECUPERACAO, $this->media($this->ana, '6')[1], '5,0: âmbar');
        $this->assertSame(SituacaoFinal::REPROVADO, $this->media($this->ana, '5')[1], '4,5: vermelho');
    }

    public function test_peso_da_avaliacao_conta_como_no_boletim(): void
    {
        $this->prova->update(['peso_etapa_avaliativa' => 3]);
        $this->nota($this->trabalho, $this->ana, 4);

        [$media] = $this->media($this->ana, '8');

        $this->assertEqualsWithDelta(7.0, $media, 0.001, '(8 x 3 + 4 x 1) / 4');
    }

    public function test_virgula_decimal_e_aceita(): void
    {
        [$media] = $this->media($this->ana, '8,5');

        $this->assertEqualsWithDelta(8.5, $media, 0.001);
    }

    public function test_valor_vazio_invalido_ou_acima_da_nota_maxima_nao_entra_na_conta(): void
    {
        $this->nota($this->trabalho, $this->ana, 4);

        foreach ([null, '', 'abc', '11', '-1'] as $digitado) {
            $this->assertEqualsWithDelta(4.0, $this->media($this->ana, $digitado)[0], 0.001, 'valor: '.json_encode($digitado));
        }
    }

    public function test_sem_nenhuma_nota_nao_ha_media_nem_cor(): void
    {
        [$media, $situacao] = $this->media($this->ana, null);

        $this->assertNull($media);
        $this->assertNull($situacao);
    }

    public function test_a_nota_ja_salva_da_avaliacao_aberta_nao_vale_mais_que_a_digitada(): void
    {
        $this->nota($this->prova, $this->ana, 10);
        $this->nota($this->trabalho, $this->ana, 4);

        $this->assertEqualsWithDelta(4.0, $this->media($this->ana, null)[0], 0.001, 'apagar o campo tira a nota salva da conta');
        $this->assertEqualsWithDelta(3.0, $this->media($this->ana, '2')[0], 0.001, 'o digitado substitui o salvo');
    }

    public function test_uma_linha_nao_contamina_a_outra(): void
    {
        $this->nota($this->trabalho, $this->ana, 4);
        $this->nota($this->trabalho, $this->bia, 8);

        $contexto = $this->servico()->contexto($this->prova->fresh());

        $this->assertEqualsWithDelta(6.5, $this->servico()->media($contexto, $this->ana->id, '9'), 0.001);
        $this->assertEqualsWithDelta(8.0, $this->servico()->media($contexto, $this->bia->id, null), 0.001, 'a nota digitada da Ana não vaza');
        $this->assertEqualsWithDelta(6.5, $this->servico()->media($contexto, $this->ana->id, '9'), 0.001, 'repetir dá o mesmo');
    }

    public function test_so_conta_a_mesma_disciplina_e_etapa(): void
    {
        $outraDisciplina = Disciplina::factory()->create();
        $outraEtapa = EtapaAvaliativa::create([
            'nome' => '2º Bimestre',
            'periodo_letivo_id' => $this->etapa->periodo_letivo_id,
            'turma_id' => $this->turma->id,
            'data_inicio' => now()->addMonths(2)->toDateString(),
            'data_fim' => now()->addMonths(3)->toDateString(),
        ]);
        $professor = Pessoa::factory()->create();
        $deOutraDisciplina = Avaliacao::create([
            'turma_id' => $this->turma->id, 'disciplina_id' => $outraDisciplina->id, 'etapa_avaliativa_id' => $this->etapa->id,
            'categoria_avaliacao_id' => CategoriaAvaliacao::create(['nome' => 'Prova B'])->id, 'professor_id' => $professor->id,
            'data_prevista' => now(), 'data_ocorrencia' => now(), 'data_limite_lancamento' => now()->addDays(20), 'nota_maxima' => 10, 'peso_etapa_avaliativa' => 1,
        ]);
        $deOutraEtapa = Avaliacao::create([
            'turma_id' => $this->turma->id, 'disciplina_id' => $this->disciplina->id, 'etapa_avaliativa_id' => $outraEtapa->id,
            'categoria_avaliacao_id' => CategoriaAvaliacao::create(['nome' => 'Prova C'])->id, 'professor_id' => $professor->id,
            'data_prevista' => now(), 'data_ocorrencia' => now(), 'data_limite_lancamento' => now()->addDays(20), 'nota_maxima' => 10, 'peso_etapa_avaliativa' => 1,
        ]);
        $this->nota($deOutraDisciplina, $this->ana, 0);
        $this->nota($deOutraEtapa, $this->ana, 0);
        $this->nota($this->trabalho, $this->ana, 8);

        $this->assertEqualsWithDelta(8.0, $this->media($this->ana, '8')[0], 0.001);
    }

    public function test_situacao_faltou_ou_nao_se_aplica_nao_conta_como_zero(): void
    {
        Nota::create(['avaliacao_id' => $this->trabalho->id, 'matricula_id' => $this->ana->id, 'valor' => null, 'situacao' => 'faltou']);
        $this->nota($this->prova, $this->bia, 6);

        $this->assertNull($this->media($this->ana, null)[0], 'só há um "faltou": sem média');
        $this->assertEqualsWithDelta(6.0, $this->media($this->bia, '6')[0], 0.001);
    }

    public function test_formato_e_legenda_usam_virgula_e_os_limites_do_periodo(): void
    {
        $contexto = $this->servico()->contexto($this->prova->fresh());

        $this->assertSame('6,5', $this->servico()->formatar(6.5));
        $this->assertSame('7,0', $this->servico()->formatar(6.96), 'arredonda a uma casa, como o boletim');
        $this->assertStringContainsString('a partir de 7,0 (aprovação)', $this->servico()->legenda($contexto));
        $this->assertStringContainsString('a partir de 5,0 (recuperação)', $this->servico()->legenda($contexto));
    }

    public function test_media_de_6_96_aparece_como_7_e_fica_verde_como_no_boletim(): void
    {
        $this->nota($this->trabalho, $this->ana, 5.92);

        [$media, $situacao] = $this->media($this->ana, '8');

        $this->assertEqualsWithDelta(6.96, $media, 0.001);
        $this->assertSame('7,0', $this->servico()->formatar($media));
        $this->assertSame(SituacaoFinal::APROVADO, $situacao, 'o que aparece 7,0 não pode ficar âmbar');
    }

    // --- A tela --------------------------------------------------------------------------------------------------------

    private function abrirGrade(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs($this->admin)
            ->test(LancamentoNotasGrade::class)
            ->set('data.turma_id', $this->turma->id)
            ->set('data.disciplina_id', $this->disciplina->id)
            ->set('data.avaliacao_id', $this->prova->id)
            ->call('carregarGrade');
    }

    /**
     * O tooltip do Filament vai para o HTML dentro de um objeto JS, com os acentos escapados (ç).
     */
    private function emJs(string $texto): string
    {
        return trim((string) json_encode($texto), '"');
    }

    private function chaveDa(\Livewire\Features\SupportTesting\Testable $pagina, Matricula $matricula): string
    {
        foreach ($pagina->get('data.notas') as $chave => $linha) {
            if ((int) $linha['matricula_id'] === $matricula->id) {
                return (string) $chave;
            }
        }

        $this->fail('Aluno fora da grade.');
    }

    public function test_grade_mostra_a_media_parcial_e_atualiza_ao_digitar(): void
    {
        $this->nota($this->trabalho, $this->ana, 4);
        $this->nota($this->trabalho, $this->bia, 8);

        $pagina = $this->abrirGrade();
        $pagina->assertSee('Média parcial')
            ->assertSee('a partir de 7,0 (aprovação)');

        $chaveAna = $this->chaveDa($pagina, $this->ana);
        $chaveBia = $this->chaveDa($pagina, $this->bia);

        // Sem nota digitada, a média é só a do trabalho já lançado: Ana 4,0 (vermelho) e Bia 8,0 (verde).
        $pagina->assertSeeHtml('fi-color-danger')->assertSeeHtml('fi-color-success');

        $pagina->set("data.notas.{$chaveAna}.valor", '9')
            ->assertSee('6,5')
            ->assertSeeHtml($this->emJs('Recuperação, se a média se mantiver'))
            ->assertSeeHtml('fi-color-warning');

        $pagina->set("data.notas.{$chaveBia}.valor", '10')->assertSee('9,0');
    }

    public function test_grade_sem_notas_mostra_traco_e_nao_quebra(): void
    {
        $this->abrirGrade()
            ->assertSee('Média parcial')
            ->assertSeeHtml($this->emJs('Sem notas lançadas nesta etapa'))
            ->assertSeeHtml('class="fi-badge fi-size-sm"') // cinza: o badge neutro não leva classe de cor
            ->assertDontSeeHtml('fi-color-success')
            ->assertDontSeeHtml('fi-color-danger');
    }

    public function test_situacao_faltou_na_grade_tira_a_nota_da_conta_e_salvar_continua_funcionando(): void
    {
        $this->nota($this->trabalho, $this->ana, 4);

        $pagina = $this->abrirGrade();
        $chave = $this->chaveDa($pagina, $this->ana);

        $pagina->set("data.notas.{$chave}.valor", '9')->assertSee('6,5');
        $pagina->set("data.notas.{$chave}.situacao", 'faltou')->assertSee('4,0');

        $pagina->call('salvarNotas');

        $nota = Nota::query()->where('avaliacao_id', $this->prova->id)->where('matricula_id', $this->ana->id)->first();
        $this->assertNotNull($nota);
        $this->assertNull($nota->valor);
        $this->assertSame('faltou', $nota->situacao->value);
    }

    public function test_salvar_grava_so_a_nota_e_a_media_nao_vai_para_o_banco(): void
    {
        $pagina = $this->abrirGrade();
        $chave = $this->chaveDa($pagina, $this->ana);

        $pagina->set("data.notas.{$chave}.valor", '8.5')->call('salvarNotas');

        $this->assertSame(8.5, (float) Nota::query()->where('avaliacao_id', $this->prova->id)->where('matricula_id', $this->ana->id)->value('valor'));
        $this->assertSame(1, Nota::query()->where('avaliacao_id', $this->prova->id)->count());
    }
}
