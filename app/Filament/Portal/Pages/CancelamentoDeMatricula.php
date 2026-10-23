<?php

declare(strict_types=1);

namespace App\Filament\Portal\Pages;

use App\Enums\MotivoCancelamentoMatricula;
use App\Enums\OrigemCancelamentoMatricula;
use App\Enums\StatusAssinante;
use App\Enums\StatusDocumentoAssinatura;
use App\Filament\Concerns\HasAjudaAction;
use App\Models\AssinanteDocumento;
use App\Models\CancelamentoMatricula;
use App\Models\DocumentoAssinatura;
use App\Models\Matricula;
use App\Services\CancelamentoMatriculaService;
use App\Services\EncargosAtrasoService;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Portal da Família: pedido de cancelamento de matrícula (Onda 61). Quem pede é o responsável pelo aluno, o responsável
 * financeiro do contrato ou o próprio aluno maior de idade; o aluno menor não vê botão de pedido. A secretaria decide.
 */
class CancelamentoDeMatricula extends Page
{
    use HasAjudaAction;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-minus';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $title = 'Cancelar Matrícula';

    protected static ?string $navigationLabel = 'Cancelar Matrícula';

    protected static ?string $slug = 'cancelamento-de-matricula';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.portal.pages.cancelamento-de-matricula';

    /**
     * Matrículas pelas quais o usuário pode pedir o cancelamento, com o último pedido de cada uma.
     *
     * @return Collection<int, Matricula>
     */
    public function getMatriculas(): Collection
    {
        $usuario = auth()->user();
        $idsAcessiveis = $usuario->pessoasAcessiveis()->pluck('id');
        $idsDoUsuario = $usuario->pessoas()->pluck('pessoa.id');
        $servico = app(CancelamentoMatriculaService::class);

        return Matricula::query()
            ->where(fn (Builder $query) => $query
                ->whereIn('pessoa_id', $idsAcessiveis)
                ->orWhereHas('contrato.responsaveisFinanceiros', fn (Builder $q) => $q->whereIn('pessoa_id', $idsDoUsuario)))
            ->with(['pessoa', 'turma.serie', 'contrato.responsaveisFinanceiros'])
            ->get()
            ->filter(fn (Matricula $matricula): bool => $servico->familiaPodeAgir($matricula, $usuario))
            ->sortBy(fn (Matricula $matricula): string => $matricula->pessoa?->nome ?? '')
            ->values();
    }

