<?php

namespace App\Filament\Resources\PlanilhaLeiMensalidades\Tables;

use App\Enums\StatusPlanilhaLei;
use App\Models\PlanilhaLeiMensalidade;
use App\Services\PlanilhaLeiMensalidadeService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class PlanilhaLeiMensalidadesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->label('Título / Descrição')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (PlanilhaLeiMensalidade $record) => "Exercício Base {$record->ano_base} ➔ Projetado {$record->ano_letivo_destino}"),

                TextColumn::make('unidade.nome')
                    ->label('Unidade')
                    ->placeholder('Todas as Unidades')
                    ->sortable(),

                TextColumn::make('mensalidade_media_base')
                    ->label('Base Atual')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('variacao_custo_total_percentual')
                    ->label('Variação Lei (%)')
                    ->suffix('%')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('percentual_reajuste_adotado')
                    ->label('Reajuste Adotado')
                    ->suffix('%')
                    ->badge()
                    ->color(fn (PlanilhaLeiMensalidade $record) => (float) $record->percentual_reajuste_adotado <= (float) $record->variacao_custo_total_percentual ? 'success' : 'warning')
                    ->sortable(),

                TextColumn::make('mensalidade_projetada')
                    ->label('Nova Mensalidade')
                    ->money('BRL')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('data_afixacao')
                    ->label('Data de Afixação')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(StatusPlanilhaLei::class),
                SelectFilter::make('ano_letivo_destino')
                    ->label('Ano Letivo Projetado')
                    ->options(fn () => PlanilhaLeiMensalidade::distinct()->pluck('ano_letivo_destino', 'ano_letivo_destino')),
            ])
            ->recordActions([
                Action::make('espelhoOficial')
                    ->label('Espelho Oficial')
                    ->icon('heroicon-o-document-text')
                    ->color('primary')
                    ->modalHeading('Demonstrativo Oficial de Custos (Lei 9.870/99)')
                    ->modalDescription('Documento para afixação prévia de 45 dias e atendimento ao PROCON.')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(fn (PlanilhaLeiMensalidade $record) => new HtmlString(
                        app(PlanilhaLeiMensalidadeService::class)->gerarEspelhoOficialHtml($record)
                    )),

                Action::make('homologar')
                    ->label('Homologar')
                    ->icon('heroicon-o-check-badge')
                    ->color('info')
                    ->visible(fn (PlanilhaLeiMensalidade $record) => in_array($record->status, [StatusPlanilhaLei::Rascunho, StatusPlanilhaLei::EmAnalise], true) && auth()->user()?->can('Homologar:PlanilhaLeiMensalidade'))
                    ->requiresConfirmation()
                    ->modalHeading('Homologar Planilha de Custos')
                    ->modalDescription('Confirma a homologação da planilha e o percentual de reajuste oficial para o próximo ano letivo?')
                    ->action(function (PlanilhaLeiMensalidade $record) {
                        $record->update([
                            'status' => StatusPlanilhaLei::Homologada,
                            'homologado_por_user_id' => auth()->id(),
                            'homologado_em' => now(),
                        ]);

                        Notification::make()
                            ->title('Planilha Homologada')
                            ->body("Planilha {$record->titulo} homologada com sucesso pela diretoria.")
                            ->success()
                            ->send();
                    }),

                Action::make('publicar')
                    ->label('Publicar')
                    ->icon('heroicon-o-megaphone')
                    ->color('success')
                    ->visible(fn (PlanilhaLeiMensalidade $record) => $record->status === StatusPlanilhaLei::Homologada)
                    ->requiresConfirmation()
                    ->modalHeading('Publicar e Afixar Planilha Oficial')
                    ->modalDescription('Esta ação oficializa a afixação legal da planilha de custos para cumprimento do prazo da Lei 9.870/99.')
                    ->action(function (PlanilhaLeiMensalidade $record) {
                        $record->update([
                            'status' => StatusPlanilhaLei::Publicada,
                            'data_afixacao' => now(),
                        ]);

                        Notification::make()
                            ->title('Planilha Publicada Oficialmente')
                            ->body('A planilha agora tem eficácia legal para a rematrícula do próximo ano letivo.')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
