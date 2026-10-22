<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MotivoCancelamentoMatricula;
use App\Enums\OrigemCancelamentoMatricula;
use App\Enums\SituacaoMatricula;
use App\Enums\StatusCancelamentoMatricula;
use App\Enums\StatusFatura;
use App\Enums\StatusRematricula;
use App\Enums\StatusTransferencia;
use App\Enums\TipoTemplateDocumento;
use App\Filament\Portal\Pages\CancelamentoDeMatricula;
use App\Filament\Resources\CancelamentosMatricula\Pages\CreateCancelamentoMatricula;
use App\Filament\Resources\CancelamentosMatricula\Pages\ListCancelamentosMatricula;
use App\Models\Banco;
use App\Models\CancelamentoMatricula;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\PeriodoRematricula;
use App\Models\Pessoa;
use App\Models\Rematricula;
use App\Models\ResponsavelFinanceiro;
use App\Models\TemplateDocumento;
use App\Models\TransacaoBancaria;
use App\Models\Turma;
use App\Models\User;
use App\Services\CancelamentoMatriculaService;
use App\Services\Gateways\FakeGatewayPagamento;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Cancelamento de matrícula e distrato (Onda 61).
 *
 * "Hoje" é quarta-feira, 21/10/2026. O cenário padrão tem uma saída em 31/10/2026 e faturas para cada regra da proposta.
 */
class CancelamentoMatriculaTest extends TestCase
{
    use RefreshDatabase;

    private Matricula $matricula;

    private Contrato $contrato;

    private User $secretaria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00:00'));
        Storage::fake('local');
        Storage::fake('public');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['super_admin', 'admin', 'secretaria', 'coordenador', 'professor', 'responsavel', 'aluno'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        foreach (['ViewAny', 'View', 'Create', 'Aprovar', 'Concluir'] as $acao) {
            Role::findByName('secretaria')->givePermissionTo(Permission::firstOrCreate(['name' => "{$acao}:CancelamentoMatricula", 'guard_name' => 'web']));
        }

        foreach (['ViewAny', 'View'] as $acao) {
            Role::findByName('coordenador')->givePermissionTo(Permission::findByName("{$acao}:CancelamentoMatricula"));
        }

        [$this->matricula, $this->contrato] = $this->aluno('Ana Lima');
        $this->secretaria = $this->usuario('secretaria');
    }

    private function servico(): CancelamentoMatriculaService
    {
        return app(CancelamentoMatriculaService::class);
    }

    private function usuario(string $papel): User
    {
        $usuario = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);

