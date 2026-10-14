<?php

declare(strict_types=1);

namespace App\Filament\Resources\Avaliacaos\Pages;

use App\Enums\StatusOmrCorrecao;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Avaliacaos\AvaliacaoResource;
use App\Models\Avaliacao;
use App\Models\Matricula;
use App\Models\OmrCorrecao;
use App\Services\Omr\OmrService;
use App\Support\HelpContent;
use App\Support\TiposArquivo;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class CorrigirGabarito extends Page implements HasForms
{
    use HasAjudaAction;
    use InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = AvaliacaoResource::class;

    protected string $view = 'filament.resources.avaliacaos.pages.corrigir-gabarito';

    protected static ?string $title = 'Corrigir Gabaritos por Foto (OMR)';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $correcoes = [];

    /**
     * Folhas cuja nota nova conflita com uma já lançada: `correcao_id => ['atual' => float, 'nova' => float]`.
     *
     * @var array<int, array{atual: float, nova: float}>
     */
    public array $conflitos = [];

    public function authorizeAccess(): void
    {
        $this->authorize('corrigirGabarito', $this->record);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->record->loadMissing(['turma.matriculas.pessoa', 'gabarito.questoes', 'disciplina']);

        if (! $this->record->gabarito) {
            Notification::make()
                ->title('Avaliação sem Gabarito')
                ->body('Você precisa configurar o gabarito oficial da prova antes de corrigir as folhas.')
                ->warning()
                ->send();

            $this->redirect(AvaliacaoResource::getUrl('edit', ['record' => $this->record]));

            return;
        }

        $this->form->fill();
        $this->carregarCorrecoes();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                FileUpload::make('arquivos_fotos')
                    ->label('Tirar Foto da Folha ou Enviar Imagens')
                    ->helperText('Selecione uma ou mais fotos nítidas da folha de respostas. Em celulares, a câmera traseira será acionada automaticamente.')
                    ->acceptedFileTypes(TiposArquivo::imagens())
                    ->multiple()
                    ->maxFiles((int) config('omr.max_arquivos_lote', 45))
                    ->disk('local')
                    ->directory('omr/uploads')
                    ->extraInputAttributes(['capture' => 'environment'])
                    ->required(),
            ]);
    }

    public function carregarCorrecoes(): void
    {
        /** @var Avaliacao $avaliacao */
        $avaliacao = $this->record;

        $lista = OmrCorrecao::where('avaliacao_id', $avaliacao->id)
            ->with(['matricula.pessoa', 'gabaritoFolha'])
            ->orderByDesc('id')
            ->get();

        $questoesOficiais = $avaliacao->gabarito?->questoes()->get()->keyBy('numero') ?? collect();

        $this->correcoes = $lista->map(function (OmrCorrecao $c) use ($questoesOficiais): array {
            $alunoNome = $c->matricula?->pessoa?->nome ?? 'Aluno não identificado';
            $matriculaCodigo = $c->matricula?->numero_matricula ?? ($c->matricula ? 'MAT-'.$c->matricula->id : 'N/A');
            $uuid = $c->gabaritoFolha?->uuid ?? 'N/A';

            $detalhes = [];
            $respostas = (array) ($c->respostas ?? []);

            foreach ($respostas as $num => $resp) {
                $altDetectada = $resp['alternativa'] ?? null;
                $oficial = $questoesOficiais->get((int) $num);
                $correta = $oficial?->alternativa_correta;
                $anulada = (bool) ($oficial?->anulada ?? false);

                $acertou = $altDetectada !== null && $correta !== null && strtoupper((string) $altDetectada) === strtoupper((string) $correta);

                if ($anulada) {
                    $corBorda = 'border-purple-300 bg-purple-50 dark:bg-purple-950/30';
                    $corTexto = 'text-purple-700 dark:text-purple-300';
                    $titulo = "Questão {$num}: Anulada (pontua conforme regra)";
                } elseif ($acertou) {
                    $corBorda = 'border-emerald-300 bg-emerald-50 dark:bg-emerald-950/30';
                    $corTexto = 'text-emerald-700 dark:text-emerald-300';
                    $titulo = "Questão {$num}: Acerto ({$altDetectada})";
                } elseif ($altDetectada === null) {
                    $corBorda = 'border-gray-300 bg-gray-50 dark:bg-gray-800';
                    $corTexto = 'text-gray-500';
                    $titulo = "Questão {$num}: Em branco / Não respondida";
                } else {
                    $corBorda = 'border-rose-300 bg-rose-50 dark:bg-rose-950/30';
                    $corTexto = 'text-rose-700 dark:text-rose-300';
                    $titulo = "Questão {$num}: Erro (Marcou {$altDetectada}, correta era {$correta})";
                }

                $detalhes[$num] = [
                    'letra' => $altDetectada,
                    'cor_borda' => $corBorda,
                    'cor_texto' => $corTexto,
                    'titulo' => $titulo,
                ];
            }

            $statusClasse = match ($c->status) {
                StatusOmrCorrecao::Confirmada => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
                StatusOmrCorrecao::Falha => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
                StatusOmrCorrecao::Descartada => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
                default => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
            };

            return [
                'id' => $c->id,
                'matricula_id' => $c->matricula_id,
                'aluno_nome' => $alunoNome,
                'matricula_codigo' => $matriculaCodigo,
                'uuid' => $uuid,
                'nota_calculada' => (float) ($c->nota_calculada ?? 0.0),
                'acertos' => $c->acertos,
                'total_validas' => $c->total_validas,
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
                'status_classe' => $statusClasse,
                'alertas' => (array) ($c->alertas ?? []),
                'tem_marcacao_a_conferir' => $c->temMarcacaoAConferir(),
                'conflito' => $this->conflitos[$c->id] ?? null,
                'respostas_detalhe' => $detalhes,
            ];
        })->all();
    }

    public function processarFotos(OmrService $omrService): void
    {
        $dados = $this->form->getState();
        $arquivos = (array) ($dados['arquivos_fotos'] ?? []);

        if (empty($arquivos)) {
            Notification::make()
                ->title('Nenhum arquivo enviado')
                ->warning()
                ->send();

            return;
        }

        $totalProcessado = 0;
        $erros = 0;

        foreach ($arquivos as $arquivoRelativo) {
            $caminhoCompleto = Storage::disk('local')->path($arquivoRelativo);

            try {
                $omrService->processarImagem(
                    avaliacao: $this->record,
                    caminhoArquivoOriginal: $caminhoCompleto,
                    userId: auth()->id(),
                );
                $totalProcessado++;
            } catch (\Throwable $e) {
                $erros++;
                report($e);
            } finally {
                // O serviço já guardou uma cópia reencodada (sem EXIF/GPS) no disco privado: o upload original,
                // com metadados e sem prazo de retenção, não deve ficar para trás.
                Storage::disk('local')->delete($arquivoRelativo);
            }
        }

        $this->form->fill(['arquivos_fotos' => []]);
        $this->carregarCorrecoes();

        if ($totalProcessado > 0) {
            Notification::make()
                ->title("{$totalProcessado} folha(s) processada(s) com sucesso!")
                ->success()
                ->send();
        }

        if ($erros > 0) {
            Notification::make()
                ->title("{$erros} folha(s) falharam na leitura.")
                ->danger()
                ->send();
        }
    }

    /**
     * Métodos públicos do Livewire aceitam qualquer id vindo do navegador: a correção só vale se for desta
     * avaliação (a que o usuário está autorizado a corrigir nesta página).
     */
    protected function correcaoDestaAvaliacao(int $correcaoId): ?OmrCorrecao
    {
        $this->authorize('corrigirGabarito', $this->record);

        return OmrCorrecao::where('avaliacao_id', $this->record->id)->find($correcaoId);
    }

    /**
     * Lança a nota de uma folha. Nota já existente e diferente NÃO é sobrescrita sem confirmação explícita:
     * o conflito aparece no cartão da folha e só `$sobrescrever = true` (botão de confirmação) a substitui.
     */
    public function lancarNotaIndividual(int $correcaoId, bool $sobrescrever = false): void
    {
        $correcao = $this->correcaoDestaAvaliacao($correcaoId);
        if (! $correcao) {
            return;
        }

        try {
            $resultado = app(OmrService::class)->lancarNota($correcao, auth()->user(), $sobrescrever);

            if (($resultado['conflito'] ?? false) === true) {
                $this->conflitos[$correcao->id] = [
                    'atual' => (float) $resultado['nota_atual'],
                    'nova' => (float) $resultado['nota_nova'],
                ];
                $this->carregarCorrecoes();

                Notification::make()
                    ->title('Nota já lançada para este aluno')
                    ->body($resultado['mensagem'])
                    ->warning()
                    ->send();

                return;
            }

            unset($this->conflitos[$correcao->id]);
            $this->carregarCorrecoes();

            Notification::make()
                ->title($resultado['mensagem'])
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erro ao lançar nota')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function associarMatricula(int $correcaoId, int|string|null $matriculaId = null): void
    {
        // Voltar ao "-- Selecione o Aluno --" envia texto vazio: não é uma seleção.
        if ($matriculaId === null || $matriculaId === '') {
            return;
        }

        $correcao = $this->correcaoDestaAvaliacao($correcaoId);
        if (! $correcao) {
            return;
        }

        try {
            app(OmrService::class)->associarMatricula($correcao, (int) $matriculaId);
            $this->carregarCorrecoes();

            Notification::make()
                ->title('Aluno associado com sucesso!')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erro ao associar aluno')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getAlunosTurma(): array
    {
        return Matricula::where('turma_id', $this->record->turma_id)
            ->where('situacao', 'ativa')
            ->with('pessoa')
            ->get()
            ->map(fn (Matricula $m) => [
                'id' => $m->id,
                'nome' => ($m->pessoa?->nome ?? 'Aluno').' ('.($m->numero_matricula ?? 'MAT-'.$m->id).')',
            ])
            ->all();
    }

    /**
     * Lança em lote só o que está pronto: com aluno identificado, sem marcação a conferir e sem conflito com nota
     * já lançada. O restante fica na tela para decisão individual do professor.
     */
    public function lancarTodasConfirmadas(): void
    {
        $this->authorize('corrigirGabarito', $this->record);

        $omrService = app(OmrService::class);

        $aguardando = OmrCorrecao::where('avaliacao_id', $this->record->id)
            ->where('status', StatusOmrCorrecao::AguardandoConferencia)
            ->get();

        $semMatricula = $aguardando->whereNull('matricula_id')->count();
        $aConferir = 0;
        $conflitos = 0;
        $falhas = 0;
        $sucessos = 0;

        foreach ($aguardando->whereNotNull('matricula_id') as $c) {
            if ($c->temMarcacaoAConferir()) {
                $aConferir++;

                continue;
            }

            try {
                $resultado = $omrService->lancarNota($c, auth()->user(), false);

                if (($resultado['conflito'] ?? false) === true) {
                    $conflitos++;
                    $this->conflitos[$c->id] = [
                        'atual' => (float) $resultado['nota_atual'],
                        'nova' => (float) $resultado['nota_nova'],
                    ];

                    continue;
                }

                $sucessos++;
            } catch (\Throwable $e) {
                $falhas++;
                report($e);
            }
        }

        $this->carregarCorrecoes();

        if ($sucessos > 0) {
            Notification::make()
                ->title("{$sucessos} nota(s) lançada(s) com sucesso no diário da turma!")
                ->success()
                ->send();
        }

        $avisos = array_filter([
            $semMatricula > 0 ? "{$semMatricula} folha(s) aguardam a identificação do aluno" : null,
            $aConferir > 0 ? "{$aConferir} folha(s) têm marcação dupla, ambígua ou ilegível e precisam de conferência individual" : null,
            $conflitos > 0 ? "{$conflitos} folha(s) conflitam com uma nota já lançada e pedem confirmação" : null,
            $falhas > 0 ? "{$falhas} folha(s) não puderam ser lançadas" : null,
        ]);

        if ($avisos !== []) {
            Notification::make()
                ->title('Algumas folhas não foram lançadas')
                ->body(implode('; ', $avisos).'.')
                ->warning()
                ->send();
        }
    }

    public function descartarCorrecao(int $correcaoId): void
    {
        $c = $this->correcaoDestaAvaliacao($correcaoId);

        // Folha já lançada no diário não pode ser "descartada": a nota continuaria lá sem correção que a explique.
        if ($c && $c->status !== StatusOmrCorrecao::Confirmada) {
            $c->update(['status' => StatusOmrCorrecao::Descartada]);
            unset($this->conflitos[$c->id]);
            $this->carregarCorrecoes();

            Notification::make()
                ->title('Correção descartada.')
                ->info()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        return [
            Action::make('voltar')
                ->label('Voltar para Avaliação')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(AvaliacaoResource::getUrl('view', ['record' => $this->record])),

            Action::make('lancar_notas_manual')
                ->label('Lançamento Manual de Notas')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->visible(fn () => $user?->can('lancarNotas', $this->record))
                ->url(AvaliacaoResource::getUrl('lancar-notas', ['record' => $this->record])),

            $this->ajudaAction(
                'Correção de Gabarito por Foto (OMR)',
                HelpContent::make('📸', 'Correção Automatizada de Gabaritos', 'Fotografe as folhas de respostas impressas para lançar as notas dos alunos automaticamente.')
                    ->secao('🎯 Como Utilizar', [
                        ['1️⃣', 'Captura', 'Fotografe a folha mantendo os 4 cantos pretos bem visíveis e sem sombras.'],
                        ['2️⃣', 'Leitura Automática', 'O sistema identifica o aluno pelo QR Code, mede o preenchimento de cada bolha e calcula a nota proporcional.'],
                        ['3️⃣', 'Conferência', 'Revise as marcações ambíguas ou duplas destacadas em amarelo antes de confirmar o lançamento.'],
                        ['4️⃣', 'Lançamento Seguro', 'Clique em Lançar Nota para persistir no diário sem risco de apagar notas prévias.'],
                    ])
                    ->dica('Imprima sempre as folhas de resposta em 100% (tamanho real sem ajustar à página) para manter a calibração precisa.')
            ),
        ];
    }
}
