<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FormatoRelatorio;
use App\Enums\FrequenciaAgendamento;
use App\Enums\OrigemExecucaoRelatorio;
use App\Enums\StatusExecucaoRelatorio;
use App\Filament\Resources\RelatoriosSalvos\Pages\CreateRelatorioSalvo;
use App\Filament\Resources\RelatoriosSalvos\Pages\EditRelatorioSalvo;
use App\Filament\Resources\RelatoriosSalvos\Pages\ListRelatoriosSalvos;
use App\Models\ContaPagar;
use App\Models\ExecucaoRelatorio;
use App\Models\Fornecedor;
use App\Models\RelatorioSalvo;
use App\Models\User;
use App\Notifications\RelatorioProntoNotification;
use App\Services\Relatorios\CatalogoDeRelatorios;
use App\Services\Relatorios\ConfiguracaoDeRelatorioService;
use App\Services\Relatorios\RelatorioExecutorService;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Relatórios configuráveis e agendados (Onda 72): as fontes e a permissão de cada uma, a geração em planilha e em PDF, os
 * filtros e a ordem, o limite de linhas, o agendamento (cálculo, execução, aviso, desligamento), o download e as telas.
 *
 * "Hoje" é quinta-feira, 22/10/2026, 10:00.
 */
class RelatoriosConfiguraveisTest extends TestCase
{
    use RefreshDatabase;

    private User $secretaria;

    private User $coordenador;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-22 10:00:00'));
        Storage::fake('local');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['super_admin', 'admin', 'secretaria', 'coordenador'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        $basicas = ['ViewAny:RelatorioSalvo', 'View:RelatorioSalvo', 'Create:RelatorioSalvo', 'Update:RelatorioSalvo', 'Delete:RelatorioSalvo', 'Executar:RelatorioSalvo'];

        $permissoes = [
            // A secretaria vê contas a pagar; a coordenação, matrículas; a administração, as duas e os relatórios de todos.
            'secretaria' => [...$basicas, 'ViewAny:ContaPagar'],
            'coordenador' => [...$basicas, 'ViewAny:Matricula'],
            'admin' => [...$basicas, 'ViewAny:ContaPagar', 'ViewAny:Matricula', 'VerTodos:RelatorioSalvo'],
        ];

        foreach ($permissoes as $papel => $nomes) {
            foreach ($nomes as $nome) {
                Role::findByName($papel)->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
            }
        }

        $this->secretaria = $this->usuario('secretaria');
        $this->coordenador = $this->usuario('coordenador');
        $this->admin = $this->usuario('admin');
    }

    private function usuario(string $papel): User
    {
        $usuario = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);

