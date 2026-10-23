<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\FormatoExportacaoContabil;
use App\Enums\RegimeContabil;
use App\Filament\Concerns\HasAjudaAction;
use App\Models\LoteContabil;
use App\Models\ParametroContabil;
use App\Models\PlanoConta;
use App\Services\Contabil\ExportacaoContabilService;
use App\Support\Contabil\LancamentoContabil;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Exportação para a contabilidade (Onda 73): confere o movimento do período, aponta o que falta classificar e entrega o
 * arquivo de lançamentos ao escritório. Ver é da administração; gerar o arquivo e mexer nos parâmetros pede a permissão
 * `Exportar`, porque o arquivo leva todo o movimento financeiro.
 */
class ExportacaoContabil extends Page
{
    use HasAjudaAction;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static UnitEnum|string|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 24;

    protected static ?string $title = 'Exportação para a Contabilidade';

    protected static ?string $navigationLabel = 'Contabilidade';

    protected static ?string $slug = 'financeiro/exportacao-contabil';

    protected string $view = 'filament.pages.exportacao-contabil';

    public string $dataInicio = '';

    public string $dataFim = '';

    public string $regime = 'caixa';

    public string $formato = 'csv_lancamentos';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:ExportacaoContabil');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        // O mês fechado mais recente: é o que o escritório costuma pedir.
        $anterior = today()->subMonthNoOverflow();
        $this->dataInicio = $anterior->startOfMonth()->toDateString();
        $this->dataFim = $anterior->endOfMonth()->toDateString();
    }

    public function podeExportar(): bool
    {
        return (bool) auth()->user()?->can('Exportar:ExportacaoContabil');
    }

    public function getRegime(): RegimeContabil
    {
        return RegimeContabil::tryFrom($this->regime) ?? RegimeContabil::Caixa;
    }

    public function getFormato(): FormatoExportacaoContabil
    {
        return FormatoExportacaoContabil::tryFrom($this->formato) ?? FormatoExportacaoContabil::CsvLancamentos;
    }

    private function data(string $valor): ?Carbon
    {
        try {
            return $valor === '' ? null : Carbon::parse($valor)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * A conferência do período escolhido, ou o motivo de não haver (datas inválidas).
     *
     * @return array{erro: ?string, lancamentos?: list<LancamentoContabil>, pendencias?: array<int, array<string, mixed>>, avisos?: array<int, array<string, mixed>>, resumo?: array<string, mixed>, sobrepostos?: list<LoteContabil>}
     */
    public function getConferencia(): array
    {
        abort_unless(static::canAccess(), 403);

        $inicio = $this->data($this->dataInicio);
        $fim = $this->data($this->dataFim);

        if (! $inicio || ! $fim) {
            return ['erro' => 'Informe o período.'];
        }

        if ($fim->lt($inicio)) {
            return ['erro' => 'A data final não pode ser anterior à inicial.'];
        }

        if ($inicio->diffInDays($fim) > 366) {
            return ['erro' => 'O período passa de um ano. Escolha um período menor.'];
        }

        $servico = app(ExportacaoContabilService::class);

        return ['erro' => null] + $servico->apurar($inicio, $fim, $this->getRegime()) + [
            'sobrepostos' => $servico->lotesQueSeSobrepoem($inicio, $fim, $this->getRegime()),
        ];
    }

    /**
     * @return Collection<int, LoteContabil>
     */
    public function getLotes()
    {
        abort_unless(static::canAccess(), 403);

        return LoteContabil::query()->with('geradoPor')->orderByDesc('id')->limit(15)->get();
    }

    public function exportarAction(): Action
    {
        return Action::make('exportar')
            ->label('Gerar arquivo')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('primary')
            ->visible(fn (): bool => $this->podeExportar())
            ->requiresConfirmation()
            ->modalHeading('Gerar o arquivo da contabilidade')
            ->modalDescription('O arquivo fica guardado no histórico e pode ser baixado de novo sem refazer a conta.')
            ->action(function (): void {
                abort_unless(static::canAccess() && $this->podeExportar(), 403);

                $inicio = $this->data($this->dataInicio);
                $fim = $this->data($this->dataFim);

                if (! $inicio || ! $fim) {
                    Notification::make()->title('Informe o período')->danger()->send();

                    return;
                }

                try {
                    $lote = app(ExportacaoContabilService::class)->exportar($inicio, $fim, $this->getRegime(), $this->getFormato(), auth()->user());
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível gerar o arquivo')->body($e->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()
                    ->title('Arquivo gerado')
                    ->body("{$lote->lancamentos} lançamento(s), total de R$ ".number_format((float) $lote->total, 2, ',', '.').'.')
                    ->success()
                    ->actions([
                        Action::make('baixar')->label('Baixar')->url(route('financeiro.exportacao-contabil.download', $lote))->button(),
                    ])
                    ->send();
            });
    }

    public function parametrosAction(): Action
    {
        return Action::make('parametros')
            ->label('Parâmetros contábeis')
            ->icon('heroicon-o-cog-6-tooth')
            ->color('gray')
            ->visible(fn (): bool => $this->podeExportar())
            ->modalHeading('Parâmetros contábeis')
            ->modalDescription('Contas padrão da exportação. Use os códigos do plano de contas do escritório.')
            ->fillForm(function (): array {
                $p = ParametroContabil::atual();

                return [
                    'conta_clientes' => $p->conta_clientes,
                    'conta_fornecedores' => $p->conta_fornecedores,
                    'plano_conta_receita_id' => $p->plano_conta_receita_id,
                    'plano_conta_despesa_id' => $p->plano_conta_despesa_id,
                ];
            })
            ->schema([
                TextInput::make('conta_clientes')->label('Conta de clientes (a receber)')->helperText('Usada no regime de competência: fatura emitida e recebimento de fatura.')->maxLength(30),
                TextInput::make('conta_fornecedores')->label('Conta de fornecedores (a pagar)')->helperText('Usada no regime de competência: conta a pagar e o seu pagamento.')->maxLength(30),
                Select::make('plano_conta_receita_id')
                    ->label('Conta de receita padrão')
                    ->helperText('Vale para recebimento e fatura sem conta do plano. A conta precisa ter o código do escritório.')
                    ->options(fn (): array => $this->opcoesDoPlano('receita'))
                    ->searchable()
                    ->native(false),
                Select::make('plano_conta_despesa_id')
                    ->label('Conta de despesa padrão')
                    ->helperText('Vale para pagamento sem conta do plano. A conta precisa ter o código do escritório.')
                    ->options(fn (): array => $this->opcoesDoPlano('despesa'))
                    ->searchable()
                    ->native(false),
            ])
            ->action(function (array $data): void {
                abort_unless(static::canAccess() && $this->podeExportar(), 403);

                $atual = ParametroContabil::query()->latest('id')->first() ?? new ParametroContabil;
                $atual->fill([
                    'conta_clientes' => filled($data['conta_clientes'] ?? null) ? trim((string) $data['conta_clientes']) : null,
                    'conta_fornecedores' => filled($data['conta_fornecedores'] ?? null) ? trim((string) $data['conta_fornecedores']) : null,
                    'plano_conta_receita_id' => filled($data['plano_conta_receita_id'] ?? null) ? (int) $data['plano_conta_receita_id'] : null,
                    'plano_conta_despesa_id' => filled($data['plano_conta_despesa_id'] ?? null) ? (int) $data['plano_conta_despesa_id'] : null,
                    'atualizado_por_user_id' => auth()->id(),
                ])->save();

                Notification::make()->title('Parâmetros salvos')->success()->send();
            });
    }

    /**
     * @return array<int, string>
     */
    private function opcoesDoPlano(string $tipo): array
    {
        return PlanoConta::query()
            ->where('tipo', $tipo)
            ->where('is_active', true)
            ->orderBy('codigo')
            ->get()
            ->mapWithKeys(fn (PlanoConta $plano): array => [$plano->id => "{$plano->codigo} - {$plano->nome}"])
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->parametrosAction(),
            $this->exportarAction(),
            $this->ajudaAction('Contabilidade', HelpContent::make('🧮', 'Exportação para a contabilidade', 'Entregue ao escritório o movimento do mês em partidas dobradas, no plano de contas dele.')
                ->passos('🔁 Como usar', [
                    'Em Plano de Contas, preencha o "Código no escritório" de cada conta; em Bancos, a "Conta no escritório" de cada banco.',
                    'Em Parâmetros contábeis, defina as contas de clientes e de fornecedores e as contas de receita e despesa padrão para o que ainda não foi classificado.',
                    'Escolha o período e o regime que o escritório pediu. A conferência mostra quantos lançamentos saem e o que ainda falta classificar.',
                    'Sem pendências, use "Gerar arquivo". O arquivo fica no histórico, com a impressão digital, e pode ser baixado de novo.',
                ])
                ->secao('📌 Os dois regimes', [
                    ['💵', 'Caixa', 'Um lançamento por movimento do banco: o recebimento contra a conta de receita e o pagamento contra a conta de despesa.'],
                    ['🧾', 'Competência', 'A fatura do mês contra clientes, a conta a pagar do mês contra fornecedores, e o banco dando baixa nos dois. O mês da fatura e da conta a pagar é o do vencimento.'],
                ])
                ->alerta('O arquivo não leva nome de aluno nem de responsável, só o número da fatura. O escritório decide o regime e confere os lançamentos; o sistema só traduz o que está cadastrado.')),
        ];
    }
}
