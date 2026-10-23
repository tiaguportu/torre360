<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MeioContatoFamilia;
use App\Enums\ResultadoContatoFamilia;
use App\Enums\StatusCasoFrequencia;
use App\Enums\TipoTemplateDocumento;
use App\Filament\Pages\FaltasExcessivas;
use App\Filament\Resources\CasosFrequencia\CasoFrequenciaResource;
use App\Filament\Resources\CasosFrequencia\Pages\ListCasosFrequencia;
use App\Filament\Resources\CasosFrequencia\Pages\ViewCasoFrequencia;
use App\Filament\Resources\ConselhosTutelares\Pages\CreateConselhoTutelar;
use App\Models\CasoFrequencia;
use App\Models\ConselhoTutelar;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\ResponsavelFinanceiro;
use App\Models\Serie;
use App\Models\TemplateDocumento;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\CargaLetivaService;
use App\Services\Documentos\AssinaturaDocumentoService;
use App\Services\Documentos\SignatariosSugeridosService;
use App\Services\FaltasExcessivasService;
use App\Services\Pendencias\Fontes\CasosDeFrequenciaParados;
use App\Services\Pendencias\Fontes\FrequenciaAcimaDoLimite;
use App\Services\Pendencias\MesaDePendencias;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Faltas excessivas e comunicação ao Conselho Tutelar (Onda 57).
 *
 * "Hoje" é quinta-feira, 22/10/2026. A turma tem 40 aulas previstas no período (01/09 a 10/10): o limite de notificação é
 * 7,5% delas, ou seja, mais de 3 faltas.
 */
class FaltasExcessivasTest extends TestCase
{
    use RefreshDatabase;

    private PeriodoLetivo $periodo;

    private Unidade $unidade;

    private Turma $turma;

    private Matricula $ana;

    private Matricula $bia;

    private Matricula $caio;

    /** @var list<int> */
    private array $aulas = [];

    private User $coordenador;

    private User $secretaria;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-22 09:00:00'));
        Storage::fake('local');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['super_admin', 'admin', 'secretaria', 'coordenador', 'professor'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        $concessoes = [
            'coordenador' => ['CargaLetiva:Turma', 'Apurar:CasoFrequencia', 'ViewAny:CasoFrequencia', 'View:CasoFrequencia', 'Create:CasoFrequencia', 'Update:CasoFrequencia', 'Enviar:CasoFrequencia', 'ViewAny:DocumentoAssinatura', 'View:DocumentoAssinatura'],
            'secretaria' => ['ViewAny:CasoFrequencia', 'View:CasoFrequencia', 'Enviar:CasoFrequencia', 'ViewAny:ConselhoTutelar', 'Create:ConselhoTutelar', 'Update:ConselhoTutelar', 'View:ConselhoTutelar', 'Create:DocumentoAssinatura', 'ViewAny:DocumentoAssinatura', 'View:DocumentoAssinatura'],
            'admin' => ['Apurar:CasoFrequencia', 'ViewAny:CasoFrequencia', 'View:CasoFrequencia', 'Create:CasoFrequencia', 'Update:CasoFrequencia', 'Enviar:CasoFrequencia', 'Create:DocumentoAssinatura', 'ViewAny:DocumentoAssinatura', 'View:DocumentoAssinatura'],
        ];

        foreach ($concessoes as $papel => $permissoes) {
            foreach ($permissoes as $permissao) {
                Role::findByName($papel)->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
            }
        }

        $this->coordenador = $this->usuario('coordenador');
        $this->secretaria = $this->usuario('secretaria');
        $this->admin = $this->usuario('admin');

        $this->unidade = Unidade::create(['nome' => 'Unidade Sede', 'cnpj' => '12.345.678/0001-90']);
        $curso = Curso::create(['unidade_id' => $this->unidade->id, 'nome_externo' => 'Fundamental', 'nome_interno' => 'EF']);
        $serie = Serie::create(['curso_id' => $curso->id, 'nome' => '7º Ano', 'sistema_avaliacao' => 'Nota']);
        $this->periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-02', 'data_fim' => '2026-12-18']);
        $this->turma = Turma::factory()->create(['nome' => '7º A', 'periodo_letivo_id' => $this->periodo->id, 'serie_id' => $serie->id]);

        $disciplina = Disciplina::factory()->create(['nome' => 'Matemática']);
        $professor = Pessoa::factory()->create(['nome' => 'Prof. Rui']);

        for ($i = 0; $i < 40; $i++) {
            $this->aulas[] = DB::table('cronograma_aula')->insertGetId([
                'turma_id' => $this->turma->id,
                'disciplina_id' => $disciplina->id,
                'pessoa_id' => $professor->id,
                'data' => Carbon::parse('2026-09-01')->addDays($i)->toDateString(),
                'hora_inicio' => '08:00:00',
                'hora_fim' => '08:50:00',
                'conteudo_ministrado' => 'Conteúdo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->ana = $this->aluno('Ana Lima');
        $this->bia = $this->aluno('Bia Souza');
        $this->caio = $this->aluno('Caio Dias');

        $this->chamada($this->ana, faltas: 4);
        $this->chamada($this->bia, faltas: 3);
        $this->chamada($this->caio, faltas: 5, justificadas: 2);
    }

    private function usuario(string $papel, array $atributos = []): User
    {
        $usuario = User::factory()->create($atributos + ['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);

        return $usuario;
    }

    private function como(User $usuario): User
    {
        session(['active_role' => $usuario->getRoleNames()->first()]);

        return $usuario;
    }

    private function servico(): FaltasExcessivasService
    {
        return app(FaltasExcessivasService::class);
    }

    private function aluno(string $nome, string $situacao = 'ativa'): Matricula
    {
        $matricula = Matricula::factory()->create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => $nome, 'data_nascimento' => '2013-05-10'])->id,
            'turma_id' => $this->turma->id,
            'situacao' => $situacao,
        ]);
        Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => 1000, 'data_aceite' => '2026-01-05']);

