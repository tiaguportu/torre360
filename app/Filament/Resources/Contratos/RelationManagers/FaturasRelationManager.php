<?php

namespace App\Filament\Resources\Contratos\RelationManagers;

use App\Filament\Resources\Faturas\Schemas\FaturaForm;
use App\Filament\Resources\Faturas\Tables\FaturasTable;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\ItemFatura;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class FaturasRelationManager extends RelationManager
{
    protected static string $relationship = 'faturas';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Schema $schema): Schema
    {
        return FaturaForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return FaturasTable::configure($table)
            ->headerActions([
                Action::make('gerarFaturasAutomaticamente')
                    ->label('Gerar Faturas Automaticamente')
                    ->icon('heroicon-o-cpu-chip')
                    ->form([
                        TextInput::make('quantidade_parcelas')
                            ->label('Quantidade de Parcelas')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(1),
                    ])
                    ->action(function (RelationManager $livewire, array $data) {
                        /** @var Contrato $contrato */
                        $contrato = $livewire->getOwnerRecord();

                        if (! $contrato->data_aceite) {
                            Notification::make()
                                ->title('Erro')
                                ->body('O contrato não possui data de aceite.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if (! $contrato->valor_total) {
                            Notification::make()
                                ->title('Erro')
                                ->body('O contrato não possui valor total definido.')
                                ->danger()
                                ->send();

                            return;
                        }

                        // Buscar a maior data_fim dos períodos letivos associados às matrículas
                        $dataFim = $contrato->matriculas()
                            ->with('periodoLetivo')
                            ->get()
                            ->map(fn ($m) => $m->periodoLetivo?->data_fim)
                            ->filter()
                            ->max();

                        if (! $dataFim) {
                            Notification::make()
                                ->title('Erro')
                                ->body('Não foi possível encontrar a data de fim do período letivo para as matrículas deste contrato.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $dataAceite = Carbon::parse($contrato->data_aceite);
                        $dataFim = Carbon::parse($dataFim);
                        $quantidadeParcelas = (int) $data['quantidade_parcelas'];

                        // O período considerado é data_aceite até data_fim
                        $diffEmDias = $dataAceite->diffInDays($dataFim);

                        if ($diffEmDias <= 0) {
                            Notification::make()
                                ->title('Erro')
                                ->body('O período entre a data de aceite e o fim do período letivo é inválido ou muito curto.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $diasEntreParcelas = $diffEmDias / $quantidadeParcelas;

                        $valorTotal = $contrato->valor_total;
                        $valorAcumulado = 0;

                        for ($i = 1; $i <= $quantidadeParcelas; $i++) {
                            // Cálculo do vencimento: divisão igual de dias
                            $vencimento = $dataAceite->copy()->addDays(round($i * $diasEntreParcelas));

                            $valorDaParcela = 0;
                            if ($i === $quantidadeParcelas) {
                                $valorDaParcela = $valorTotal - $valorAcumulado;
                            } else {
                                $valorDaParcela = round($valorTotal / $quantidadeParcelas, 2);
                                $valorAcumulado += $valorDaParcela;
                            }

                            $fatura = Fatura::create([
                                'contrato_id' => $contrato->id,
                                'vencimento' => $vencimento,
                                'status' => 'pendente',
                            ]);

                            ItemFatura::create([
                                'fatura_id' => $fatura->id,
                                'descricao' => "Parcela {$i}/{$quantidadeParcelas} - Contrato #{$contrato->id}",
                                'valor_unitario' => $valorDaParcela,
                                'quantidade' => 1,
                            ]);
                        }

                        Notification::make()
                            ->title('Sucesso')
                            ->body("{$quantidadeParcelas} faturas foram geradas com sucesso.")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),
            ]);
    }
}
