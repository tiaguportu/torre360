<?php

namespace App\Filament\Resources\Series\RelationManagers;

use App\Services\MatrizCurricularService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Grade curricular de referência da série: quais disciplinas ela deve ter e a
 * carga horária semanal esperada. Usada por `MatrizCurricularService` para
 * popular automaticamente as disciplinas de novas turmas dessa série.
 */
class MatrizCurricularRelationManager extends RelationManager
{
    protected static string $relationship = 'matrizCurricular';

    protected static ?string $title = 'Matriz Curricular';

    protected static ?string $recordTitleAttribute = 'disciplina.nome';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('disciplina_id')
                    ->label('Disciplina')
                    ->relationship('disciplina', 'nome')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('serie_id', $this->getOwnerRecord()->id)),
                TextInput::make('carga_horaria_semanal')
                    ->label('Carga Horária Semanal (aulas)')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('ordem')
                    ->label('Ordem')
                    ->numeric()
                    ->minValue(0),
                Toggle::make('obrigatoria')
                    ->label('Obrigatória')
                    ->default(true)
                    ->helperText('Disciplinas não obrigatórias (optativas) também são sincronizadas com as turmas.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordem')
            ->columns([
                TextColumn::make('disciplina.nome')
                    ->label('Disciplina')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('carga_horaria_semanal')
                    ->label('Carga Horária')
                    ->suffix(' aula(s)/semana')
                    ->placeholder('—'),
                IconColumn::make('obrigatoria')
                    ->label('Obrigatória')
                    ->boolean(),
                TextColumn::make('ordem')
                    ->label('Ordem')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
                Action::make('sincronizarTurmas')
                    ->label('Sincronizar Turmas Existentes')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Adiciona às turmas já existentes desta série as disciplinas da matriz que ainda não estiverem vinculadas. Disciplinas já vinculadas não são alteradas.')
                    ->action(function () {
                        $service = app(MatrizCurricularService::class);
                        $total = 0;

                        foreach ($this->getOwnerRecord()->turmas as $turma) {
                            $total += $service->sincronizarTurmaDisciplinas($turma);
                        }

                        Notification::make()
                            ->title($total > 0 ? "{$total} disciplina(s) adicionada(s) às turmas." : 'Nenhuma disciplina nova para adicionar.')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
