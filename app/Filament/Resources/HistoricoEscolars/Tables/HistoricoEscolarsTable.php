<?php

namespace App\Filament\Resources\HistoricoEscolars\Tables;

use App\Models\HistoricoEscolar;
use App\Services\HistoricoEscolarService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class HistoricoEscolarsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pessoa.nome')
                    ->label('Estudante')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (HistoricoEscolar $record) => $record->pessoa?->cpf ? 'CPF: '.$record->pessoa->cpf : null),

                TextColumn::make('curso.nome_interno')
                    ->label('Curso / Etapa')
                    ->badge()
                    ->color('info')
                    ->placeholder('Educação Básica')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('situacao')
                    ->label('Situação')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'concluido' => 'Concluído',
                        'transferido' => 'Transferido',
                        default => 'Em Curso',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'concluido' => 'success',
                        'transferido' => 'info',
                        default => 'warning',
                    })
                    ->sortable(),

                TextColumn::make('codigo_autenticidade')
                    ->label('Código QR')
                    ->fontFamily('mono')
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->copyMessage('Código de autenticidade copiado!'),

                TextColumn::make('anos_count')
                    ->label('Anos Cursados')
                    ->counts('anos')
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),

                TextColumn::make('data_emissao')
                    ->label('Expedição')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('data_conclusao')
                    ->label('Conclusão')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('curso_id')
                    ->label('Curso / Segmento')
                    ->relationship('curso', 'nome_interno')
                    ->preload(),

                SelectFilter::make('situacao')
                    ->label('Situação no Ciclo')
                    ->options([
                        'em_curso' => 'Em Curso',
                        'concluido' => 'Concluído',
                        'transferido' => 'Transferido',
                    ]),
            ])
            ->recordActions([
                Action::make('imprimir_pdf')
                    ->label('Emitir PDF Oficial')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->url(fn (HistoricoEscolar $record) => route('historicos-escolares.pdf', $record))
                    ->openUrlInNewTab(),

                Action::make('sincronizar_dados')
                    ->label('Sincronizar do Torre360')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Sincronizar Matrículas Internas')
                    ->modalDescription('O sistema irá importar ou atualizar automaticamente todas as matrículas, disciplinas, cargas horárias e notas do aluno cursadas no Torre360 para este Histórico Escolar.')
                    ->modalSubmitActionLabel('Sincronizar Agora')
                    ->action(function (HistoricoEscolar $record, HistoricoEscolarService $service) {
                        $resultado = $service->sincronizarMatriculasInternas($record);

                        Notification::make()
                            ->success()
                            ->title('Histórico sincronizado com sucesso!')
                            ->body("Foram processados {$resultado['anos_sincronizados']} anos/séries e {$resultado['disciplinas_sincronizadas']} componentes curriculares a partir das matrículas do aluno.")
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