    /**
     * O pedido mais recente de cada matrícula (matrícula => pedido).
     *
     * @param  Collection<int, Matricula>  $matriculas
     * @return Collection<int, CancelamentoMatricula>
     */
    public function getPedidos(Collection $matriculas): Collection
    {
        return CancelamentoMatricula::query()
            ->whereIn('matricula_id', $matriculas->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->unique('matricula_id')
            ->keyBy('matricula_id');
    }

    /**
     * O distrato em assinatura eletrônica (Onda 69), se a escola o enviou: quantos já assinaram e, quando o usuário é um
     * dos assinantes e ainda não assinou, o link dele. O link de cada pessoa é só dela: nunca aparece o de outro assinante.
     *
     * @return array{situacao: StatusDocumentoAssinatura, link: ?string, ja_assinou: bool, assinaram: int, total: int}|null
     */
    public function getAssinaturaEletronica(CancelamentoMatricula $pedido): ?array
    {
        $documento = DocumentoAssinatura::query()
            ->where('documentavel_type', CancelamentoMatricula::class)
            ->where('documentavel_id', $pedido->id)
            ->whereIn('status', [StatusDocumentoAssinatura::Enviado->value, StatusDocumentoAssinatura::Assinado->value])
            ->latest('id')
            ->first();

        if (! $documento) {
            return null;
        }

        $email = mb_strtolower(trim((string) auth()->user()?->email));
        $assinantes = $documento->assinantes()->get();
        $meu = $email === '' ? null : $assinantes->first(fn (AssinanteDocumento $assinante): bool => mb_strtolower($assinante->email) === $email);

        return [
            'situacao' => $documento->status,
            'link' => $documento->status === StatusDocumentoAssinatura::Enviado && $meu?->status === StatusAssinante::Pendente ? $meu->link_assinatura : null,
            'ja_assinou' => $meu?->status === StatusAssinante::Assinou,
            'assinaram' => $assinantes->where('status', StatusAssinante::Assinou)->count(),
            'total' => $assinantes->count(),
        ];
    }

    /**
     * Faturas vencidas da matrícula, com multa e juros até hoje, para a família saber o que pesa antes de pedir.
     *
     * @return array{faturas: int, total: float}
     */
    public function getPendencias(Matricula $matricula): array
    {
        if (! $matricula->contrato) {
            return ['faturas' => 0, 'total' => 0.0];
        }

        $encargos = app(EncargosAtrasoService::class);
        $totais = $encargos->totais($encargos->linhasDoContrato($matricula->contrato));

        return ['faturas' => $totais['faturas'], 'total' => $totais['total']];
    }

    public function podePedir(Matricula $matricula, ?CancelamentoMatricula $pedido): bool
    {
        return app(CancelamentoMatriculaService::class)->motivoDeNaoPoderSolicitar($matricula) === null
            && ($pedido === null || ! $pedido->estaAberto());
    }

    public function solicitarAction(): Action
    {
        return Action::make('solicitar')
            ->label('Pedir cancelamento')
            ->icon('heroicon-o-document-minus')
            ->color('danger')
            ->modalHeading('Pedir o cancelamento da matrícula')
            ->modalDescription('A secretaria analisa o pedido, confere a situação financeira e envia o distrato para assinatura. A matrícula só é cancelada depois disso.')
            ->modalSubmitActionLabel('Enviar pedido')
            ->schema(fn (): array => [
                Select::make('motivo')
                    ->label('Motivo')
                    ->options(MotivoCancelamentoMatricula::class)
                    ->required()
                    ->live(),
                TextInput::make('escola_destino')
                    ->label('Escola de destino (se souber)')
                    ->maxLength(160)
                    ->visible(fn (Get $get): bool => $get('motivo') === MotivoCancelamentoMatricula::MudancaEscola->value),
                Textarea::make('motivo_detalhe')
                    ->label('Quer contar mais? (opcional)')
                    ->helperText('Só a escola lê, e fica guardado com segurança.')
                    ->rows(3)
                    ->maxLength(2000),
                DatePicker::make('data_efetiva')
                    ->label('Último dia do aluno na escola')
                    ->helperText('O mês da saída é cobrado inteiro.')
                    ->native(false)
                    ->minDate(today()->addDays((int) config('cancelamento.antecedencia_minima_dias', 0)))
                    ->default(today()->addDays((int) config('cancelamento.antecedencia_minima_dias', 0)))
                    ->required(),
                Checkbox::make('ciente')
                    ->label('Estou ciente de que as mensalidades que vencem até o mês da saída continuam devidas e de que a matrícula só é cancelada depois do distrato.')
                    ->accepted()
                    ->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                $matricula = $this->matriculaDosArgumentos($arguments);

                if (! $matricula) {
                    return;
                }

                try {
                    app(CancelamentoMatriculaService::class)->solicitar($matricula, [
                        'motivo' => $data['motivo'],
                        'motivo_detalhe' => $data['motivo_detalhe'] ?? null,
                        'data_efetiva' => $data['data_efetiva'],
                        'escola_destino' => $data['escola_destino'] ?? null,
                    ], auth()->user(), OrigemCancelamentoMatricula::Familia);
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível enviar o pedido')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title('Pedido enviado')
                    ->body('A secretaria vai analisar e retornar. Acompanhe por aqui.')
                    ->success()
                    ->send();
            });
    }

    public function retirarAction(): Action
    {
        return Action::make('retirar')
            ->label('Retirar o pedido')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Retirar o pedido de cancelamento')
            ->modalDescription('A matrícula continua como está.')
            ->action(function (array $arguments): void {
                $pedido = $this->pedidoDosArgumentos($arguments);

                if (! $pedido) {
                    return;
                }

                try {
                    app(CancelamentoMatriculaService::class)->retirar($pedido, auth()->user());
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível retirar o pedido')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Pedido retirado')->success()->send();
            });
    }

    /**
     * A matrícula vem do cliente: só vale se for uma das que o usuário pode gerir.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function matriculaDosArgumentos(array $arguments): ?Matricula
    {
        $matricula = $this->getMatriculas()->firstWhere('id', (int) ($arguments['matricula'] ?? 0));

        if (! $matricula) {
            Notification::make()->title('Matrícula não encontrada')->danger()->send();
        }

        return $matricula;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function pedidoDosArgumentos(array $arguments): ?CancelamentoMatricula
    {
        $matriculas = $this->getMatriculas();
        $pedido = $this->getPedidos($matriculas)->firstWhere('id', (int) ($arguments['pedido'] ?? 0));

        if (! $pedido) {
            Notification::make()->title('Pedido não encontrado')->danger()->send();
        }

        return $pedido;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Cancelar Matrícula', HelpContent::make('📄', 'Cancelar Matrícula', 'Peça o cancelamento e acompanhe o andamento.')
                ->passos('🔁 Como funciona', [
                    'Clique em "Pedir cancelamento" no aluno, escolha o motivo e o último dia dele na escola.',
                    'A secretaria confere a situação financeira e aprova o pedido.',
                    'Assine o distrato: online, se a escola o enviou por e-mail para assinatura eletrônica (o botão "Assinar o distrato" aparece aqui), ou baixe, assine e entregue na secretaria.',
                    'Com o distrato assinado, a escola conclui o cancelamento e, se for o caso, devolve o que foi pago adiantado.',
                ])
                ->secao('📌 Bom saber', [
                    ['💳', 'Mensalidades', 'As mensalidades que vencem até o fim do mês da saída continuam devidas. O que vence depois e ainda não foi pago é cancelado.'],
                    ['📑', 'Documentos', 'A escola entrega a declaração de transferência e os documentos do aluno mesmo que haja débitos.'],
                    ['↩️', 'Desistiu?', 'Você pode retirar o pedido enquanto a secretaria ainda não o analisou.'],
                ])
                ->alerta('Só o responsável pelo aluno, o responsável financeiro do contrato ou o próprio aluno maior de idade pede o cancelamento.')),
        ];
    }
}