        return $matricula;
    }

    /**
     * A chamada de todas as aulas do aluno: as primeiras `faltas` aulas são faltas (as primeiras `justificadas` delas com
     * justificativa) e o resto, presenças. Inserção direta para não disparar o aviso de falta ao responsável.
     */
    private function chamada(Matricula $aluno, int $faltas, int $justificadas = 0): void
    {
        $linhas = [];

        foreach ($this->aulas as $indice => $aulaId) {
            $ausente = $indice < $faltas;
            $linhas[] = [
                'matricula_id' => $aluno->id,
                'cronograma_aula_id' => $aulaId,
                'situacao' => $ausente ? 'ausente' : 'presente',
                'justificada' => $ausente && $indice < $justificadas,
                'justificativa' => $ausente && $indice < $justificadas ? 'Atestado médico' : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('frequencia_escolar')->insert($linhas);
    }

    private function acima(): \Illuminate\Support\Collection
    {
        return $this->servico()->acimaDoLimite($this->periodo);
    }

    private function caso(?Matricula $aluno = null): CasoFrequencia
    {
        return $this->servico()->abrirCaso($aluno ?? $this->ana, $this->periodo, $this->coordenador);
    }

    private function contato(CasoFrequencia $caso, string $quando = '2026-10-20'): void
    {
        $this->servico()->registrarContato($caso, Carbon::parse($quando), MeioContatoFamilia::Telefone, ResultadoContatoFamilia::NaoAtendeu, null, $this->coordenador);
    }

    // --- Apuração ----------------------------------------------------------------------------------------------------

    public function test_o_limite_e_30_por_cento_do_percentual_permitido_de_faltas(): void
    {
        $this->assertSame(7.5, $this->servico()->limitePercentual());

        config(['frequencia_legal.frequencia_minima' => 80.0]);
        $this->assertSame(6.0, $this->servico()->limitePercentual());
    }

    public function test_acima_do_limite_traz_quem_passou_de_tres_faltas_do_mais_faltoso_para_o_menos(): void
    {
        $linhas = $this->acima();

        $this->assertSame(['Caio Dias', 'Ana Lima'], $linhas->map(fn (array $l): string => $l['matricula']->pessoa->nome)->all(), 'a Bia, com 3, não passou');

        $caio = $linhas->first();
        $this->assertSame(40, $caio['aulas_previstas']);
        $this->assertSame(3, $caio['limite_aulas']);
        $this->assertSame(5, $caio['faltas']);
        $this->assertSame(2, $caio['justificadas']);
        $this->assertSame(3, $caio['injustificadas']);
        $this->assertSame(12.5, $caio['percentual']);
        $this->assertNull($caio['caso']);
    }

    public function test_criterio_de_injustificadas_tira_quem_tem_atestado_para_as_faltas_que_sobram(): void
    {
        config(['frequencia_legal.notificacao.criterio' => 'injustificadas']);

        $nomes = $this->acima()->map(fn (array $l): string => $l['matricula']->pessoa->nome)->all();

        $this->assertSame(['Ana Lima'], $nomes, 'o Caio tem 5 faltas, mas só 3 injustificadas');
    }

    public function test_so_alunos_ativos_e_aula_realizada_entram_na_conta(): void
    {
        $duda = $this->aluno('Duda Reis', 'cancelada');
        $this->chamada($duda, faltas: 10);

        // A aula 1 não aconteceu: sai das faltas da Ana e das aulas previstas (39 → limite 2).
        DB::table('cronograma_aula')->where('id', $this->aulas[0])->update(['nao_realizada_em' => now(), 'motivo_nao_realizacao' => 'Chuva']);

        $linhas = $this->acima();
        $nomes = $linhas->map(fn (array $l): string => $l['matricula']->pessoa->nome)->all();

        $this->assertNotContains('Duda Reis', $nomes, 'matrícula cancelada não entra');
        $ana = $linhas->first(fn (array $l): bool => $l['matricula']->id === $this->ana->id);
        $this->assertSame(39, $ana['aulas_previstas']);
        $this->assertSame(2, $ana['limite_aulas']);
        $this->assertSame(3, $ana['faltas'], 'as 4 faltas menos a da aula que não aconteceu');
    }

    public function test_turma_sem_cronograma_nao_tem_base_para_a_conta(): void
    {
        DB::table('cronograma_aula')->delete();

        $this->assertTrue($this->acima()->isEmpty());
    }

    public function test_filtrar_por_turma(): void
    {
        $outra = Turma::factory()->create(['nome' => '8º B', 'periodo_letivo_id' => $this->periodo->id]);

        $this->assertCount(2, $this->servico()->acimaDoLimite($this->periodo, $this->turma->id));
        $this->assertCount(0, $this->servico()->acimaDoLimite($this->periodo, $outra->id));
    }

    // --- Caso --------------------------------------------------------------------------------------------------------

    public function test_abrir_caso_guarda_a_apuracao_do_dia_e_sugere_o_conselho_da_unidade(): void
    {
        $geral = ConselhoTutelar::create(['nome' => 'Conselho Geral', 'is_ativo' => true]);
        $daUnidade = ConselhoTutelar::create(['nome' => 'Conselho da Sede', 'unidade_id' => $this->unidade->id, 'is_ativo' => true]);
        ConselhoTutelar::create(['nome' => 'Conselho Inativo', 'unidade_id' => $this->unidade->id, 'is_ativo' => false]);

        $caso = $this->caso();

        $this->assertSame(StatusCasoFrequencia::Aberto, $caso->status);
        $this->assertSame(4, $caso->faltas);
        $this->assertSame(0, $caso->faltas_justificadas);
        $this->assertSame(40, $caso->aulas_previstas);
        $this->assertSame(3, $caso->limite_aulas);
        $this->assertSame('10.00', $caso->percentual_faltas);
        $this->assertSame($daUnidade->id, $caso->conselho_tutelar_id);
        $this->assertNotSame($geral->id, $caso->conselho_tutelar_id);
        $this->assertSame($this->coordenador->id, $caso->aberto_por_user_id);
    }

    public function test_sem_conselho_da_unidade_vale_o_geral(): void
    {
        $geral = ConselhoTutelar::create(['nome' => 'Conselho Geral', 'is_ativo' => true]);

        $this->assertSame($geral->id, $this->caso()->conselho_tutelar_id);
    }

    public function test_so_abre_caso_de_quem_esta_acima_do_limite_e_um_por_vez(): void
    {
        try {
            $this->servico()->abrirCaso($this->bia, $this->periodo, $this->coordenador);
            $this->fail('a Bia não passou do limite');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('não está acima do limite', $e->getMessage());
        }

        $this->caso();

        try {
            $this->caso();
            $this->fail('já há um caso em andamento');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('já tem um caso', $e->getMessage());
        }

        // Com o caso aberto, a lista de alunos acima do limite mostra o caso.
        $this->assertNotNull($this->acima()->first(fn (array $l): bool => $l['matricula']->id === $this->ana->id)['caso']);

        // Encerrado o caso, pode abrir outro.
        $this->servico()->encerrar(CasoFrequencia::query()->firstOrFail(), 'Frequência regularizada', $this->coordenador);
        $this->assertSame(StatusCasoFrequencia::Aberto, $this->caso()->status);
    }

    public function test_registrar_contato_muda_o_status_e_guarda_a_observacao_cifrada(): void
    {
        $caso = $this->caso();

        $this->servico()->registrarContato($caso, Carbon::parse('2026-10-20'), MeioContatoFamilia::Whatsapp, ResultadoContatoFamilia::Compromisso, 'A mãe disse que o menino está com depressão', $this->coordenador);

        $this->assertSame(StatusCasoFrequencia::EmContato, $caso->refresh()->status);

        $bruto = DB::table('contatos_caso_frequencia')->value('observacao');
        $this->assertStringNotContainsString('depressão', (string) $bruto, 'cifrado no banco');
        $this->assertSame('A mãe disse que o menino está com depressão', $caso->contatos()->first()->observacao);

        $log = Activity::query()->where('log_name', 'frequencia')->where('description', 'Contato com a família registrado.')->firstOrFail();
        $this->assertStringNotContainsString('depressão', json_encode($log->properties, JSON_UNESCAPED_UNICODE), 'o histórico leva o meio e o resultado, não o que a família disse');
        $this->assertSame('whatsapp', $log->properties['meio']);
    }

    public function test_contato_no_futuro_ou_em_caso_encerrado_e_recusado(): void
    {
        $caso = $this->caso();

        try {
            $this->servico()->registrarContato($caso, Carbon::parse('2026-10-25'), MeioContatoFamilia::Telefone, ResultadoContatoFamilia::Atendeu);
            $this->fail('data futura');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('futuro', $e->getMessage());
        }

        $this->servico()->encerrar($caso, 'Aluno transferido', $this->coordenador);

        try {
            $this->contato($caso);
            $this->fail('caso encerrado');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('encerrado', $e->getMessage());
        }
    }

    public function test_o_envio_ao_conselho_exige_contato_com_a_familia_antes(): void
    {
        $caso = $this->caso();

        try {
            $this->servico()->marcarNotificado($caso, now(), 'PROT-1', null, $this->secretaria);
            $this->fail('sem contato com a família');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('tentativa de contato', $e->getMessage());
        }

        $this->contato($caso);
        $notificado = $this->servico()->marcarNotificado($caso, now(), 'PROT-1', null, $this->secretaria);

        $this->assertSame(StatusCasoFrequencia::Notificado, $notificado->status);
        $this->assertSame('PROT-1', $notificado->protocolo_externo);
        $this->assertSame($this->secretaria->id, $notificado->notificado_por_user_id);
        $this->assertTrue($notificado->foiNotificado());

        try {
            $this->servico()->marcarNotificado($caso, now());
            $this->fail('já notificado');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('já foi notificado', $e->getMessage());
        }
    }

    public function test_a_exigencia_de_contato_pode_ser_desligada_na_configuracao_e_a_data_nao_vai_ao_futuro(): void
    {
        config(['frequencia_legal.notificacao.exigir_contato_antes_do_oficio' => false]);
        $caso = $this->caso();

        try {
            $this->servico()->marcarNotificado($caso, now()->addDay());
            $this->fail('data futura');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('futuro', $e->getMessage());
        }

        $this->assertSame(StatusCasoFrequencia::Notificado, $this->servico()->marcarNotificado($caso, now())->status);
    }

    public function test_resposta_do_conselho_so_depois_do_envio_e_fica_cifrada(): void
    {
        $caso = $this->caso();
        $this->contato($caso);

        try {
            $this->servico()->registrarResposta($caso, 'Vamos visitar a família', now(), $this->secretaria);
            $this->fail('ainda não enviado');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('depois de registrar o envio', $e->getMessage());
        }

        $this->servico()->marcarNotificado($caso, now(), null, null, $this->secretaria);

        try {
            $this->servico()->registrarResposta($caso, '   ', now());
            $this->fail('resposta vazia');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Escreva a resposta', $e->getMessage());
        }

        $respondido = $this->servico()->registrarResposta($caso, 'Vamos visitar a família', now(), $this->secretaria);

        $this->assertSame('Vamos visitar a família', $respondido->resposta);
        $this->assertStringNotContainsString('visitar', (string) DB::table('casos_frequencia')->value('resposta'));
    }

    public function test_encerrar_exige_motivo_e_nao_deixa_mexer_mais(): void
    {
        $caso = $this->caso();

        try {
            $this->servico()->encerrar($caso, '  ', $this->coordenador);
            $this->fail('sem motivo');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('motivo', $e->getMessage());
        }

        $encerrado = $this->servico()->encerrar($caso, 'Frequência regularizada', $this->coordenador);

        $this->assertSame(StatusCasoFrequencia::Encerrado, $encerrado->status);
        $this->assertFalse($encerrado->estaAberto());
        $this->assertSame($this->coordenador->id, $encerrado->encerrado_por_user_id);

        $this->expectException(\DomainException::class);
        $this->servico()->encerrar($caso, 'De novo', $this->coordenador);
    }

    public function test_justificar_faltas_marca_so_as_ausencias_do_aluno_e_atualizar_refaz_os_numeros_do_caso(): void
    {
        $caso = $this->caso($this->ana);
        $ids = FrequenciaEscolar::query()->where('matricula_id', $this->ana->id)->where('situacao', 'ausente')->pluck('id')->all();
        $presenca = FrequenciaEscolar::query()->where('matricula_id', $this->ana->id)->where('situacao', 'presente')->value('id');
        $deOutroAluno = FrequenciaEscolar::query()->where('matricula_id', $this->bia->id)->where('situacao', 'ausente')->value('id');

        $total = $this->servico()->justificarFaltas($this->ana, [...array_slice($ids, 0, 2), $presenca, $deOutroAluno], 'Atestado médico', $this->coordenador);

        $this->assertSame(2, $total, 'só as ausências da própria Ana');
        $this->assertFalse((bool) FrequenciaEscolar::query()->find($presenca)->justificada);
        $this->assertFalse((bool) FrequenciaEscolar::query()->find($deOutroAluno)->justificada);

        $atualizado = $this->servico()->atualizarApuracao($caso, $this->coordenador);

        $this->assertSame(4, $atualizado->faltas, 'a falta justificada segue contando na lei');
        $this->assertSame(2, $atualizado->faltas_justificadas);
    }

    public function test_justificar_exige_texto_e_faltas(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('justificativa');

        $this->servico()->justificarFaltas($this->ana, [1], '  ', $this->coordenador);
    }

    public function test_atualizar_apuracao_continua_valendo_quando_o_aluno_deixa_de_passar_do_limite(): void
    {
        $caso = $this->caso($this->ana);
        // Com 4 faltas e 3 justificadas, no critério de injustificadas a Ana sai da lista, mas o caso se atualiza.
        $ids = FrequenciaEscolar::query()->where('matricula_id', $this->ana->id)->where('situacao', 'ausente')->pluck('id')->all();
        $this->servico()->justificarFaltas($this->ana, array_slice($ids, 0, 3), 'Atestado', $this->coordenador);
        config(['frequencia_legal.notificacao.criterio' => 'injustificadas']);

        $atualizado = $this->servico()->atualizarApuracao($caso, $this->coordenador);

        $this->assertSame(3, $atualizado->faltas_justificadas);
        $this->assertSame(4, $atualizado->faltas);
    }

    // --- Ofício ------------------------------------------------------------------------------------------------------

    private function modeloDoOficio(bool $ativo = true): TemplateDocumento
    {
        return TemplateDocumento::create([
            'nome' => 'Ofício ao Conselho Tutelar',
            'tipo' => TipoTemplateDocumento::OficioConselhoTutelar,
            'conteudo' => '<p>Aluno {{ALUNO_NOME}}, {{TURMA_NOME}}, {{FALTAS}} faltas de {{AULAS_PREVISTAS}} ({{PERCENTUAL_FALTAS}}). Responsáveis: {{RESPONSAVEIS}}.</p>{{TENTATIVAS_CONTATO}}<p>{{CONSELHO_NOME}} — {{CONSELHO_MUNICIPIO}}</p>{{BLOCO_ASSINATURAS}}',
            'validade_dias' => 0,
            'is_ativo' => $ativo,
        ]);
    }

    public function test_oficio_sai_do_modelo_com_os_dados_do_caso_e_sem_o_que_a_familia_disse(): void
    {
        ConselhoTutelar::create(['nome' => 'Conselho da Sede', 'municipio' => 'Belo Horizonte', 'uf' => 'MG', 'is_ativo' => true]);
        $pai = Pessoa::create(['nome' => 'Marina Lima', 'telefone' => '(31) 99999-0000']);
        ResponsavelFinanceiro::create(['contrato_id' => Contrato::query()->where('matricula_id', $this->ana->id)->value('id'), 'pessoa_id' => $pai->id, 'percentual' => 100]);
        $caso = $this->caso($this->ana);
        $this->servico()->registrarContato($caso, Carbon::parse('2026-10-20'), MeioContatoFamilia::Telefone, ResultadoContatoFamilia::Atendeu, 'Segredo da família', $this->coordenador);
        $diretora = Pessoa::create(['nome' => 'Diretora Clara', 'email' => 'clara@escola.test']);
        $this->unidade->representantesLegais()->attach($diretora->id, ['cargo' => 'Diretora']);
        $modelo = $this->modeloDoOficio();

        $sugestao = app(SignatariosSugeridosService::class)->para($caso);
        $documento = app(AssinaturaDocumentoService::class)->criar($modelo, $caso, $sugestao['assinantes'], $this->admin);

        $this->assertSame(['Diretora Clara'], collect($sugestao['assinantes'])->pluck('nome')->all(), 'a direção assina; a família não');
        $this->assertStringContainsString('Ana Lima', $documento->conteudo);
        $this->assertStringContainsString('4 faltas de 40 (10,0%)', $documento->conteudo);
        $this->assertStringContainsString('Marina Lima (', $documento->conteudo);
        $this->assertStringContainsString('(31) 99999-0000', $documento->conteudo);
        $this->assertStringContainsString('20/10/2026 — Telefone: A família atendeu', $documento->conteudo);
        $this->assertStringContainsString('Belo Horizonte/MG', $documento->conteudo);
        $this->assertStringNotContainsString('Segredo da família', $documento->conteudo, 'a observação do contato não vai no ofício');
        $this->assertStringNotContainsString('{{', $documento->conteudo);
        $this->assertSame(CasoFrequencia::class, $documento->documentavel_type);
    }

    public function test_oficio_assinado_fica_no_historico_do_caso_e_a_familia_nao_abre_o_pdf(): void
    {
        $caso = $this->caso($this->ana);
        $modelo = $this->modeloDoOficio();
        $documento = app(AssinaturaDocumentoService::class)->criar($modelo, $caso, [['nome' => 'Diretora Clara', 'email' => 'clara@escola.test']], $this->admin);

        $responsavel = $this->usuario('professor');
        $mae = Pessoa::create(['nome' => 'Marina Lima']);
        $mae->users()->save($responsavel);
        $mae->alunos()->attach($this->ana->pessoa_id);

        $this->assertFalse($documento->isAccessibleBy($responsavel), 'o ofício ao Conselho não é da família');

        $caso->documentoAssinado($documento);

        $this->assertSame(1, Activity::query()->where('description', 'Ofício ao Conselho Tutelar assinado.')->count());
        $this->assertSame('Caso de faltas excessivas', $documento->tipoDoRegistro());
    }

    public function test_o_catalogo_do_contexto_do_caso_e_o_preenchimento_nao_se_afastam(): void
    {
        $macros = app(\App\Services\Documentos\MacrosDocumentoService::class);
        $caso = $this->caso();

        $valores = $macros->valores(\App\Enums\ContextoDocumento::CasoFrequencia, $caso);

        $this->assertEqualsCanonicalizing($macros->variaveisDoContexto(\App\Enums\ContextoDocumento::CasoFrequencia), array_keys($valores));
        $this->assertSame('Nenhuma tentativa de contato registrada.', strip_tags($valores['{{TENTATIVAS_CONTATO}}']));
        $this->assertSame('Ana Lima', $macros->rotulo($caso));
    }

    // --- Telas -------------------------------------------------------------------------------------------------------

    public function test_a_pagina_lista_os_alunos_acima_do_limite_com_as_faltas_justificadas(): void
    {
        Livewire::actingAs($this->como($this->coordenador))
            ->test(FaltasExcessivas::class)
            ->assertSuccessful()
            ->assertSee('Ana Lima')
            ->assertSee('Caio Dias')
            ->assertDontSee('Bia Souza')
            ->assertSee('7,5%')
            ->assertSee('Sem caso');
    }

    public function test_so_quem_pode_apurar_abre_a_pagina(): void
    {
        $this->actingAs($this->como($this->secretaria))->get('/admin/academico/faltas-excessivas')->assertForbidden();
        $this->actingAs($this->como($this->coordenador))->get('/admin/academico/faltas-excessivas')->assertOk();
    }

    public function test_abrir_caso_pela_pagina_leva_ao_caso(): void
    {
        Livewire::actingAs($this->como($this->coordenador))
            ->test(FaltasExcessivas::class)
            ->callAction('abrirCaso', data: ['observacao' => 'Aluno novo na escola'], arguments: ['matricula' => $this->ana->id])
            ->assertNotified('Caso aberto')
            ->assertRedirect();

        $caso = CasoFrequencia::query()->firstOrFail();

        $this->assertSame($this->ana->id, $caso->matricula_id);
        $this->assertSame('Aluno novo na escola', $caso->observacao);
        $this->assertStringNotContainsString('Aluno novo', (string) DB::table('casos_frequencia')->value('observacao'), 'a observação interna fica cifrada');
    }

    public function test_a_matricula_vem_do_cliente_e_so_vale_se_estiver_na_lista(): void
    {
        Livewire::actingAs($this->como($this->coordenador))
            ->test(FaltasExcessivas::class)
            ->callAction('abrirCaso', data: [], arguments: ['matricula' => $this->bia->id]);

        $this->assertSame(0, CasoFrequencia::query()->count(), 'a Bia não está acima do limite');
    }

    public function test_justificar_pela_pagina(): void
    {
        $ids = FrequenciaEscolar::query()->where('matricula_id', $this->ana->id)->where('situacao', 'ausente')->pluck('id')->all();

        Livewire::actingAs($this->como($this->coordenador))
            ->test(FaltasExcessivas::class)
            ->callAction('justificar', data: ['faltas' => array_slice($ids, 0, 2), 'justificativa' => 'Atestado do dia'], arguments: ['matricula' => $this->ana->id])
            ->assertNotified('2 falta(s) justificada(s)');

        $this->assertSame(2, FrequenciaEscolar::query()->where('matricula_id', $this->ana->id)->where('justificada', true)->count());
    }

    public function test_pagina_do_caso_registra_contato_envio_resposta_e_encerra(): void
    {
        $caso = $this->caso($this->ana);

        $tela = Livewire::actingAs($this->como($this->coordenador))->test(ViewCasoFrequencia::class, ['record' => $caso->id]);

        $tela->assertSuccessful()
            ->assertSee('Ana Lima')
            ->assertActionVisible('contato')
            ->assertActionHidden('resposta')
            ->callAction('notificado', data: ['quando' => '2026-10-21'])
            ->assertNotified('Não foi possível concluir');

        $tela->callAction('contato', data: ['data' => '2026-10-20', 'meio' => 'telefone', 'resultado' => 'nao_atendeu', 'observacao' => 'Caixa postal'])
            ->assertNotified('Contato registrado');

        $this->assertSame(StatusCasoFrequencia::EmContato, $caso->refresh()->status);
        $this->assertSame(1, $caso->contatos()->count());

        $tela->callAction('notificado', data: ['quando' => '2026-10-21', 'protocolo' => 'PROT-9'])
            ->assertNotified('Envio registrado')
            ->assertActionHidden('notificado')
            ->assertActionVisible('resposta');

        $tela->callAction('resposta', data: ['quando' => '2026-10-22', 'resposta' => 'Visita agendada'])
            ->assertNotified('Resposta registrada')
            ->callAction('encerrar', data: ['motivo' => 'Frequência regularizada'])
            ->assertNotified('Caso encerrado')
            ->assertActionHidden('contato')
            ->assertActionHidden('encerrar')
            ->assertActionHidden('notificado')
            ->assertActionHidden('resposta');

        $this->assertSame(StatusCasoFrequencia::Encerrado, $caso->refresh()->status);
    }

    public function test_secretaria_registra_o_envio_mas_nao_o_contato_nem_encerra(): void
    {
        $caso = $this->caso($this->ana);
        $this->contato($caso);

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ViewCasoFrequencia::class, ['record' => $caso->id])
            ->assertActionHidden('contato')
            ->assertActionHidden('encerrar')
            ->assertActionVisible('notificado')
            ->assertActionVisible('oficio');
    }

    public function test_super_admin_nao_ve_acao_que_o_estado_do_caso_nao_permite(): void
    {
        $superAdmin = $this->usuario('super_admin');
        $caso = $this->caso($this->ana);
        $this->servico()->encerrar($caso, 'Frequência regularizada', $this->coordenador);

        Livewire::actingAs($this->como($superAdmin))
            ->test(ViewCasoFrequencia::class, ['record' => $caso->id])
            ->assertActionHidden('contato')
            ->assertActionHidden('encerrar')
            ->assertActionHidden('notificado')
            ->assertActionHidden('atualizar')
            ->assertActionHidden('oficio');
    }

    public function test_gerar_oficio_pela_pagina_monta_o_documento_e_avisa_quando_falta_modelo_ou_direcao(): void
    {
        $caso = $this->caso($this->ana);

        $tela = Livewire::actingAs($this->como($this->admin))->test(ViewCasoFrequencia::class, ['record' => $caso->id]);

        $tela->callAction('oficio')->assertNotified('Não há modelo de ofício ativo');

        $this->modeloDoOficio();
        $tela->callAction('oficio')->assertNotified('Não foi possível montar o ofício');

        $diretora = Pessoa::create(['nome' => 'Diretora Clara', 'email' => 'clara@escola.test']);
        $this->unidade->representantesLegais()->attach($diretora->id, ['cargo' => 'Diretora']);

        $tela->callAction('oficio')->assertRedirect();

        $this->assertSame(1, $caso->documentosAssinatura()->count());
    }

    public function test_a_lista_de_casos_mostra_contatos_e_filtra_por_situacao(): void
    {
        $aberto = $this->caso($this->ana);
        $outro = $this->caso($this->caio);
        $this->servico()->encerrar($outro, 'Transferido', $this->coordenador);
        $this->contato($aberto);

        Livewire::actingAs($this->como($this->secretaria))
            ->test(ListCasosFrequencia::class)
            ->assertCanSeeTableRecords([$aberto, $outro])
            ->filterTable('status', [StatusCasoFrequencia::Encerrado->value])
            ->assertCanSeeTableRecords([$outro])
            ->assertCanNotSeeTableRecords([$aberto]);
    }

    public function test_so_quem_tem_permissao_abre_os_casos(): void
    {
        $professor = $this->usuario('professor');
        $caso = $this->caso($this->ana);

        $this->actingAs($this->como($professor))->get(CasoFrequenciaResource::getUrl('index'))->assertForbidden();
        $this->actingAs($this->como($professor))->get(CasoFrequenciaResource::getUrl('view', ['record' => $caso]))->assertForbidden();
        $this->actingAs($this->como($this->secretaria))->get(CasoFrequenciaResource::getUrl('view', ['record' => $caso]))->assertOk();
    }

    public function test_cadastrar_conselho_tutelar(): void
    {
        Livewire::actingAs($this->como($this->secretaria))
            ->test(CreateConselhoTutelar::class)
            ->fillForm(['nome' => 'Conselho da Região Central', 'municipio' => 'Belo Horizonte', 'uf' => 'mg', 'email' => 'conselho@teste.gov.br', 'is_ativo' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $conselho = ConselhoTutelar::query()->firstOrFail();

        $this->assertSame('MG', $conselho->uf, 'a UF vai em maiúsculas');
        $this->assertSame($conselho->id, ConselhoTutelar::paraUnidade($this->unidade->id)?->id);
    }

    // --- Mesa de pendências ------------------------------------------------------------------------------------------

    public function test_a_fila_de_alunos_acima_do_limite_so_conta_quem_nao_tem_caso(): void
    {
        $fila = new FrequenciaAcimaDoLimite;

        $this->assertSame(2, $fila->contar($this->coordenador));
        $this->assertSame(2, $fila->atrasados($this->coordenador));
        $this->assertSame(['Caio Dias', 'Ana Lima'], array_map(fn ($i): string => $i->titulo, $fila->itens($this->coordenador, 5)));

        $this->caso($this->ana);
        app(MesaDePendencias::class)->esquecer($this->coordenador);

        $this->assertSame(1, (new FrequenciaAcimaDoLimite)->contar($this->coordenador), 'depois de "Atualizar", a Ana (com caso) sai');
    }

    public function test_casos_parados_por_tipo(): void
    {
        $semContato = $this->caso($this->ana);
        CasoFrequencia::query()->whereKey($semContato->id)->update(['created_at' => now()->subDays(10)]);

        $notificado = $this->caso($this->caio);
        $this->contato($notificado);
        $this->servico()->marcarNotificado($notificado, now()->subDays(40), 'PROT-2', null, $this->secretaria);

        $fila = new CasosDeFrequenciaParados;

        $this->assertSame(2, $fila->contar($this->coordenador));
        $detalhes = collect($fila->itens($this->coordenador, 5))->pluck('detalhe')->implode(' | ');
        $this->assertStringContainsString('sem contato com a família', $detalhes);
        $this->assertStringContainsString('sem resposta', $detalhes);

        // Registrar o contato tira o primeiro? Não: o caso só sai da fila ao sair de "aberto" (contato muda para em contato).
        $this->contato($semContato);
        $this->assertSame(1, $fila->contar($this->coordenador), 'com contato registrado, o caso sai da fila de sem contato');

        $this->servico()->registrarResposta($notificado, 'Visita feita', now(), $this->secretaria);
        $this->assertSame(0, $fila->contar($this->coordenador));
    }

    public function test_as_filas_novas_aparecem_na_mesa_so_para_quem_tem_a_permissao(): void
    {
        $mesa = app(MesaDePendencias::class);
        $this->como($this->coordenador);
        $chaves = array_map(fn ($f): string => $f->chave(), $mesa->fontesDe($this->coordenador));

        $this->assertContains('frequencia_acima_do_limite', $chaves);
        $this->assertContains('casos_de_frequencia_parados', $chaves);
        $this->assertContains('carga_letiva_em_risco', $chaves);

        $this->como($this->secretaria);
        $chavesSecretaria = array_map(fn ($f): string => $f->chave(), $mesa->fontesDe($this->secretaria));

        $this->assertNotContains('frequencia_acima_do_limite', $chavesSecretaria);
        $this->assertNotContains('casos_de_frequencia_parados', $chavesSecretaria);
    }

    public function test_a_fila_de_carga_letiva_em_risco_lista_a_turma_com_o_calendario_insuficiente(): void
    {
        $fila = new \App\Services\Pendencias\Fontes\CargaLetivaEmRisco;

        // 40 aulas de 50 minutos em 40 dias: 33,3 h e 40 dias contra 800 h e 200 dias.
        $this->assertSame(1, $fila->contar($this->coordenador));
        $this->assertSame(1, $fila->atrasados($this->coordenador));
        $this->assertStringContainsString('Calendário insuficiente', $fila->itens($this->coordenador, 5)[0]->detalhe);
        $this->assertSame(app(CargaLetivaService::class)->apurar($this->turma)['situacao']->value, 'insuficiente');
    }

    // --- Permissões --------------------------------------------------------------------------------------------------

    public function test_as_habilidades_do_caso_seguem_a_permissao_e_o_estado(): void
    {
        $caso = $this->caso($this->ana);

        $this->como($this->coordenador);
        $this->assertTrue($this->coordenador->can('acompanhar', $caso));
        $this->assertFalse($this->coordenador->can('update', $caso), 'sem edição livre');
        $this->assertFalse($this->coordenador->can('delete', $caso));

        $this->como($this->secretaria);
        $this->assertFalse($this->secretaria->can('acompanhar', $caso));
        $this->assertTrue($this->secretaria->can('enviar', $caso));

        $this->servico()->encerrar($caso, 'Transferido', $this->coordenador);
        $this->como($this->coordenador);
        $this->assertFalse($this->coordenador->can('acompanhar', $caso->refresh()), 'encerrado');
    }
}
