<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Jobs\GerarInformeAnualLotePdfJob;
use App\Models\Turma;
use App\Services\InformeAnualMensalidadesService;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Informe anual de mensalidades pagas para a secretaria e a administração: o que cada família pagou no ano-calendário,
 * com PDF individual e em lote (por turma).
 */
class InformeAnualMensalidades extends Page
{
    use HasAjudaAction;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static UnitEnum|string|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 41;

    protected static ?string $title = 'Informe Anual de Mensalidades';

    protected static ?string $navigationLabel = 'Informe Anual de Mensalidades';

    protected static ?string $slug = 'financeiro/informe-anual';

    protected string $view = 'filament.pages.informe-anual-mensalidades';

    public int $ano;

    public ?int $turmaId = null;

    public string $busca = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('GerarInformeAnual:Fatura');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        // Em janeiro e fevereiro, o informe procurado costuma ser o do ano anterior.
        $this->ano = (int) (today()->month <= 2 ? today()->subYear()->format('Y') : today()->format('Y'));
    }

    /**
     * @return array<int, string>
     */
    public function getAnos(): array
    {
        $atual = (int) today()->format('Y');

        return collect(range($atual, $atual - 5))->mapWithKeys(fn (int $a): array => [$a => (string) $a])->all();
    }

    /**
     * @return array<int, string>
     */
    public function getTurmas(): array
    {
        return Turma::query()->orderBy('nome')->pluck('nome', 'id')->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getLinhas(): Collection
    {
        abort_unless(static::canAccess(), 403);

        return app(InformeAnualMensalidadesService::class)->gerar($this->anoValido(), null, $this->turmaId ?: null, $this->busca !== '' ? $this->busca : null);
    }

    public function anoValido(): int
    {
        return array_key_exists($this->ano, $this->getAnos()) ? $this->ano : (int) today()->format('Y');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('loteAsync')
                ->label('Gerar PDF em Lote (Fila)')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Gerar Relatório Consolidado em Fila')
                ->modalDescription('O relatório em lote é gerado de forma assíncrona pela fila do sistema para garantir velocidade e prevenir travamentos. Você receberá uma notificação com o link de download seguro e assinado assim que o PDF estiver pronto.')
                ->modalSubmitActionLabel('Iniciar Processamento em Fila')
                ->action(function () {
                    $ano = $this->anoValido();
                    $turmaId = $this->turmaId;
                    $busca = $this->busca !== '' ? $this->busca : null;

                    GerarInformeAnualLotePdfJob::dispatch($ano, $turmaId, $busca, (int) auth()->id());

                    Notification::make()
                        ->title('Geração iniciada em segundo plano')
                        ->body('O relatório foi enfileirado para processamento. Você será notificado com o link para baixar o PDF assim que for concluído.')
                        ->info()
                        ->send();
                }),
            Action::make('lote')
                ->label('Download Direto')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn (): string => route('informe-anual.lote', array_filter(['ano' => $this->anoValido(), 'turma' => $this->turmaId, 'busca' => $this->busca ?: null])))
                ->openUrlInNewTab(),
            $this->ajudaAction('Informe Anual de Mensalidades', HelpContent::make('🧾', 'Informe Anual de Mensalidades', 'O que cada família pagou à escola no ano, para a declaração do imposto de renda.')
                ->secao('📖 Como funciona', [
                    ['📅', 'Ano-calendário', 'Conta os pagamentos RECEBIDOS de 1º de janeiro a 31 de dezembro (data do recebimento), não o vencimento.'],
                    ['👨‍👩‍👧', 'Responsáveis', 'O valor de cada responsável financeiro segue a participação cadastrada no contrato.'],
                    ['🧮', 'Parte de instrução', 'Estimativa que exclui itens cuja descrição indica material, uniforme, alimentação, transporte ou passeios.'],
                    ['⚡', 'Geração em Fila', 'Use o botão "Gerar PDF em Lote (Fila)" para processar lotes grandes em segundo plano sem travar a navegação. Você receberá um aviso com link seguro assim que o PDF for gerado.'],
                ])
                ->alerta('O informe é um documento de apoio: não substitui o recibo nem a nota fiscal. Confira as regras de dedução e o limite anual com a contabilidade.')
                ->dica('As famílias também baixam o próprio informe no Portal, em Meus Dados → Informe Anual.')),
        ];
    }
}
