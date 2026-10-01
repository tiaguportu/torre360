<?php

namespace App\Filament\Portal\Pages;

use App\Enums\StatusFatura;
use App\Filament\Concerns\HasAjudaAction;
use App\Models\Fatura;
use App\Services\GatewayPagamentoManager;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

class Financeiro extends Page implements HasTable
{
    use HasAjudaAction;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $title = 'Financeiro';

    protected static ?string $slug = 'financeiro';

    protected string $view = 'filament.portal.pages.financeiro';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getFaturasQuery())
            ->columns([
                TextColumn::make('contrato.matricula.pessoa.nome')
                    ->label('Aluno')
                    ->searchable(),
                TextColumn::make('vencimento')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (Fatura $record) => $record->status === StatusFatura::Atrasado ? 'danger' : null),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->money('BRL'),
                TextColumn::make('valor_pago')
                    ->label('Pago')
                    ->money('BRL')
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                TextColumn::make('valor_restante')
                    ->label('Saldo')
                    ->money('BRL')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusFatura::class)
                    ->multiple(),
            ])
            ->recordActions([
                self::pagarAction(),
            ])
            ->defaultSort('vencimento', 'desc')
            ->stackedOnMobile();
    }

    /**
     * Gera (se ainda não existir) e mostra os dados de pagamento da fatura — PIX copia e
     * cola, boleto e link de pagamento/2ª via — para a família pagar sem precisar falar
     * com a secretaria.
     */
    public static function pagarAction(): Action
    {
        return Action::make('pagar')
            ->label('Pagar')
            ->icon('heroicon-o-qr-code')
            ->color('success')
            ->visible(fn (Fatura $record): bool => ! in_array($record->status, [StatusFatura::Pago, StatusFatura::Cancelado]))
            ->modalHeading(fn (Fatura $record): string => "Pagamento — Fatura #{$record->id}")
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->mountUsing(function (Fatura $record, ?Schema $schema): void {
                if (! $record->gateway_id) {
                    $dados = GatewayPagamentoManager::resolver()->criarCobranca($record);
                    $record->update($dados);
                }

                $schema?->fill();
            })
            ->schema([
                Placeholder::make('dados')
                    ->label('')
                    ->content(function (Fatura $record) {
                        $record = $record->fresh();

                        $html = '<div class="space-y-3 text-sm">';
                        $html .= '<p><strong>Saldo a pagar:</strong> R$ '.number_format($record->valor_restante, 2, ',', '.').'</p>';

                        if ($record->pix_copia_e_cola) {
                            $html .= '<div><strong>PIX Copia e Cola:</strong><textarea readonly class="w-full text-xs p-2 mt-1 border rounded font-mono" rows="3" onclick="this.select()">'.e($record->pix_copia_e_cola).'</textarea></div>';
                        }

                        if ($record->boleto_url) {
                            $html .= '<p><a href="'.e($record->boleto_url).'" target="_blank" class="text-primary-600 underline">Baixar Boleto (2ª via)</a></p>';
                        }

                        if ($record->link_pagamento) {
                            $html .= '<p><a href="'.e($record->link_pagamento).'" target="_blank" class="text-primary-600 underline">Abrir Link de Pagamento</a></p>';
                        }

                        $html .= '</div>';

                        return new HtmlString($html);
                    }),
            ]);
    }

    protected function getFaturasQuery(): Builder
    {
        $idsAcessiveis = auth()->user()->pessoasAcessiveis()->pluck('id');

        return Fatura::query()
            ->whereHas('contrato', function (Builder $query) use ($idsAcessiveis) {
                $query->whereHas('matricula', fn (Builder $q) => $q->whereIn('pessoa_id', $idsAcessiveis))
                    ->orWhereHas('responsaveisFinanceiros', fn (Builder $q) => $q->whereIn('pessoa_id', $idsAcessiveis));
            })
            ->with(['itens', 'transacoes', 'contrato.matricula.pessoa']);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Financeiro', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('💰', 'Financeiro', 'Faturas e pagamentos dos seus alunos.')
            ->secao('🎯 O que você encontra aqui?', [
                ['🧾', 'Faturas', 'Veja aluno, vencimento, valor, valor pago e saldo de cada fatura.'],
                ['🚦', 'Situação', 'A coluna Status mostra se a fatura está Pendente, Paga, Atrasada, Paga Parcialmente ou Cancelada.'],
                ['🔎', 'Filtro', 'Filtre as faturas por status.'],
            ])
            ->dica('Dúvidas sobre valores? Abra um chamado na Central de Atendimento.');
    }
}