        return $usuario;
    }

    private function como(User $usuario): User
    {
        session(['active_role' => $usuario->getRoleNames()->first()]);

        return $usuario;
    }

    private function servico(): RelatorioExecutorService
    {
        return app(RelatorioExecutorService::class);
    }

    private function contas(): void
    {
        $papelaria = Fornecedor::create(['razao_social' => 'Papelaria Central']);
        $limpeza = Fornecedor::create(['razao_social' => 'Limpeza Brilho']);

        ContaPagar::factory()->create(['descricao' => 'Papel A4', 'valor' => 1234.5, 'vencimento' => '2026-10-10', 'status' => 'pago', 'fornecedor_id' => $papelaria->id, 'data_pagamento' => '2026-10-09']);
        ContaPagar::factory()->create(['descricao' => 'Produtos de limpeza', 'valor' => 300, 'vencimento' => '2026-10-20', 'status' => 'pendente', 'fornecedor_id' => $limpeza->id]);
        ContaPagar::factory()->create(['descricao' => 'Toner', 'valor' => 80, 'vencimento' => '2026-11-05', 'status' => 'pendente', 'fornecedor_id' => $papelaria->id]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function relatorio(User $dono, array $extra = []): RelatorioSalvo
    {
        return RelatorioSalvo::create($extra + [
            'nome' => 'Contas do mês',
            'fonte' => 'contas_a_pagar',
            'colunas' => ['descricao', 'fornecedor', 'vencimento', 'valor', 'status'],
            'filtros' => [],
            'formato' => FormatoRelatorio::Csv,
            'criado_por_user_id' => $dono->id,
        ]);
    }

    /**
     * Linhas do CSV guardado, sem o BOM.
     *
     * @return list<string>
     */
    private function linhasDoCsv(ExecucaoRelatorio $execucao): array
    {
        $conteudo = Storage::disk('local')->get((string) $execucao->arquivo_path);

        return array_values(array_filter(explode("\n", ltrim($conteudo, "\xEF\xBB\xBF"))));
    }

    // --- Fontes e permissões -----------------------------------------------------------------------------------------

    public function test_cada_pessoa_ve_so_as_fontes_cuja_permissao_ela_tem(): void
    {
        $catalogo = app(CatalogoDeRelatorios::class);

        $this->como($this->secretaria);
        $this->assertSame(['contas_a_pagar'], array_keys($catalogo->permitidasPara($this->secretaria)));

        $this->como($this->coordenador);
        $this->assertSame(['matriculas'], array_keys($catalogo->permitidasPara($this->coordenador)));

        $this->como($this->admin);
        $this->assertSame(['matriculas', 'contas_a_pagar'], array_keys(array_intersect_key($catalogo->permitidasPara($this->admin), array_flip(['matriculas', 'contas_a_pagar']))));
    }

    public function test_todas_as_fontes_declaram_colunas_filtros_e_permissao_validos(): void
    {
        foreach (app(CatalogoDeRelatorios::class)->todas() as $chave => $fonte) {
            $this->assertSame($chave, $fonte->chave());
            $this->assertNotSame('', $fonte->titulo());
            $this->assertNotEmpty($fonte->colunas(), "{$chave} sem colunas");
            $this->assertMatchesRegularExpression('/^[A-Za-z]+:[A-Za-z]+$/', $fonte->permissao());

            foreach ($fonte->colunas() as $chaveDaColuna => $coluna) {
                $this->assertSame($chaveDaColuna, $coluna->chave);
            }

            foreach ($fonte->filtros() as $chaveDoFiltro => $filtro) {
                $this->assertSame($chaveDoFiltro, $filtro->chave);
                $this->assertContains($filtro->tipo, ['data', 'opcao', 'numero']);
            }

            // Cada fonte roda vazia, com todas as colunas, sem erro de consulta.
            $this->assertIsArray($fonte->linhas([], 5), "{$chave} não rodou");
        }
    }

    // --- Geração -----------------------------------------------------------------------------------------------------

    public function test_planilha_leva_so_as_colunas_escolhidas_formatadas_e_ordenadas(): void
    {
        $this->contas();
        $relatorio = $this->relatorio($this->secretaria, ['ordenar_por' => 'valor', 'direcao' => 'desc']);

        $execucao = $this->servico()->executar($relatorio, OrigemExecucaoRelatorio::Manual, $this->secretaria);

        $this->assertSame(StatusExecucaoRelatorio::Sucesso, $execucao->status);
        $this->assertSame(3, $execucao->linhas);
        $this->assertFalse($execucao->truncado);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($execucao->arquivo_path)), $execucao->arquivo_hash);
        $this->assertSame(today()->addDays(30)->toDateString(), $execucao->expira_em->toDateString());
        $this->assertStringStartsWith("\xEF\xBB\xBF", Storage::disk('local')->get($execucao->arquivo_path));

        $this->assertSame([
            'Descrição;Fornecedor;Vencimento;Valor;Situação',
            '"Papel A4";"Papelaria Central";10/10/2026;1234,50;Pago',
            '"Produtos de limpeza";"Limpeza Brilho";20/10/2026;300,00;Pendente',
            'Toner;"Papelaria Central";05/11/2026;80,00;Pendente',
        ], $this->linhasDoCsv($execucao));
    }

    public function test_ordem_crescente_e_filtros_de_periodo_e_de_situacao(): void
    {
        $this->contas();
        $relatorio = $this->relatorio($this->secretaria, [
            'ordenar_por' => 'valor',
            'direcao' => 'asc',
            'filtros' => ['vencimento_de' => '2026-10-15', 'vencimento_ate' => '2026-12-31', 'status' => 'pendente'],
        ]);

        $linhas = $this->linhasDoCsv($this->servico()->executar($relatorio, OrigemExecucaoRelatorio::Manual, $this->secretaria));

        $this->assertCount(3, $linhas);
        $this->assertStringStartsWith('Toner;', $linhas[1]);
        $this->assertStringStartsWith('"Produtos de limpeza";', $linhas[2]);
    }

    public function test_filtros_que_a_fonte_nao_declara_ou_com_valor_invalido_sao_descartados(): void
    {
        $fonte = app(CatalogoDeRelatorios::class)->da('contas_a_pagar');

        $validos = $this->servico()->filtrosValidos($fonte, [
            'status' => 'inexistente',
            'vencimento_de' => '2026-10-01; DROP TABLE users',
            'vencimento_ate' => '2026-10-31',
            'coluna_secreta' => 'x',
            'conta_pagars.id' => '1',
        ]);

        $this->assertSame(['vencimento_ate' => '2026-10-31'], $validos);
    }

    public function test_coluna_que_a_fonte_nao_oferece_e_ignorada_e_sem_nenhuma_valida_a_execucao_erra(): void
    {
        $this->contas();

        $parcial = $this->servico()->executar($this->relatorio($this->secretaria, ['colunas' => ['descricao', 'senha_do_banco', 'descricao']]), OrigemExecucaoRelatorio::Manual, $this->secretaria);
        $this->assertSame(['Descrição', '"Papel A4"', '"Produtos de limpeza"', 'Toner'], $this->linhasDoCsv($parcial));

        $nenhuma = $this->servico()->executar($this->relatorio($this->secretaria, ['colunas' => ['senha_do_banco']]), OrigemExecucaoRelatorio::Manual, $this->secretaria);
        $this->assertSame(StatusExecucaoRelatorio::Erro, $nenhuma->status);
        $this->assertStringContainsString('ao menos uma coluna', $nenhuma->erro);
        $this->assertNull($nenhuma->arquivo_path);
    }

    public function test_texto_que_comeca_como_formula_e_neutralizado_na_planilha(): void
    {
        ContaPagar::factory()->create(['descricao' => '=HYPERLINK("http://mal.example","clique")', 'valor' => 10, 'vencimento' => '2026-10-10']);

        $linhas = $this->linhasDoCsv($this->servico()->executar($this->relatorio($this->secretaria, ['colunas' => ['descricao']]), OrigemExecucaoRelatorio::Manual, $this->secretaria));

        $this->assertStringContainsString("'=HYPERLINK", $linhas[1]);
        $this->assertStringNotContainsString('"=HYPERLINK', $linhas[1]);
    }

    public function test_formato_pdf_gera_um_pdf(): void
    {
        $this->contas();

        $execucao = $this->servico()->executar($this->relatorio($this->secretaria, ['formato' => FormatoRelatorio::Pdf]), OrigemExecucaoRelatorio::Manual, $this->secretaria);

        $this->assertSame(StatusExecucaoRelatorio::Sucesso, $execucao->status);
        $this->assertStringEndsWith('.pdf', $execucao->arquivo_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($execucao->arquivo_path));
    }

    public function test_passou_do_limite_o_arquivo_sai_cortado_e_a_execucao_avisa(): void
    {
        $this->contas();
        config(['relatorios.limite_linhas' => 2]);

        $execucao = $this->servico()->executar($this->relatorio($this->secretaria), OrigemExecucaoRelatorio::Manual, $this->secretaria);

        $this->assertTrue($execucao->truncado);
        $this->assertSame(2, $execucao->linhas);
        $this->assertCount(3, $this->linhasDoCsv($execucao), 'cabeçalho mais duas linhas');
    }

    public function test_sem_permissao_da_fonte_a_execucao_erra_sem_gerar_arquivo(): void
    {
        $this->contas();
        $relatorio = $this->relatorio($this->coordenador);

        $execucao = $this->servico()->executar($relatorio, OrigemExecucaoRelatorio::Manual, $this->coordenador);

        $this->assertSame(StatusExecucaoRelatorio::Erro, $execucao->status);
        $this->assertStringContainsString('Sem permissão', $execucao->erro);
        $this->assertNull($execucao->arquivo_path);
        $this->assertSame([], Storage::disk('local')->allFiles('relatorios'));
    }

    public function test_fonte_que_sumiu_do_catalogo_vira_erro_de_negocio(): void
    {
        $execucao = $this->servico()->executar($this->relatorio($this->secretaria, ['fonte' => 'fonte_antiga']), OrigemExecucaoRelatorio::Manual, $this->secretaria);

        $this->assertSame(StatusExecucaoRelatorio::Erro, $execucao->status);
        $this->assertStringContainsString('não existe mais', $execucao->erro);
    }

    // --- Download ----------------------------------------------------------------------------------------------------

    public function test_download_vale_para_o_dono_os_destinatarios_e_quem_ve_todos(): void
    {
        $this->contas();
        $destinatario = $this->usuario('secretaria');
        $outro = $this->usuario('secretaria');
        $relatorio = $this->relatorio($this->secretaria, ['destinatarios' => [$destinatario->id]]);
        $execucao = $this->servico()->executar($relatorio, OrigemExecucaoRelatorio::Manual, $this->secretaria);
        $rota = route('relatorios.execucoes.download', $execucao);

        $this->como($this->secretaria);
        $this->actingAs($this->secretaria)->get($rota)
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->como($destinatario);
        $this->actingAs($destinatario)->get($rota)->assertOk();

        $this->como($this->admin);
        $this->actingAs($this->admin)->get($rota)->assertOk();

        // Outra pessoa com a mesma permissão da fonte, mas sem relação com o relatório.
        $this->como($outro);
        $this->actingAs($outro)->get($rota)->assertForbidden();
    }

    public function test_perder_a_permissao_da_fonte_fecha_o_arquivo_ate_para_o_dono(): void
    {
        $this->contas();
        $execucao = $this->servico()->executar($this->relatorio($this->secretaria), OrigemExecucaoRelatorio::Manual, $this->secretaria);

        Role::findByName('secretaria')->revokePermissionTo('ViewAny:ContaPagar');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->como($this->secretaria);
        $this->actingAs($this->secretaria)->get(route('relatorios.execucoes.download', $execucao))->assertForbidden();
    }

    public function test_download_recusa_arquivo_alterado_ausente_ou_de_execucao_com_erro(): void
    {
        $this->contas();
        $execucao = $this->servico()->executar($this->relatorio($this->secretaria), OrigemExecucaoRelatorio::Manual, $this->secretaria);
        $this->como($this->secretaria);

        Storage::disk('local')->put($execucao->arquivo_path, "adulterado\n");
        $this->actingAs($this->secretaria)->get(route('relatorios.execucoes.download', $execucao))->assertStatus(409);

        Storage::disk('local')->delete($execucao->arquivo_path);
        $this->actingAs($this->secretaria)->get(route('relatorios.execucoes.download', $execucao))->assertNotFound();

        $comErro = $this->servico()->executar($this->relatorio($this->secretaria, ['colunas' => ['x']]), OrigemExecucaoRelatorio::Manual, $this->secretaria);
        $this->actingAs($this->secretaria)->get(route('relatorios.execucoes.download', $comErro))->assertNotFound();
    }

    // --- Agendamento -------------------------------------------------------------------------------------------------

    public function test_proxima_execucao_diaria_semanal_e_mensal(): void
    {
        // Quinta-feira, 22/10/2026, 10:00.
        $calcular = fn (FrequenciaAgendamento $f, ?int $dia, string $hora): string => (new RelatorioSalvo(['frequencia' => $f, 'dia' => $dia, 'hora' => $hora]))->calcularProximaExecucao(now())->format('Y-m-d H:i');

        $this->assertSame('2026-10-22 18:30', $calcular(FrequenciaAgendamento::Diaria, null, '18:30'), 'ainda dá tempo hoje');
        $this->assertSame('2026-10-23 07:00', $calcular(FrequenciaAgendamento::Diaria, null, '07:00'), 'a hora de hoje já passou');
        $this->assertSame('2026-10-22 10:05', $calcular(FrequenciaAgendamento::Diaria, null, '10:05'));

        $this->assertSame('2026-10-26 08:00', $calcular(FrequenciaAgendamento::Semanal, 1, '08:00'), 'próxima segunda');
        $this->assertSame('2026-10-22 18:00', $calcular(FrequenciaAgendamento::Semanal, 4, '18:00'), 'hoje é quinta e a hora ainda não passou');
        $this->assertSame('2026-10-29 08:00', $calcular(FrequenciaAgendamento::Semanal, 4, '08:00'), 'quinta que vem');

        $this->assertSame('2026-11-05 08:00', $calcular(FrequenciaAgendamento::Mensal, 5, '08:00'));
        $this->assertSame('2026-10-25 08:00', $calcular(FrequenciaAgendamento::Mensal, 25, '08:00'));
        $this->assertSame('2026-11-01 00:00', $calcular(FrequenciaAgendamento::Mensal, 1, '00:00'));
    }

    public function test_proxima_execucao_mensal_atravessa_dezembro_e_nunca_cai_no_passado(): void
    {
        $this->travelTo(Carbon::parse('2026-12-28 12:00:00'));

        $proxima = (new RelatorioSalvo(['frequencia' => FrequenciaAgendamento::Mensal, 'dia' => 28, 'hora' => '09:00']))->calcularProximaExecucao(now());

        $this->assertSame('2027-01-28 09:00', $proxima->format('Y-m-d H:i'));
        $this->assertTrue($proxima->gt(now()));
        $this->assertNull((new RelatorioSalvo(['frequencia' => FrequenciaAgendamento::Diaria, 'hora' => '25:99']))->calcularProximaExecucao(now()));
    }

    public function test_configuracao_normaliza_e_calcula_a_proxima_execucao(): void
    {
        $this->como($this->secretaria);

        $dados = app(ConfiguracaoDeRelatorioService::class)->normalizar([
            'nome' => '  Contas da semana ',
            'fonte' => 'contas_a_pagar',
            'colunas' => ['descricao', 'valor', 'coluna_fantasma'],
            'filtros' => ['status' => 'pendente', 'nada' => 'x'],
            'ordenar_por' => 'fornecedor',
            'direcao' => 'desc',
            'formato' => 'pdf',
            'agendado' => true,
            'frequencia' => 'semanal',
            'dia' => 1,
            'hora' => '08:00:00',
            'destinatarios' => [$this->secretaria->id],
        ], $this->secretaria);

        $this->assertSame('Contas da semana', $dados['nome']);
        $this->assertSame(['descricao', 'valor'], $dados['colunas']);
        $this->assertSame(['status' => 'pendente'], $dados['filtros']);
        $this->assertNull($dados['ordenar_por'], 'a coluna da ordem precisa estar entre as escolhidas');
        $this->assertSame(FormatoRelatorio::Pdf, $dados['formato']);
        $this->assertSame('08:00', $dados['hora']);
        $this->assertSame('2026-10-26 08:00', $dados['proxima_execucao_em']->format('Y-m-d H:i'));
        $this->assertSame($this->secretaria->id, $dados['criado_por_user_id']);
    }

    public function test_configuracao_recusa_o_que_nao_fecha(): void
    {
        $this->como($this->secretaria);
        $semAcesso = $this->usuario('coordenador');
        $base = ['nome' => 'X', 'fonte' => 'contas_a_pagar', 'colunas' => ['descricao'], 'agendado' => true, 'frequencia' => 'diaria', 'hora' => '08:00', 'destinatarios' => [$this->secretaria->id]];

        $casos = [
            'fonte sem permissão' => [['fonte' => 'matriculas'] + $base, 'não tem permissão'],
            'sem colunas' => [['colunas' => []] + $base, 'ao menos uma coluna'],
            'sem nome' => [['nome' => '  '] + $base, 'nome'],
            'hora inválida' => [['hora' => '8h'] + $base, 'hora do agendamento'],
            'dia do mês 29' => [['frequencia' => 'mensal', 'dia' => 29] + $base, 'dia do mês'],
            'sem dia da semana' => [['frequencia' => 'semanal', 'dia' => 9] + $base, 'dia da semana'],
            'sem destinatário' => [['destinatarios' => []] + $base, 'quem recebe'],
            'destinatário sem acesso aos dados' => [['destinatarios' => [$semAcesso->id]] + $base, 'pode ver os dados'],
            'destinatário que não existe' => [['destinatarios' => [999999]] + $base, 'pode ver os dados'],
        ];

        foreach ($casos as $nome => [$dados, $trecho]) {
            try {
                app(ConfiguracaoDeRelatorioService::class)->normalizar($dados, $this->secretaria);
                $this->fail("{$nome}: devia ser recusado");
            } catch (\DomainException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage(), $nome);
            }
        }
    }

    public function test_salvar_sem_mexer_na_agenda_nao_muda_a_proxima_execucao(): void
    {
        $this->como($this->secretaria);
        $proxima = Carbon::parse('2026-10-23 08:00:00');
        $relatorio = $this->relatorio($this->secretaria, ['agendado' => true, 'frequencia' => FrequenciaAgendamento::Diaria, 'hora' => '08:00', 'destinatarios' => [$this->secretaria->id], 'proxima_execucao_em' => $proxima]);

        $dados = app(ConfiguracaoDeRelatorioService::class)->normalizar([
            'nome' => 'Outro nome', 'fonte' => 'contas_a_pagar', 'colunas' => ['descricao'], 'agendado' => true,
            'frequencia' => 'diaria', 'hora' => '08:00', 'destinatarios' => [$this->secretaria->id],
        ], $this->secretaria, $relatorio);

        $this->assertSame('2026-10-23 08:00', $dados['proxima_execucao_em']->format('Y-m-d H:i'));

        $dados = app(ConfiguracaoDeRelatorioService::class)->normalizar([
            'nome' => 'Outro nome', 'fonte' => 'contas_a_pagar', 'colunas' => ['descricao'], 'agendado' => true,
            'frequencia' => 'diaria', 'hora' => '07:00', 'destinatarios' => [$this->secretaria->id],
        ], $this->secretaria, $relatorio);

        $this->assertSame('2026-10-23 07:00', $dados['proxima_execucao_em']->format('Y-m-d H:i'), 'mudou a hora: recalcula');
    }

    public function test_agendador_executa_o_vencido_avanca_a_agenda_e_avisa_so_quem_pode_ver_os_dados(): void
    {
        $this->contas();
        Notification::fake();
        $semAcesso = $this->usuario('coordenador');
        $outraSecretaria = $this->usuario('secretaria');

        $relatorio = $this->relatorio($this->secretaria, [
            'agendado' => true, 'frequencia' => FrequenciaAgendamento::Diaria, 'hora' => '08:00',
            'destinatarios' => [$this->secretaria->id, $outraSecretaria->id, $semAcesso->id],
            'proxima_execucao_em' => now()->subMinutes(3),
        ]);
        $futuro = $this->relatorio($this->secretaria, ['nome' => 'Amanhã', 'agendado' => true, 'frequencia' => FrequenciaAgendamento::Diaria, 'hora' => '08:00', 'destinatarios' => [$this->secretaria->id], 'proxima_execucao_em' => now()->addHour()]);

        $resumo = $this->servico()->executarAgendados();

        $this->assertSame(['executados' => 1, 'com_erro' => 0, 'desligados' => 0], $resumo);

        $execucao = $relatorio->execucoes()->firstOrFail();
        $this->assertSame(OrigemExecucaoRelatorio::Agendada, $execucao->origem);
        $this->assertSame(StatusExecucaoRelatorio::Sucesso, $execucao->status);

        $relatorio->refresh();
        $this->assertSame('2026-10-23 08:00', $relatorio->proxima_execucao_em->format('Y-m-d H:i'));
        $this->assertSame('2026-10-22 10:00', $relatorio->ultima_execucao_em->format('Y-m-d H:i'));

        Notification::assertSentTo([$this->secretaria, $outraSecretaria], RelatorioProntoNotification::class);
        Notification::assertNotSentTo($semAcesso, RelatorioProntoNotification::class);
        $this->assertSame(0, $futuro->execucoes()->count());
    }

    public function test_duas_rodadas_do_agendador_nao_geram_o_mesmo_relatorio_duas_vezes(): void
    {
        $this->contas();
        Notification::fake();
        $relatorio = $this->relatorio($this->secretaria, ['agendado' => true, 'frequencia' => FrequenciaAgendamento::Diaria, 'hora' => '08:00', 'destinatarios' => [$this->secretaria->id], 'proxima_execucao_em' => now()->subMinute()]);

        $this->assertSame(1, $this->servico()->executarAgendados()['executados']);
        $this->assertSame(0, $this->servico()->executarAgendados()['executados']);
        $this->assertSame(1, $relatorio->execucoes()->count());
    }

    public function test_tres_falhas_seguidas_desligam_o_agendamento_e_avisam_o_dono(): void
    {
        $relatorio = $this->relatorio($this->coordenador, [
            'agendado' => true, 'frequencia' => FrequenciaAgendamento::Diaria, 'hora' => '08:00',
            'destinatarios' => [$this->coordenador->id], 'proxima_execucao_em' => now()->subMinute(),
            // Contas a pagar: a coordenação não tem essa permissão, então toda execução falha.
        ]);

        foreach ([1, 2] as $rodada) {
            $this->assertSame(0, $this->servico()->executarAgendados()['desligados'], "rodada {$rodada}");
            $relatorio->refresh()->update(['proxima_execucao_em' => now()->subMinute()]);
        }

        $this->assertSame(1, $this->servico()->executarAgendados()['desligados']);

        $relatorio->refresh();
        $this->assertFalse($relatorio->agendado);
        $this->assertNull($relatorio->proxima_execucao_em);
        $this->assertSame(3, $relatorio->execucoes()->count());
        $this->assertSame(1, $this->coordenador->notifications()->count());
        $this->assertSame(0, $this->servico()->executarAgendados()['executados'], 'desligado, não roda mais');
    }

    public function test_arquivos_vencidos_sao_apagados_e_o_registro_da_execucao_permanece(): void
    {
        $this->contas();
        $execucao = $this->servico()->executar($this->relatorio($this->secretaria), OrigemExecucaoRelatorio::Manual, $this->secretaria);
        $recente = $this->servico()->executar($this->relatorio($this->secretaria, ['nome' => 'Recente']), OrigemExecucaoRelatorio::Manual, $this->secretaria);
        $caminho = $execucao->arquivo_path;
        $execucao->update(['expira_em' => today()->subDay()]);

        $this->assertSame(1, $this->servico()->limparArquivosVencidos());

        Storage::disk('local')->assertMissing($caminho);
        Storage::disk('local')->assertExists($recente->arquivo_path);
        $execucao->refresh();
        $this->assertNull($execucao->arquivo_path);
        $this->assertFalse($execucao->temArquivo());
        $this->assertSame(3, $execucao->linhas);
    }

    public function test_comandos_do_agendador_rodam(): void
    {
        $this->contas();
        Notification::fake();
        $this->relatorio($this->secretaria, ['agendado' => true, 'frequencia' => FrequenciaAgendamento::Diaria, 'hora' => '08:00', 'destinatarios' => [$this->secretaria->id], 'proxima_execucao_em' => now()->subMinute()]);

        $this->artisan('relatorios:executar-agendados')->expectsOutputToContain('1 relatório(s) executado(s)')->assertSuccessful();
        $this->artisan('relatorios:limpar-arquivos')->expectsOutputToContain('0 arquivo(s) apagado(s)')->assertSuccessful();
    }

    // --- Telas -------------------------------------------------------------------------------------------------------

    public function test_lista_mostra_so_os_relatorios_da_pessoa_e_a_administracao_ve_todos(): void
    {
        $meu = $this->relatorio($this->secretaria, ['nome' => 'Meu relatório']);
        $alheio = $this->relatorio($this->coordenador, ['nome' => 'Do coordenador', 'fonte' => 'matriculas', 'colunas' => ['aluno']]);

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListRelatoriosSalvos::class)
            ->assertCanSeeTableRecords([$meu])
            ->assertCanNotSeeTableRecords([$alheio]);

        Livewire::actingAs($this->como($this->admin))
            ->test(ListRelatoriosSalvos::class)
            ->assertCanSeeTableRecords([$meu, $alheio]);
    }

    public function test_pessoa_nao_edita_nem_executa_relatorio_de_outra(): void
    {
        $alheio = $this->relatorio($this->coordenador, ['fonte' => 'matriculas', 'colunas' => ['aluno']]);

        $this->como($this->secretaria);
        $this->assertFalse($this->secretaria->can('update', $alheio));
        $this->assertFalse($this->secretaria->can('executar', $alheio));
        $this->assertFalse($this->secretaria->can('delete', $alheio));

        $this->como($this->admin);
        $this->assertTrue($this->admin->can('update', $alheio));
    }

    public function test_botao_gera_agora_e_mostra_o_resultado_ou_o_motivo(): void
    {
        $this->contas();
        $relatorio = $this->relatorio($this->secretaria);

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListRelatoriosSalvos::class)
            ->callAction(TestAction::make('executar')->table($relatorio))
            ->assertNotified('Relatório gerado');

        $this->assertSame(1, $relatorio->execucoes()->count());

        Role::findByName('secretaria')->revokePermissionTo('ViewAny:ContaPagar');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // O usuário em memória guarda os papéis (e as permissões deles) já carregados: a nova requisição o lê de novo.
        Livewire::actingAs($this->como(User::query()->findOrFail($this->secretaria->id)))
            ->test(ListRelatoriosSalvos::class)
            ->callAction(TestAction::make('executar')->table($relatorio))
            ->assertNotified('Não foi possível gerar o relatório');
    }

    public function test_criar_pela_tela_grava_o_relatorio_normalizado(): void
    {
        Livewire::actingAs($this->como($this->secretaria))
            ->test(CreateRelatorioSalvo::class)
            ->fillForm([
                'nome' => 'Contas pendentes',
                'fonte' => 'contas_a_pagar',
                'formato' => 'csv',
            ])
            ->fillForm([
                'colunas' => ['descricao', 'valor'],
                'filtros' => ['status' => 'pendente'],
                'ordenar_por' => 'valor',
                'direcao' => 'desc',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $relatorio = RelatorioSalvo::query()->firstOrFail();
        $this->assertSame($this->secretaria->id, $relatorio->criado_por_user_id);
        $this->assertSame('contas_a_pagar', $relatorio->fonte);
        $this->assertSame(['descricao', 'valor'], $relatorio->colunas);
        $this->assertSame(['status' => 'pendente'], $relatorio->filtros);
        $this->assertFalse($relatorio->agendado);
        $this->assertNull($relatorio->proxima_execucao_em);
    }

    public function test_criar_com_agendamento_agenda_a_proxima_rodada(): void
    {
        Livewire::actingAs($this->como($this->secretaria))
            ->test(CreateRelatorioSalvo::class)
            ->fillForm(['nome' => 'Toda segunda', 'fonte' => 'contas_a_pagar', 'formato' => 'pdf'])
            ->fillForm(['colunas' => ['descricao', 'valor'], 'agendado' => true])
            // O dia da semana só existe depois de escolher a frequência (campo dependente), como na tela.
            ->fillForm(['frequencia' => 'semanal'])
            ->fillForm(['dia' => 1, 'hora' => '08:00', 'destinatarios' => [$this->secretaria->id]])
            ->call('create')
            ->assertHasNoFormErrors();

        $relatorio = RelatorioSalvo::query()->firstOrFail();
        $this->assertTrue($relatorio->agendado);
        $this->assertSame('2026-10-26 08:00', $relatorio->proxima_execucao_em->format('Y-m-d H:i'));
        $this->assertSame([$this->secretaria->id], $relatorio->destinatarios);
        $this->assertSame('Toda segunda às 08:00', $relatorio->descricaoDoAgendamento());
    }

    public function test_criar_com_destinatario_sem_acesso_aos_dados_nao_grava(): void
    {
        // A lista de destinatários já só oferece quem pode ver os dados; o servidor confere de novo (ver o teste do serviço).
        $semAcesso = $this->usuario('coordenador');

        Livewire::actingAs($this->como($this->secretaria))
            ->test(CreateRelatorioSalvo::class)
            ->fillForm(['nome' => 'Para quem não pode', 'fonte' => 'contas_a_pagar', 'formato' => 'csv'])
            ->fillForm(['colunas' => ['descricao'], 'agendado' => true, 'frequencia' => 'diaria', 'hora' => '08:00', 'destinatarios' => [$semAcesso->id]])
            ->call('create')
            ->assertHasFormErrors(['destinatarios.0']);

        $this->assertSame(0, RelatorioSalvo::count());
    }

    public function test_editar_pela_tela_mantem_o_dono_e_atualiza_as_colunas(): void
    {
        $relatorio = $this->relatorio($this->secretaria);

        Livewire::actingAs($this->como($this->admin))
            ->test(EditRelatorioSalvo::class, ['record' => $relatorio->getKey()])
            ->fillForm(['nome' => 'Contas (revisado)', 'colunas' => ['descricao']])
            ->call('save')
            ->assertHasNoFormErrors();

        $relatorio->refresh();
        $this->assertSame('Contas (revisado)', $relatorio->nome);
        $this->assertSame(['descricao'], $relatorio->colunas);
        $this->assertSame($this->secretaria->id, $relatorio->criado_por_user_id, 'quem edita não vira dono');
    }
}