        return $usuario;
    }

    /**
     * As permissões valem pelo papel ativo da sessão, que é global no teste: quem age precisa ser declarado antes de cada
     * chamada, senão vale o papel do último usuário criado.
     */
    private function como(User $usuario): User
    {
        session(['active_role' => $usuario->getRoleNames()->first()]);

        return $usuario;
    }

    /**
     * @return array{0: Matricula, 1: Contrato}
     */
    private function aluno(string $nome, ?string $nascimento = '2014-03-10'): array
    {
        $turma = Turma::factory()->create();
        $matricula = Matricula::factory()->create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => $nome, 'data_nascimento' => $nascimento])->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);
        $contrato = Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => 12000, 'data_aceite' => '2026-01-05']);

        return [$matricula, $contrato];
    }

    private function fatura(string $vencimento, float $valor, string $status, string $descricao, float $pago = 0.0, ?Contrato $contrato = null): Fatura
    {
        $fatura = Fatura::create(['contrato_id' => ($contrato ?? $this->contrato)->id, 'vencimento' => $vencimento, 'status' => $status]);
        ItemFatura::create(['fatura_id' => $fatura->id, 'descricao' => $descricao, 'quantidade' => 1, 'valor_unitario' => $valor, 'desconto' => 0, 'tipo_desconto' => 'absoluto']);

        if ($pago > 0) {
            $this->pagamento($fatura, $pago);
        }

        return $fatura->fresh();
    }

    private function pagamento(Fatura $fatura, float $valor): void
    {
        $banco = Banco::first() ?? Banco::create(['nome' => 'Banco Teste', 'is_active' => true]);

        TransacaoBancaria::create(['banco_id' => $banco->id, 'fatura_id' => $fatura->id, 'tipo' => 'entrada', 'valor' => $valor, 'data_transacao' => '2026-10-01', 'descricao' => 'Pagamento', 'conciliado' => true]);
    }

    private function campanhaDeRematricula(): PeriodoRematricula
    {
        return PeriodoRematricula::create([
            'nome' => 'Rematrícula 2027',
            'periodo_letivo_origem_id' => PeriodoLetivo::create(['nome' => 'Origem teste', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15'])->id,
            'periodo_letivo_destino_id' => PeriodoLetivo::create(['nome' => 'Destino teste', 'data_inicio' => '2027-02-01', 'data_fim' => '2027-12-15'])->id,
            'valor_taxa' => 1200,
            'quantidade_parcelas_padrao' => 4,
            'valor_entrada_padrao' => 0,
            'data_inicio' => now()->subDay()->toDateString(),
            'data_fim' => now()->addDays(10)->toDateString(),
            'is_ativo' => true,
        ]);
    }

    /**
     * Uma fatura para cada regra: entrada paga, duas vencidas, uma futura sem pagamento, uma paga adiantada, uma parcial
     * futura e uma já cancelada (fora da proposta).
     *
     * @return array<string, Fatura>
     */
    private function cenario(): array
    {
        return [
            'entrada' => $this->fatura('2026-01-05', 500, 'pago', 'Entrada', 500),
            'vencida_antiga' => $this->fatura('2026-09-10', 1000, 'pendente', 'Parcela 1 de 12'),
            'vencida' => $this->fatura('2026-10-09', 1000, 'pendente', 'Parcela 2 de 12'),
            'futura' => $this->fatura('2026-11-10', 1000, 'pendente', 'Parcela 3 de 12'),
            'adiantada' => $this->fatura('2026-12-10', 1000, 'pago', 'Parcela 4 de 12', 1000),
            'parcial' => $this->fatura('2027-01-10', 1000, 'parcial', 'Parcela 5 de 12', 400),
            'cancelada' => $this->fatura('2026-08-10', 1000, 'cancelado', 'Parcela antiga'),
        ];
    }

    private function pedido(array $dados = [], ?Matricula $matricula = null): CancelamentoMatricula
    {
        return $this->servico()->solicitar($matricula ?? $this->matricula, $dados + [
            'motivo' => MotivoCancelamentoMatricula::MudancaCidade,
            'data_efetiva' => '2026-10-31',
        ], null, OrigemCancelamentoMatricula::Escola);
    }

    private function aprovado(array $dadosAprovacao = []): CancelamentoMatricula
    {
        return $this->servico()->aprovar($this->pedido(), $this->secretaria, $dadosAprovacao);
    }

    private function familiaDe(Matricula $matricula): User
    {
        $responsavel = Pessoa::create(['nome' => 'Responsável '.uniqid()]);
        $user = $this->usuario('responsavel');
        $responsavel->users()->save($user);
        $responsavel->alunos()->attach($matricula->pessoa_id);

        return $user;
    }

    // --- Pedido ------------------------------------------------------------------------------------------------------

    public function test_pedido_da_familia_fica_aguardando_a_secretaria_e_avisa_a_equipe(): void
    {
        $familia = $this->familiaDe($this->matricula);

        $pedido = $this->servico()->solicitar($this->matricula, [
            'motivo' => 'mudanca_escola',
            'motivo_detalhe' => 'Meu filho tem depressão e queremos outro ambiente',
            'data_efetiva' => '2026-11-30',
            'escola_destino' => 'Colégio Aurora',
        ], $familia);

        $this->assertSame(StatusCancelamentoMatricula::Solicitado, $pedido->status);
        $this->assertSame(OrigemCancelamentoMatricula::Familia, $pedido->origem);
        $this->assertTrue($pedido->gerar_transferencia, 'quem vai para outra escola precisa da declaração de transferência');
        $this->assertSame('Colégio Aurora', $pedido->escola_destino);

        $aviso = $this->secretaria->notifications()->first();

        $this->assertNotNull($aviso);
        $this->assertStringContainsString('Ana Lima', json_encode($aviso->data, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('depressão', json_encode($aviso->data, JSON_UNESCAPED_UNICODE), 'o aviso não carrega o motivo');
    }

    public function test_detalhe_do_motivo_fica_criptografado_e_fora_do_log(): void
    {
        $pedido = $this->pedido(['motivo_detalhe' => 'Situação delicada de saúde na família']);

        $this->assertSame('Situação delicada de saúde na família', $pedido->fresh()->motivo_detalhe);
        $this->assertStringNotContainsString('delicada', (string) DB::table('cancelamentos_matricula')->where('id', $pedido->id)->value('motivo_detalhe'));

        $logs = Activity::query()->get()->map(fn (Activity $a): string => $a->description.json_encode($a->properties, JSON_UNESCAPED_UNICODE))->implode(' ');

        $this->assertStringContainsString('Cancelamento de matrícula solicitado.', $logs);
        $this->assertStringNotContainsString('delicada', $logs);
    }

    public function test_matricula_ja_cancelada_nao_aceita_pedido(): void
    {
        $this->matricula->update(['situacao' => SituacaoMatricula::CANCELADA]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('não pode mais ser cancelada');

        $this->pedido();
    }

    public function test_so_um_pedido_em_andamento_por_matricula_e_depois_de_encerrado_pode_pedir_de_novo(): void
    {
        $primeiro = $this->pedido();

        try {
            $this->pedido();
            $this->fail('o segundo pedido em andamento deveria ser recusado');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Já existe um pedido', $e->getMessage());
        }

        $this->servico()->retirar($primeiro, $this->secretaria);

        $this->assertSame(StatusCancelamentoMatricula::Retirado, $primeiro->fresh()->status);
        $this->assertSame(StatusCancelamentoMatricula::Solicitado, $this->pedido()->status);
        $this->assertSame(2, CancelamentoMatricula::count());
    }

    public function test_familia_nao_escolhe_data_passada_mas_a_secretaria_registra_pedido_retroativo(): void
    {
        $familia = $this->familiaDe($this->matricula);

        try {
            $this->servico()->solicitar($this->matricula, ['motivo' => 'outro', 'data_efetiva' => '2026-10-01'], $familia);
            $this->fail('data passada deveria ser recusada para a família');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('a partir de 21/10/2026', $e->getMessage());
        }

        $retroativo = $this->servico()->solicitar($this->matricula, ['motivo' => 'outro', 'data_efetiva' => '2026-10-01'], $this->secretaria, OrigemCancelamentoMatricula::Escola);

        $this->assertSame('2026-10-01', $retroativo->data_efetiva->toDateString());
    }

    public function test_antecedencia_minima_da_configuracao_vale_para_a_familia(): void
    {
        config(['cancelamento.antecedencia_minima_dias' => 30]);
        $familia = $this->familiaDe($this->matricula);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('a partir de 20/11/2026');

        $this->servico()->solicitar($this->matricula, ['motivo' => 'outro', 'data_efetiva' => '2026-11-10'], $familia);
    }

    // --- Proposta ----------------------------------------------------------------------------------------------------

    public function test_proposta_segue_a_regra_de_cada_fatura(): void
    {
        $f = $this->cenario();
        $proposta = $this->servico()->proposta($this->pedido())->keyBy('fatura_id');

        $this->assertCount(6, $proposta, 'a fatura cancelada fica de fora');
        $this->assertFalse($proposta->has($f['cancelada']->id));

        $this->assertSame('manter', $proposta[$f['entrada']->id]['decisao'], 'a entrada não se cancela nem se devolve');
        $this->assertSame('manter', $proposta[$f['vencida_antiga']->id]['decisao']);
        $this->assertSame('manter', $proposta[$f['vencida']->id]['decisao']);
        $this->assertSame('cancelar', $proposta[$f['futura']->id]['decisao']);
        $this->assertSame('devolver', $proposta[$f['adiantada']->id]['decisao']);
        $this->assertSame('devolver', $proposta[$f['parcial']->id]['decisao']);

        $this->assertSame(['manter', 'cancelar'], $proposta[$f['futura']->id]['opcoes']);
        $this->assertSame(['manter', 'devolver'], $proposta[$f['adiantada']->id]['opcoes']);
    }

    public function test_proposta_traz_multa_e_juros_das_vencidas_e_o_mes_da_saida_e_cobrado_inteiro(): void
    {
        $f = $this->cenario();
        $mesDaSaida = $this->fatura('2026-10-28', 1000, 'pendente', 'Parcela do mês da saída');

        $proposta = $this->servico()->proposta($this->pedido())->keyBy('fatura_id');

        $this->assertSame(33.67, $proposta[$f['vencida_antiga']->id]['encargos']); // 41 dias: 20,00 + 13,67
        $this->assertSame(1033.67, $proposta[$f['vencida_antiga']->id]['total_em_aberto']);
        $this->assertSame(24.0, $proposta[$f['vencida']->id]['encargos']);
        $this->assertSame('manter', $proposta[$mesDaSaida->id]['decisao'], 'vence antes do fim do mês da saída: cobrada inteira');
        $this->assertSame(0.0, $proposta[$mesDaSaida->id]['encargos'], 'ainda não venceu');
    }

    public function test_proposta_sem_contrato_e_vazia(): void
    {
        [$semContrato] = $this->aluno('Sem Contrato');
        Contrato::where('matricula_id', $semContrato->id)->delete();

        $this->assertCount(0, $this->servico()->proposta($this->pedido([], $semContrato->fresh())));
    }

    public function test_decisao_que_a_fatura_nao_aceita_e_recusada(): void
    {
        $f = $this->cenario();
        $pedido = $this->pedido();

        foreach ([[$f['entrada'], 'cancelar'], [$f['futura'], 'devolver'], [$f['adiantada'], 'cancelar']] as [$fatura, $decisao]) {
            try {
                $this->servico()->normalizarDecisoes($pedido, [$fatura->id => $decisao]);
                $this->fail("{$decisao} não deveria valer para a fatura {$fatura->id}");
            } catch (\DomainException $e) {
                $this->assertStringContainsString('não pode ficar como', $e->getMessage());
            }
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('não pertencem a esta matrícula');

        [$outra, $outroContrato] = $this->aluno('Outra Aluna');
        $estranha = $this->fatura('2026-11-10', 100, 'pendente', 'Alheia', 0, $outroContrato);
        $this->assertNotNull($outra);

        $this->servico()->normalizarDecisoes($pedido, [$estranha->id => 'cancelar']);
    }

    // --- Aprovação ---------------------------------------------------------------------------------------------------

    public function test_aprovar_fixa_decisoes_valores_e_libera_o_distrato(): void
    {
        $f = $this->cenario();

        $aprovado = $this->aprovado(['multa_rescisoria' => 150, 'observacao' => 'Família combinou por telefone.']);

        $this->assertSame(StatusCancelamentoMatricula::Aprovado, $aprovado->status);
        $this->assertSame('2057.67', $aprovado->valor_devido, 'as duas vencidas, com multa e juros até hoje');
        $this->assertSame('1400.00', $aprovado->valor_a_devolver, 'a paga adiantada e o pago da parcial');
        $this->assertSame('150.00', $aprovado->multa_rescisoria);
        $this->assertNotNull($aprovado->distrato_gerado_em);
        $this->assertSame($this->secretaria->id, $aprovado->decidido_por_user_id);
        $this->assertSame('cancelar', $aprovado->decisoes[$f['futura']->id]);
        $this->assertSame('devolver', $aprovado->decisoes[$f['parcial']->id]);
        $this->assertSame(StatusFatura::Pendente, $f['futura']->fresh()->status, 'aprovar ainda não mexe nas faturas');
        $this->assertSame(SituacaoMatricula::ATIVA, $this->matricula->fresh()->situacao, 'nem na matrícula');
    }

    public function test_secretaria_pode_trocar_a_decisao_sugerida(): void
    {
        $f = $this->cenario();

        $aprovado = $this->aprovado(['decisoes' => [$f['futura']->id => 'manter']]);

        $this->assertSame('manter', $aprovado->decisoes[$f['futura']->id]);
        $this->assertSame('3057.67', $aprovado->valor_devido, 'a futura mantida entra no devido');
    }

    public function test_so_aprova_pedido_aguardando_a_secretaria(): void
    {
        $aprovado = $this->aprovado();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ainda aguarda a secretaria');

        $this->servico()->aprovar($aprovado, $this->secretaria);
    }

    public function test_recusar_exige_motivo_e_registra_quem_decidiu(): void
    {
        $pedido = $this->pedido();

        try {
            $this->servico()->recusar($pedido, $this->secretaria, '  ');
            $this->fail('motivo vazio deveria ser recusado');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('motivo da recusa', $e->getMessage());
        }

        $recusado = $this->servico()->recusar($pedido, $this->secretaria, 'Contrato prevê aviso prévio de 30 dias.');

        $this->assertSame(StatusCancelamentoMatricula::Recusado, $recusado->status);
        $this->assertSame('Contrato prevê aviso prévio de 30 dias.', $recusado->motivo_recusa);
        $this->assertSame($this->secretaria->id, $recusado->decidido_por_user_id);
    }

    public function test_familia_retira_enquanto_aguarda_mas_depois_de_aprovado_so_a_secretaria_encerra(): void
    {
        $familia = $this->familiaDe($this->matricula);
        $pedido = $this->servico()->solicitar($this->matricula, ['motivo' => 'outro', 'data_efetiva' => '2026-11-30'], $familia);

        $this->servico()->aprovar($pedido, $this->secretaria);

        try {
            $this->servico()->retirar($pedido->fresh(), $familia);
            $this->fail('a família não encerra um pedido já aprovado');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('só a secretaria', $e->getMessage());
        }

        $this->assertSame(StatusCancelamentoMatricula::Retirado, $this->servico()->retirar($pedido->fresh(), $this->como($this->secretaria))->status);
    }

    // --- Distrato assinado -------------------------------------------------------------------------------------------

    public function test_distrato_assinado_vai_para_o_disco_privado_e_a_troca_apaga_o_anterior(): void
    {
        $aprovado = $this->aprovado();

        $this->servico()->anexarDistratoAssinado($aprovado, UploadedFile::fake()->createWithContent('d.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"), $this->secretaria);
        $primeiro = $aprovado->fresh()->distrato_assinado_path;

        $this->assertStringStartsWith("cancelamentos_matricula/{$aprovado->id}/distrato-assinado-", $primeiro);
        $this->assertStringEndsWith('.pdf', $primeiro);
        Storage::disk('local')->assertExists($primeiro);

        $this->travel(2)->seconds();
        $this->servico()->anexarDistratoAssinado($aprovado->fresh(), UploadedFile::fake()->createWithContent('foto.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')), $this->secretaria);

        $segundo = $aprovado->fresh()->distrato_assinado_path;

        $this->assertStringEndsWith('.png', $segundo);
        Storage::disk('local')->assertMissing($primeiro);
        Storage::disk('local')->assertExists($segundo);
    }

    public function test_distrato_assinado_recusa_html_arquivo_vazio_e_pedido_nao_aprovado(): void
    {
        $aprovado = $this->aprovado();

        foreach ([UploadedFile::fake()->createWithContent('d.pdf', '<html><script>alert(1)</script></html>'), UploadedFile::fake()->createWithContent('d.pdf', '')] as $arquivo) {
            try {
                $this->servico()->anexarDistratoAssinado($aprovado, $arquivo, $this->secretaria);
                $this->fail('arquivo inválido deveria ser recusado');
            } catch (\DomainException) {
                $this->assertNull($aprovado->fresh()->distrato_assinado_path);
            }
        }

        $aguardando = $this->pedido([], $this->aluno('Outro Aluno')[0]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('depois da aprovação');

        $this->servico()->anexarDistratoAssinado($aguardando, UploadedFile::fake()->createWithContent('d.pdf', '%PDF-1.4'), $this->secretaria);
    }

    // --- Conclusão ---------------------------------------------------------------------------------------------------

    private function concluivel(array $dadosAprovacao = []): CancelamentoMatricula
    {
        $aprovado = $this->aprovado($dadosAprovacao);
        $this->servico()->anexarDistratoAssinado($aprovado, UploadedFile::fake()->createWithContent('d.pdf', '%PDF-1.4 assinado'), $this->secretaria);
        $this->travelTo(Carbon::parse('2026-11-02 09:00:00'));

        return $aprovado->fresh();
    }

    public function test_concluir_cancela_a_matricula_e_so_as_faturas_marcadas(): void
    {
        $f = $this->cenario();
        $aprovado = $this->concluivel();

        $resumo = $this->servico()->concluir($aprovado, $this->secretaria);

        $matricula = $this->matricula->fresh();

        $this->assertSame(SituacaoMatricula::CANCELADA, $matricula->situacao);
        $this->assertSame('2026-10-31', $matricula->data_desativacao->toDateString());
        $this->assertSame(3, $resumo['faturas_canceladas']);

        $this->assertSame(StatusFatura::Cancelado, $f['futura']->fresh()->status);
        $this->assertSame(StatusFatura::Cancelado, $f['adiantada']->fresh()->status, 'paga adiantada: cancelada e devolvida');
        $this->assertSame(StatusFatura::Cancelado, $f['parcial']->fresh()->status);
        $this->assertSame(StatusFatura::Pago, $f['entrada']->fresh()->status);
        $this->assertSame(StatusFatura::Pendente, $f['vencida']->fresh()->status, 'o que vence até o mês da saída continua devido');
        $this->assertSame(StatusFatura::Pendente, $f['vencida_antiga']->fresh()->status);

        $concluido = $aprovado->fresh();

        $this->assertSame(StatusCancelamentoMatricula::Concluido, $concluido->status);
        $this->assertSame($this->secretaria->id, $concluido->concluido_por_user_id);
        $this->assertTrue($concluido->devolucaoPendente());
    }

    public function test_concluir_gera_fatura_da_multa_rescisoria(): void
    {
        $this->cenario();
        $aprovado = $this->concluivel(['multa_rescisoria' => 150]);

        $resumo = $this->servico()->concluir($aprovado, $this->secretaria);

        $multa = Fatura::with('itens')->findOrFail($resumo['multa_fatura_id']);

        $this->assertSame(StatusFatura::Pendente, $multa->status);
        $this->assertSame('2026-11-12', $multa->vencimento->toDateString());
        $this->assertEqualsWithDelta(150.0, (float) $multa->valor, 0.001);
        $this->assertSame('Multa rescisória — cancelamento de matrícula', $multa->itens->first()->descricao);
        $this->assertSame($this->contrato->id, $multa->contrato_id);
    }

    public function test_concluir_cancela_a_cobranca_no_gateway_das_faturas_canceladas(): void
    {
        $f = $this->cenario();
        $f['futura']->update(['gateway' => 'fake', 'gateway_id' => 'cobranca-futura']);

        $this->mock(FakeGatewayPagamento::class, function ($mock): void {
            $mock->shouldReceive('cancelar')->once()->with('cobranca-futura')->andReturn(true);
        });

        $this->servico()->concluir($this->concluivel(), $this->secretaria);

        $this->assertSame(StatusFatura::Cancelado, $f['futura']->fresh()->status);
    }

    public function test_falha_do_gateway_nao_impede_o_cancelamento(): void
    {
        $f = $this->cenario();
        $f['futura']->update(['gateway' => 'fake', 'gateway_id' => 'cobranca-futura']);

        $this->mock(FakeGatewayPagamento::class, function ($mock): void {
            $mock->shouldReceive('cancelar')->andThrow(new \RuntimeException('gateway fora do ar'));
        });

        $this->servico()->concluir($this->concluivel(), $this->secretaria);

        $this->assertSame(StatusFatura::Cancelado, $f['futura']->fresh()->status);
        $this->assertSame(SituacaoMatricula::CANCELADA, $this->matricula->fresh()->situacao);
    }

    public function test_fatura_que_recebeu_pagamento_depois_da_aprovacao_nao_e_cancelada(): void
    {
        $f = $this->cenario();
        $aprovado = $this->concluivel();
        $this->pagamento($f['futura'], 1000);

        $resumo = $this->servico()->concluir($aprovado, $this->secretaria);

        $this->assertSame(StatusFatura::Pendente, $f['futura']->fresh()->status);
        $this->assertSame(2, $resumo['faturas_canceladas']);
        $this->assertStringContainsString('recebeu pagamento depois da aprovação', implode(' ', $resumo['avisos']));
    }

    public function test_concluir_exige_distrato_assinado_e_a_data_de_saida(): void
    {
        $aprovado = $this->aprovado();

        try {
            $this->servico()->concluir($aprovado, $this->secretaria);
            $this->fail('a saída ainda está no futuro');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Conclua a partir dessa data', $e->getMessage());
        }

        $this->travelTo(Carbon::parse('2026-11-02 09:00:00'));

        try {
            $this->servico()->concluir($aprovado, $this->secretaria);
            $this->fail('falta o distrato assinado');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Anexe o distrato assinado', $e->getMessage());
        }

        config(['cancelamento.exige_distrato_assinado' => false]);
        $this->servico()->concluir($aprovado, $this->secretaria);

        $this->assertSame(SituacaoMatricula::CANCELADA, $this->matricula->fresh()->situacao);
    }

    public function test_concluir_so_vale_para_pedido_aprovado_e_nao_se_repete(): void
    {
        $aguardando = $this->pedido();

        try {
            $this->servico()->concluir($aguardando, $this->secretaria);
            $this->fail('pedido não aprovado');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('pedido aprovado', $e->getMessage());
        }

        $aprovado = $this->servico()->aprovar($aguardando, $this->secretaria);
        $this->servico()->anexarDistratoAssinado($aprovado, UploadedFile::fake()->createWithContent('d.pdf', '%PDF-1.4'), $this->secretaria);
        $this->travelTo(Carbon::parse('2026-11-02 09:00:00'));
        $this->servico()->concluir($aprovado, $this->secretaria);

        $this->expectException(\DomainException::class);

        $this->servico()->concluir($aprovado, $this->secretaria);
    }

    public function test_rematricula_sem_contrato_e_cancelada_junto_e_a_efetivada_gera_aviso(): void
    {
        $periodo = $this->campanhaDeRematricula();
        $semContrato = Rematricula::create(['periodo_rematricula_id' => $periodo->id, 'matricula_origem_id' => $this->matricula->id, 'solicitante_user_id' => $this->secretaria->id, 'status' => StatusRematricula::DadosConfirmados]);

        $aprovado = $this->concluivel();
        $resumo = $this->servico()->concluir($aprovado, $this->secretaria);

        $this->assertSame(StatusRematricula::Cancelada, $semContrato->fresh()->status);
        $this->assertStringContainsString('rematrícula do próximo ano', implode(' ', $resumo['avisos']));
    }

    public function test_rematricula_ja_efetivada_nao_e_mexida_e_vira_aviso(): void
    {
        $periodo = $this->campanhaDeRematricula();
        [$nova, $novoContrato] = $this->aluno('Ana Lima (próximo ano)');
        $efetivada = Rematricula::create([
            'periodo_rematricula_id' => $periodo->id,
            'matricula_origem_id' => $this->matricula->id,
            'solicitante_user_id' => $this->secretaria->id,
            'status' => StatusRematricula::Confirmada,
            'nova_matricula_id' => $nova->id,
            'contrato_id' => $novoContrato->id,
        ]);

        $resumo = $this->servico()->concluir($this->concluivel(), $this->secretaria);

        $this->assertSame(StatusRematricula::Confirmada, $efetivada->fresh()->status);
        $this->assertSame(SituacaoMatricula::ATIVA, $nova->fresh()->situacao);
        $this->assertStringContainsString('já efetivada', implode(' ', $resumo['avisos']));
    }

    // --- Transferência -----------------------------------------------------------------------------------------------

    private function templateDeTransferencia(): TemplateDocumento
    {
        return TemplateDocumento::create([
            'nome' => 'Declaração de Transferência',
            'tipo' => TipoTemplateDocumento::DeclaracaoTransferencia,
            'conteudo' => '<p>Certificamos a transferência de {{ALUNO_NOME}}.</p>',
            'validade_dias' => 30,
            'is_ativo' => true,
        ]);
    }

    public function test_concluir_com_transferencia_emite_a_declaracao_mesmo_com_debitos(): void
    {
        $this->cenario();
        $this->templateDeTransferencia();
        $aprovado = $this->concluivel(['gerar_transferencia' => true, 'escola_destino' => 'Colégio Aurora']);

        $resumo = $this->servico()->concluir($aprovado, $this->secretaria);

        $transferencia = $aprovado->fresh()->transferenciaEscolar;

        $this->assertSame('emitida', $resumo['transferencia']);
        $this->assertSame('Colégio Aurora', $transferencia->escola_externa_nome);
        $this->assertSame(StatusTransferencia::Concluida, $transferencia->status);
        $this->assertNotNull($transferencia->solicitacao_documento_id);
        $this->assertSame([], $resumo['avisos']);
    }

    public function test_sem_modelo_de_declaracao_o_cancelamento_conclui_e_a_transferencia_fica_em_andamento(): void
    {
        $aprovado = $this->concluivel(['gerar_transferencia' => true]);

        $resumo = $this->servico()->concluir($aprovado, $this->secretaria);

        $transferencia = $aprovado->fresh()->transferenciaEscolar;

        $this->assertSame('pendente', $resumo['transferencia']);
        $this->assertSame(StatusTransferencia::EmAndamento, $transferencia->status);
        $this->assertSame('Não informada', $transferencia->escola_externa_nome);
        $this->assertSame(SituacaoMatricula::CANCELADA, $this->matricula->fresh()->situacao);
        $this->assertSame(StatusCancelamentoMatricula::Concluido, $aprovado->fresh()->status);
        $this->assertStringContainsString('Declaração de Transferência não emitida', implode(' ', $resumo['avisos']));
    }

    // --- Devolução ---------------------------------------------------------------------------------------------------

    public function test_registrar_devolucao_so_com_valor_pendente_e_data_nao_futura(): void
    {
        $this->cenario();
        $aprovado = $this->concluivel();
        $this->servico()->concluir($aprovado, $this->secretaria);
        $concluido = $aprovado->fresh();

        try {
            $this->servico()->registrarDevolucao($concluido, Carbon::parse('2026-11-20'), $this->secretaria);
            $this->fail('data futura');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('não pode ser futura', $e->getMessage());
        }

        $this->servico()->registrarDevolucao($concluido, Carbon::parse('2026-11-02'), $this->secretaria);

        $this->assertSame('2026-11-02', $concluido->fresh()->devolucao_realizada_em->toDateString());
        $this->assertFalse($concluido->fresh()->devolucaoPendente());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('não tem devolução pendente');

        $this->servico()->registrarDevolucao($concluido->fresh(), Carbon::parse('2026-11-02'), $this->secretaria);
    }

    // --- Tela da secretaria ------------------------------------------------------------------------------------------

    public function test_lista_abre_para_secretaria_e_coordenacao_e_nao_para_professor(): void
    {
        $this->pedido();

        Livewire::actingAs($this->como($this->secretaria))->test(ListCancelamentosMatricula::class)->assertSuccessful()->assertSee('Ana Lima');
        Livewire::actingAs($this->como($this->usuario('coordenador')))->test(ListCancelamentosMatricula::class)->assertSuccessful();
        Livewire::actingAs($this->como($this->usuario('professor')))->test(ListCancelamentosMatricula::class)->assertForbidden();
    }

    public function test_secretaria_registra_o_pedido_pela_tela(): void
    {
        Livewire::actingAs($this->como($this->secretaria))
            ->test(CreateCancelamentoMatricula::class)
            ->fillForm([
                'matricula_id' => $this->matricula->id,
                'motivo' => 'saude',
                'data_efetiva' => '2026-10-15',
                'motivo_detalhe' => 'Tratamento fora da cidade',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $pedido = CancelamentoMatricula::firstOrFail();

        $this->assertSame(OrigemCancelamentoMatricula::Escola, $pedido->origem);
        $this->assertSame($this->secretaria->id, $pedido->solicitado_por_user_id);
        $this->assertSame('2026-10-15', $pedido->data_efetiva->toDateString());
    }

    public function test_pedido_duplicado_pela_tela_e_recusado_sem_criar_outro(): void
    {
        $this->pedido();

        Livewire::actingAs($this->como($this->secretaria))
            ->test(CreateCancelamentoMatricula::class)
            ->fillForm(['matricula_id' => $this->matricula->id, 'motivo' => 'outro', 'data_efetiva' => '2026-10-31'])
            ->call('create');

        $this->assertSame(1, CancelamentoMatricula::count());
    }

    public function test_aprovar_pela_tela_usa_as_sugestoes_como_padrao(): void
    {
        $f = $this->cenario();
        $pedido = $this->pedido();

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListCancelamentosMatricula::class)
            ->assertTableActionVisible('aprovar', $pedido)
            ->assertTableActionHidden('concluir', $pedido)
            ->callTableAction('aprovar', $pedido, data: ['data_efetiva' => '2026-10-31', 'multa_rescisoria' => 0])
            ->assertHasNoTableActionErrors();

        $pedido = $pedido->fresh();

        $this->assertSame(StatusCancelamentoMatricula::Aprovado, $pedido->status);
        $this->assertSame('cancelar', $pedido->decisoes[$f['futura']->id]);
        $this->assertSame('devolver', $pedido->decisoes[$f['adiantada']->id]);
        $this->assertSame('2057.67', $pedido->valor_devido);
    }

    public function test_aprovar_pela_tela_com_decisao_trocada(): void
    {
        $f = $this->cenario();
        $pedido = $this->pedido();

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListCancelamentosMatricula::class)
            ->callTableAction('aprovar', $pedido, data: [
                'data_efetiva' => '2026-10-31',
                'decisao_'.$f['futura']->id => 'manter',
                'multa_rescisoria' => 200,
            ]);

        $pedido = $pedido->fresh();

        $this->assertSame('manter', $pedido->decisoes[$f['futura']->id]);
        $this->assertSame('200.00', $pedido->multa_rescisoria);
    }

    public function test_acoes_aparecem_conforme_a_etapa_do_pedido(): void
    {
        $this->cenario();
        $pedido = $this->pedido();
        $lista = Livewire::actingAs($this->como($this->secretaria))->test(ListCancelamentosMatricula::class);

        $lista->assertTableActionVisible('recusar', $pedido)
            ->assertTableActionHidden('distrato', $pedido)
            ->assertTableActionHidden('anexar_distrato', $pedido)
            ->assertTableActionVisible('encerrar', $pedido);

        $this->servico()->aprovar($pedido, $this->secretaria);

        Livewire::actingAs($this->como($this->secretaria))->test(ListCancelamentosMatricula::class)
            ->assertTableActionHidden('aprovar', $pedido)
            ->assertTableActionHidden('recusar', $pedido)
            ->assertTableActionVisible('distrato', $pedido)
            ->assertTableActionVisible('anexar_distrato', $pedido)
            ->assertTableActionVisible('concluir', $pedido, 'o super admin passaria; a secretaria tem Concluir')
            ->assertTableActionHidden('ver_distrato_assinado', $pedido);
    }

    public function test_coordenacao_ve_a_lista_mas_nao_decide(): void
    {
        $pedido = $this->pedido();

        Livewire::actingAs($this->como($this->usuario('coordenador')))
            ->test(ListCancelamentosMatricula::class)
            ->assertCanSeeTableRecords([$pedido])
            ->assertTableActionHidden('aprovar', $pedido)
            ->assertTableActionHidden('recusar', $pedido)
            ->assertTableActionHidden('encerrar', $pedido);
    }

    public function test_recusar_e_anexar_distrato_pela_tela(): void
    {
        $recusavel = $this->pedido();

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListCancelamentosMatricula::class)
            ->callTableAction('recusar', $recusavel, data: ['motivo_recusa' => 'Aviso prévio de 30 dias não cumprido.']);

        $this->assertSame(StatusCancelamentoMatricula::Recusado, $recusavel->fresh()->status);

        [$outra] = $this->aluno('Bia Souza');
        $aprovado = $this->servico()->aprovar($this->pedido([], $outra), $this->secretaria);

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListCancelamentosMatricula::class)
            ->callTableAction('anexar_distrato', $aprovado, data: ['arquivo' => UploadedFile::fake()->createWithContent('assinado.pdf', "%PDF-1.4\n%%EOF")])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($aprovado->fresh()->temDistratoAssinado());
    }

    public function test_concluir_e_registrar_devolucao_pela_tela(): void
    {
        $this->cenario();
        $aprovado = $this->concluivel();

        $lista = Livewire::actingAs($this->como($this->secretaria))->test(ListCancelamentosMatricula::class);
        $lista->callTableAction('concluir', $aprovado);

        $this->assertSame(StatusCancelamentoMatricula::Concluido, $aprovado->fresh()->status);

        $lista = Livewire::actingAs($this->como($this->secretaria))->test(ListCancelamentosMatricula::class);
        $lista->assertTableActionVisible('registrar_devolucao', $aprovado)
            ->callTableAction('registrar_devolucao', $aprovado, data: ['data' => '2026-11-02']);

        $this->assertSame('2026-11-02', $aprovado->fresh()->devolucao_realizada_em->toDateString());
    }

    // --- PDF do distrato e cópia assinada ----------------------------------------------------------------------------

    public function test_distrato_em_pdf_so_depois_da_aprovacao_e_so_para_quem_pode(): void
    {
        $this->cenario();
        $familia = $this->familiaDe($this->matricula);
        $pedido = $this->pedido();

        $this->actingAs($this->como($this->secretaria))->get(route('cancelamentos-matricula.distrato', $pedido))->assertNotFound();

        $this->servico()->aprovar($pedido, $this->secretaria);

        $resposta = $this->actingAs($this->como($familia))->get(route('cancelamentos-matricula.distrato', $pedido));
        $resposta->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $resposta->headers->get('content-type'));

        $this->actingAs($this->como($this->secretaria))->get(route('cancelamentos-matricula.distrato', $pedido))->assertOk();
        $this->actingAs($this->como($this->usuario('coordenador')))->get(route('cancelamentos-matricula.distrato', $pedido))->assertOk();
    }

    public function test_distrato_negado_para_outra_familia_professor_e_visitante(): void
    {
        $pedido = $this->aprovado();
        [$outra] = $this->aluno('Bia Souza');

        $this->actingAs($this->como($this->familiaDe($outra)))->get(route('cancelamentos-matricula.distrato', $pedido))->assertForbidden();
        $this->actingAs($this->como($this->usuario('professor')))->get(route('cancelamentos-matricula.distrato', $pedido))->assertForbidden();

        auth()->logout();
        $this->get(route('cancelamentos-matricula.distrato', $pedido))->assertRedirect();
    }

    public function test_distrato_depois_de_concluido_continua_abrindo_mesmo_com_faturas_canceladas(): void
    {
        $this->cenario();
        $aprovado = $this->concluivel();
        $this->servico()->concluir($aprovado, $this->secretaria);

        $this->actingAs($this->como($this->secretaria))->get(route('cancelamentos-matricula.distrato', $aprovado))->assertOk();
    }

    public function test_copia_assinada_abre_para_a_familia_e_a_secretaria_e_nao_para_outras(): void
    {
        $aprovado = $this->aprovado();
        $this->servico()->anexarDistratoAssinado($aprovado, UploadedFile::fake()->createWithContent('d.pdf', "%PDF-1.4\n%%EOF"), $this->secretaria);
        $caminho = $aprovado->fresh()->distrato_assinado_path;
        $familia = $this->familiaDe($this->matricula);
        [$outra] = $this->aluno('Bia Souza');

        $this->actingAs($this->como($familia))->get(route('documentos.visualizar', ['path' => $caminho]))->assertOk();
        $this->actingAs($this->como($this->secretaria))->get(route('documentos.visualizar', ['path' => $caminho]))->assertOk();
        $this->actingAs($this->como($this->familiaDe($outra)))->get(route('documentos.visualizar', ['path' => $caminho]))->assertForbidden();
        $this->actingAs($this->como($this->usuario('professor')))->get(route('documentos.visualizar', ['path' => $caminho]))->assertForbidden();
    }

    // --- Portal da Família -------------------------------------------------------------------------------------------

    public function test_familia_pede_o_cancelamento_pelo_portal(): void
    {
        $familia = $this->familiaDe($this->matricula);

        Livewire::actingAs($this->como($familia))
            ->test(CancelamentoDeMatricula::class)
            ->assertSee('Ana Lima')
            ->assertSee('Pedir cancelamento')
            ->callAction('solicitar', data: [
                'motivo' => 'mudanca_cidade',
                'motivo_detalhe' => 'Mudança de emprego',
                'data_efetiva' => '2026-11-30',
                'ciente' => true,
            ], arguments: ['matricula' => $this->matricula->id])
            ->assertHasNoActionErrors();

        $pedido = CancelamentoMatricula::firstOrFail();

        $this->assertSame(OrigemCancelamentoMatricula::Familia, $pedido->origem);
        $this->assertSame($familia->id, $pedido->solicitado_por_user_id);
        $this->assertSame('2026-11-30', $pedido->data_efetiva->toDateString());
    }

    public function test_portal_exige_a_ciencia_e_data_a_partir_de_hoje(): void
    {
        $familia = $this->familiaDe($this->matricula);

        Livewire::actingAs($this->como($familia))
            ->test(CancelamentoDeMatricula::class)
            ->callAction('solicitar', data: ['motivo' => 'outro', 'data_efetiva' => '2026-11-30', 'ciente' => false], arguments: ['matricula' => $this->matricula->id])
            ->assertHasActionErrors(['ciente']);

        $this->assertSame(0, CancelamentoMatricula::count());
    }

    public function test_portal_nao_aceita_matricula_de_outra_familia(): void
    {
        [$alheia] = $this->aluno('Bia Souza');
        $familia = $this->familiaDe($this->matricula);

        Livewire::actingAs($this->como($familia))
            ->test(CancelamentoDeMatricula::class)
            ->assertDontSee('Bia Souza')
            ->callAction('solicitar', data: ['motivo' => 'outro', 'data_efetiva' => '2026-11-30', 'ciente' => true], arguments: ['matricula' => $alheia->id]);

        $this->assertSame(0, CancelamentoMatricula::count());
    }

    public function test_aluno_menor_nao_pede_o_proprio_cancelamento_mas_o_maior_de_idade_sim(): void
    {
        $alunoMenor = $this->usuario('aluno');
        $this->matricula->pessoa->users()->save($alunoMenor);

        Livewire::actingAs($this->como($alunoMenor))
            ->test(CancelamentoDeMatricula::class)
            ->assertDontSee('Pedir cancelamento')
            ->assertSee('Não há aluno cuja matrícula você possa cancelar');

        [$adulta] = $this->aluno('Carla Adulta', '1998-04-02');
        $alunoMaior = $this->usuario('aluno');
        $adulta->pessoa->users()->save($alunoMaior);

        Livewire::actingAs($this->como($alunoMaior))
            ->test(CancelamentoDeMatricula::class)
            ->assertSee('Carla Adulta')
            ->assertSee('Pedir cancelamento');
    }

    public function test_responsavel_financeiro_sem_vinculo_de_responsavel_tambem_pede(): void
    {
        $pagador = Pessoa::create(['nome' => 'Tio Pagador']);
        ResponsavelFinanceiro::create(['contrato_id' => $this->contrato->id, 'pessoa_id' => $pagador->id, 'percentual' => 100]);
        $user = $this->usuario('responsavel');
        $pagador->users()->save($user);

        Livewire::actingAs($this->como($user))
            ->test(CancelamentoDeMatricula::class)
            ->assertSee('Ana Lima')
            ->assertSee('Pedir cancelamento');
    }

    public function test_portal_mostra_o_andamento_e_a_familia_retira_o_pedido_que_aguarda(): void
    {
        $familia = $this->familiaDe($this->matricula);
        $pedido = $this->servico()->solicitar($this->matricula, ['motivo' => 'outro', 'data_efetiva' => '2026-11-30'], $familia);

        Livewire::actingAs($this->como($familia))
            ->test(CancelamentoDeMatricula::class)
            ->assertSee('Aguardando a secretaria')
            ->assertDontSee('Pedir cancelamento')
            ->callAction('retirar', arguments: ['pedido' => $pedido->id])
            ->assertHasNoActionErrors();

        $this->assertSame(StatusCancelamentoMatricula::Retirado, $pedido->fresh()->status);
    }

    public function test_portal_mostra_o_motivo_da_recusa_e_o_distrato_aprovado(): void
    {
        $this->cenario();
        $familia = $this->familiaDe($this->matricula);
        $recusado = $this->servico()->solicitar($this->matricula, ['motivo' => 'outro', 'data_efetiva' => '2026-11-30'], $familia);
        $this->servico()->recusar($recusado, $this->secretaria, 'Falta o aviso prévio.');

        Livewire::actingAs($this->como($familia))->test(CancelamentoDeMatricula::class)->assertSee('Falta o aviso prévio.')->assertSee('Pedir cancelamento');

        $aprovado = $this->servico()->solicitar($this->matricula, ['motivo' => 'outro', 'data_efetiva' => '2026-11-30'], $familia);
        $this->servico()->aprovar($aprovado, $this->secretaria);

        Livewire::actingAs($this->como($familia))->test(CancelamentoDeMatricula::class)->assertSee('Baixar distrato')->assertSee('Pedido aprovado');
    }

    public function test_portal_alerta_sobre_faturas_vencidas_antes_do_pedido(): void
    {
        $this->cenario();
        $familia = $this->familiaDe($this->matricula);

        Livewire::actingAs($this->como($familia))
            ->test(CancelamentoDeMatricula::class)
            ->assertSee('2 fatura(s) vencida(s)')
            ->assertSee('R$ 2.057,67');
    }
}
