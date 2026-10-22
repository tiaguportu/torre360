<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\SituacaoAlunoCenso;
use App\Exceptions\CensoEscolarException;
use App\Filament\Concerns\HasAjudaAction;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Unidade;
use App\Services\Educacenso\EducacensoRetornoImporter;
use App\Services\Educacenso\SituacaoAlunoCensoExporter;
use App\Services\Educacenso\SituacaoAlunoCensoService;
use App\Support\HelpContent;
use App\Support\TiposArquivo;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

/**
 * Censo Escolar, 2ª etapa (Situação do Aluno): mostra a situação apurada de cada aluno da escola, o que ainda impede o
 * envio, e gera os arquivos de importação do Educacenso. Também importa o que o Educacenso devolve (códigos do Inep).
 */
class CensoEscolar extends Page
{
    use HasAjudaAction;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static UnitEnum|string|null $navigationGroup = 'Secretaria';

    protected static ?int $navigationSort = 60;

    protected static ?string $title = 'Censo Escolar: Situação do Aluno';

    protected static ?string $navigationLabel = 'Censo Escolar (2ª etapa)';

    protected static ?string $slug = 'secretaria/censo-escolar';

    protected string $view = 'filament.pages.censo-escolar';

    public ?int $unidadeId = null;

    public ?int $periodoId = null;

