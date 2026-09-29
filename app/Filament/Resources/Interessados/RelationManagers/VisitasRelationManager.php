<?php

namespace App\Filament\Resources\Interessados\RelationManagers;

use App\Enums\StatusVisitaInteressado;
use App\Models\User;
use App\Models\VisitaInteressado;
use App\Services\LeadScoreService;
use App\Services\VisitaInteressadoService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VisitasRelationManager extends RelationManager
{
    protected static string $relationship = 'visitas';

    protected static ?string $title = 'Visitas à Escola';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('data_hora')
                    ->label('Data e Hora')
                    ->seconds(false)
                    ->native(false)
                    ->displayFormat('d/m/Y H:i')
                    ->required(),
                Select::make('usuario_id')
                    ->label('Consultor')
                    ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                Select::make('interessado_dependente_id')
                    ->label('Aluno')
                    ->options(fn () => $this->getOwnerRecord()->dependentes()->pluck('nome_crianca', 'id'))
                    ->placeholder('Toda a família'),
                Select::make('status')
                    ->label('Situação')
                    ->options(StatusVisitaInteressado::class)
                    ->default(StatusVisitaInteressado::Agendada)
                    ->required(),
                Textarea::make('observacoes')
                    ->label('Observações')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('data_hora')
            ->defaultSort('data_hora', 'desc')
            ->columns([
                TextColumn::make('data_hora')
                    ->label('Data e Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->color(fn (VisitaInteressado $record): ?string => $record->estaAtrasada() ? 'danger' : null),
                TextColumn::make('status')
                    ->label('Situação')
                    ->badge(),
                TextColumn::make('usuario.name')
                    ->label('Consultor')
                    ->placeholder('—')
                    ->icon('heroicon-o-user'),
                TextColumn::make('dependente.nome_crianca')
                    ->label('Aluno')
                    ->placeholder('Toda a família'),
                TextColumn::make('observacoes')
                    ->label('Observações')
                    ->limit(50)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options(StatusVisitaInteressado::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agendar Visita')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['usuario_id'] ??= auth()->id();

                        return $data;
                    })
                    ->after(fn () => $this->sincronizarProximoContato()),
            ])
            ->actions([
                Action::make('realizada')
                    ->label('Realizada')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (VisitaInteressado $record): bool => $record->status === StatusVisitaInteressado::Agendada)
                    ->action(fn (VisitaInteressado $record) => $this->alterarStatus($record, StatusVisitaInteressado::Realizada)),
                Action::make('faltou')
                    ->label('Não compareceu')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->visible(fn (VisitaInteressado $record): bool => $record->status === StatusVisitaInteressado::Agendada)
                    ->action(fn (VisitaInteressado $record) => $this->alterarStatus($record, StatusVisitaInteressado::Faltou)),
                EditAction::make()
                    ->after(fn () => $this->sincronizarProximoContato()),
                DeleteAction::make()
                    ->after(fn () => $this->sincronizarProximoContato()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }

    private function alterarStatus(VisitaInteressado $visita, StatusVisitaInteressado $status): void
    {
        $visita->update(['status' => $status]);

        LeadScoreService::recalcular($this->getOwnerRecord());
    }

    /**
     * Mantém "Próximo Contato" do lead alinhado à próxima visita agendada.
     */
    private function sincronizarProximoContato(): void
    {
        VisitaInteressadoService::sincronizarProximoContato($this->getOwnerRecord());
        LeadScoreService::recalcular($this->getOwnerRecord());
    }
}