    /** "todas", "pendencias" ou "prontas". */
    public string $filtro = 'todas';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('GerenciarCensoEscolar:Matricula');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->unidadeId = Unidade::query()->orderBy('nome')->value('id');
        $this->periodoId = PeriodoLetivo::query()->whereDate('data_inicio', '<=', today())->orderByDesc('data_inicio')->value('id')
            ?? PeriodoLetivo::query()->orderByDesc('data_inicio')->value('id');
    }

    /**
     * @return array<int, string>
     */
    public function getUnidades(): array
    {
        return Unidade::query()->orderBy('nome')->pluck('nome', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public function getPeriodos(): array
    {
        return PeriodoLetivo::query()->orderByDesc('data_inicio')->pluck('nome', 'id')->all();
    }

    private function unidade(): ?Unidade
    {
        return $this->unidadeId ? Unidade::query()->with('instituicaoEnsino')->find($this->unidadeId) : null;
    }

    private function periodo(): ?PeriodoLetivo
    {
        return $this->periodoId ? PeriodoLetivo::query()->find($this->periodoId) : null;
    }

    private function servico(): SituacaoAlunoCensoService
    {
        return app(SituacaoAlunoCensoService::class);
    }

    /**
     * @return array{inep: string, gestor_cpf: string, gestor_nome: string, pendencias: list<string>}|null
     */
    public function getEscola(): ?array
    {
        abort_unless(static::canAccess(), 403);

        $unidade = $this->unidade();

        return $unidade ? $this->servico()->escola($unidade) : null;
    }

    public function getDataReferencia(): ?string
    {
        $periodo = $this->periodo();

        return $periodo ? $this->servico()->dataReferencia($this->servico()->anoDoCenso($periodo))->format('d/m/Y') : null;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function todasAsLinhas(): Collection
    {
        abort_unless(static::canAccess(), 403);

        $unidade = $this->unidade();
        $periodo = $this->periodo();

        return $unidade && $periodo ? $this->servico()->apurar($unidade, $periodo) : collect();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getLinhas(): Collection
    {
        $linhas = $this->todasAsLinhas();

        return match ($this->filtro) {
            'pendencias' => $linhas->filter(fn (array $l): bool => $l['pendencias'] !== [])->values(),
            'prontas' => $linhas->filter(fn (array $l): bool => $l['pendencias'] === [])->values(),
            default => $linhas,
        };
    }

    /**
     * @return array{total: int, prontas: int, registro_90: int, registro_91: int, com_pendencia: int, sem_id_inep: int, sem_codigo_matricula: int, ajustes: int}
     */
    public function getResumo(): array
    {
        return $this->servico()->resumo($this->todasAsLinhas());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importarRetorno')
                ->label('Importar retorno da matrícula inicial')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Importar o arquivo exportado do Educacenso')
                ->modalDescription('Use o arquivo .txt da exportação da matrícula inicial (descompacte o .zip antes). O sistema grava o código Inep das turmas, o ID Inep dos alunos e o código das matrículas. Nada é sobrescrito: o que for diferente do já cadastrado aparece como divergência.')
                ->modalSubmitActionLabel('Importar')
                ->schema([$this->campoArquivo()])
                ->action(fn (array $data) => $this->importar($data, 'matricula_inicial')),
            Action::make('importarIdentificacao')
                ->label('Importar identificação (RESULTADO_CERTO)')
                ->icon('heroicon-o-identification')
                ->color('gray')
                ->modalHeading('Importar o resultado da identificação de alunos')
                ->modalDescription('Use o arquivo RESULTADO_CERTO devolvido pelo Educacenso. Os "prováveis" devem ser conferidos um a um e informados na linha do aluno, em "Códigos do Inep".')
                ->modalSubmitActionLabel('Importar')
                ->schema([$this->campoArquivo()])
                ->action(fn (array $data) => $this->importar($data, 'identificacao')),
            Action::make('baixarIdentificacao')
                ->label('Baixar arquivo de identificação')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => $this->baixar('identificacao')),
            Action::make('baixarSituacao')
                ->label('Baixar arquivo da Situação do Aluno')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->action(fn () => $this->baixar('situacao')),
            $this->ajudaAction('Censo Escolar: Situação do Aluno', HelpContent::make('🏫', 'Censo Escolar: Situação do Aluno', 'A 2ª etapa do Censo informa o que aconteceu com cada aluno no ano: aprovado, reprovado, transferido ou deixou de frequentar.')
                ->passos('📖 Passo a passo', [
                    'Na matrícula inicial (1ª etapa), exporte os dados da escola. O Educacenso devolve um arquivo com os códigos do Inep.',
                    'Importe esse arquivo aqui, em "Importar retorno da matrícula inicial". O sistema grava o código das turmas, o ID Inep dos alunos e o código das matrículas.',
                    'Para quem não tem ID Inep (entrou depois da matrícula inicial), baixe o arquivo de identificação, envie ao Educacenso e importe o RESULTADO_CERTO.',
                    'Confira a lista: o sistema calcula a situação pelo resultado final, pelas transferências e pela matrícula. Ajuste os casos que o sistema não sabe (por exemplo, falecimento).',
                    'Baixe o arquivo da Situação do Aluno e importe no Educacenso (Migração > Importação).',
                ])
                ->alerta('A importação no Educacenso sobrepõe tudo o que a escola já informou (inclusive à mão) e não dá para enviar o arquivo em partes: gere-o quando a lista estiver sem pendências. Se alguma matrícula ficar de fora, o Inep aceita o arquivo como "importado parcialmente" e esses alunos devem ser informados direto nos formulários do Educacenso, sem importar de novo.')
                ->dica('A data de referência do Censo é a última quarta-feira de maio. Quem entrou depois dela vai no registro 91 (admitido após).')),
        ];
    }

    private function campoArquivo(): FileUpload
    {
        return FileUpload::make('arquivo')
            ->label('Arquivo .txt')
            ->acceptedFileTypes(TiposArquivo::textoPlano())
            ->storeFiles(false)
            ->maxSize(20480)
            ->required();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function importar(array $data, string $tipo): void
    {
        $arquivo = Arr::first(Arr::wrap($data['arquivo'] ?? null));
        $conteudo = $arquivo instanceof TemporaryUploadedFile ? $arquivo->get() : null;

        if (! is_string($conteudo) || $conteudo === '') {
            Notification::make()->title('Arquivo vazio ou ilegível')->danger()->send();

            return;
        }

        try {
            $importador = app(EducacensoRetornoImporter::class);
            $resumo = $tipo === 'identificacao' ? $importador->importarIdentificacao($conteudo) : $importador->importarMatriculaInicial($conteudo);
        } catch (\InvalidArgumentException $e) {
            Notification::make()->title('Arquivo não reconhecido')->body($e->getMessage())->danger()->persistent()->send();

            return;
        }

        $problemas = [...$resumo['divergencias'], ...$resumo['nao_encontrados']];
        $corpo = "Gravados: {$resumo['pessoas']} ID(s) de aluno, {$resumo['turmas']} turma(s) e {$resumo['matriculas']} matrícula(s).";

        if ($resumo['escolas_desconhecidas'] !== []) {
            $corpo .= ' Escolas do arquivo sem cadastro aqui (ignoradas): '.implode(', ', $resumo['escolas_desconhecidas']).'.';
        }

        if ($problemas !== []) {
            $corpo .= ' Atenção ('.count($problemas).'): '.implode(' | ', array_slice($problemas, 0, 5)).(count($problemas) > 5 ? ' | ...' : '');
        }

        $notificacao = Notification::make()->title('Importação concluída')->body($corpo);
        $problemas === [] ? $notificacao->success() : $notificacao->warning()->persistent();
        $notificacao->send();
    }

    private function baixar(string $tipo): mixed
    {
        abort_unless(static::canAccess(), 403);

        $unidade = $this->unidade();
        $periodo = $this->periodo();

        if (! $unidade || ! $periodo) {
            Notification::make()->title('Escolha a unidade e o período letivo')->warning()->send();

            return null;
        }

        try {
            $exportador = app(SituacaoAlunoCensoExporter::class);
            $arquivo = $tipo === 'identificacao' ? $exportador->identificacao($unidade, $periodo) : $exportador->situacao($unidade, $periodo);
        } catch (CensoEscolarException $e) {
            Notification::make()->title('Não foi possível gerar o arquivo')->body($e->getMessage())->danger()->persistent()->send();

            return null;
        }

        if ($arquivo['de_fora'] !== []) {
            Notification::make()
                ->title(count($arquivo['de_fora']).' aluno(s) ficaram de fora do arquivo')
                ->body(collect($arquivo['de_fora'])->take(5)->map(fn (array $d): string => "{$d['aluno']}: {$d['motivo']}")->implode(' | ').(count($arquivo['de_fora']) > 5 ? ' | ...' : ''))
                ->warning()
                ->persistent()
                ->send();
        }

        return response()->streamDownload(fn () => print ($arquivo['conteudo']), $arquivo['arquivo'], ['Content-Type' => 'text/plain; charset=ISO-8859-1']);
    }

    public function ajustarAction(): Action
    {
        return Action::make('ajustar')
            ->label('Ajustar situação')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->size('xs')
            ->modalHeading('Ajustar a situação do aluno no Censo')
            ->modalDescription('O ajuste vale no lugar do cálculo do sistema. Explique o motivo: ele fica registrado.')
            ->fillForm(function (array $arguments): array {
                $ajuste = $this->matriculaDosArgumentos($arguments)?->loadMissing('censoAjuste')->censoAjuste;

                return ['situacao' => $ajuste?->situacao?->value, 'justificativa' => $ajuste?->justificativa];
            })
            ->schema([
                Select::make('situacao')
                    ->label('Situação do aluno')
                    ->options(collect(SituacaoAlunoCenso::cases())->mapWithKeys(fn (SituacaoAlunoCenso $s): array => [$s->value => $s->getLabel()])->all())
                    ->required(),
                Textarea::make('justificativa')
                    ->label('Justificativa')
                    ->helperText('Por exemplo: falecimento comunicado pela família em 12/08; aluno concluiu o curso por aproveitamento.')
                    ->required()
                    ->minLength(10)
                    ->maxLength(1000),
            ])
            ->action(function (array $data, array $arguments): void {
                $matricula = $this->matriculaDosArgumentos($arguments);

                if (! $matricula) {
                    return;
                }

                try {
                    $this->servico()->definirAjuste($matricula, SituacaoAlunoCenso::from((int) $data['situacao']), (string) $data['justificativa'], auth()->user());
                } catch (CensoEscolarException $e) {
                    Notification::make()->title('Ajuste não aceito')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Situação ajustada')->success()->send();
            });
    }

    public function removerAjusteAction(): Action
    {
        return Action::make('removerAjuste')
            ->label('Voltar ao cálculo')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->size('xs')
            ->requiresConfirmation()
            ->modalHeading('Voltar ao cálculo do sistema?')
            ->modalDescription('O ajuste da secretaria é apagado e a situação volta a ser calculada pelo resultado final e pela matrícula.')
            ->action(function (array $arguments): void {
                $matricula = $this->matriculaDosArgumentos($arguments);

                if (! $matricula) {
                    return;
                }

                $this->servico()->removerAjuste($matricula);

                Notification::make()->title('Ajuste removido')->success()->send();
            });
    }

    public function codigosAction(): Action
    {
        return Action::make('codigos')
            ->label('Códigos do Inep')
            ->icon('heroicon-o-hashtag')
            ->color('gray')
            ->size('xs')
            ->modalHeading('Códigos do Inep')
            ->modalDescription('Preencha só o que o Educacenso informou. O código da turma vale para todos os alunos dela.')
            ->fillForm(function (array $arguments): array {
                $matricula = $this->matriculaDosArgumentos($arguments)?->loadMissing(['pessoa', 'turma']);

                return [
                    'id_inep' => $matricula?->pessoa?->codigo_inep,
                    'codigo_turma' => $matricula?->turma?->codigo_inep,
                    'codigo_matricula' => $matricula?->codigo_matricula_inep,
                ];
            })
            ->schema([
                TextInput::make('id_inep')->label('ID Inep do aluno (12 dígitos)')->inputMode('numeric')->regex('/^\d{12}$/')->validationMessages(['regex' => 'O ID Inep tem exatamente 12 dígitos.']),
                TextInput::make('codigo_turma')->label('Código Inep da turma (até 10 dígitos)')->inputMode('numeric')->regex('/^\d{1,10}$/')->validationMessages(['regex' => 'Use de 1 a 10 dígitos.']),
                TextInput::make('codigo_matricula')->label('Código da matrícula (até 12 dígitos)')->inputMode('numeric')->regex('/^\d{1,12}$/')->validationMessages(['regex' => 'Use de 1 a 12 dígitos.']),
            ])
            ->action(function (array $data, array $arguments): void {
                $matricula = $this->matriculaDosArgumentos($arguments);

                if (! $matricula) {
                    return;
                }

                try {
                    $this->servico()->informarCodigos($matricula, $data['id_inep'] ?? null, $data['codigo_turma'] ?? null, $data['codigo_matricula'] ?? null, auth()->user());
                } catch (CensoEscolarException $e) {
                    Notification::make()->title('Códigos não aceitos')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Códigos gravados')->success()->send();
            });
    }

    /**
     * A matrícula só vale se pertencer a uma turma da unidade e do período mostrados na tela: o argumento da ação vem do
     * navegador e não pode apontar para a matrícula de outra escola.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function matriculaDosArgumentos(array $arguments): ?Matricula
    {
        abort_unless(static::canAccess(), 403);

        $unidade = $this->unidade();
        $periodo = $this->periodo();

        if (! $unidade || ! $periodo || ! isset($arguments['matricula'])) {
            return null;
        }

        return $this->servico()->consulta($unidade, $periodo)->whereKey((int) $arguments['matricula'])->first();
    }
}
