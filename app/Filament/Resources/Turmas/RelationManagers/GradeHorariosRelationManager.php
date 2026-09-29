<?php

namespace App\Filament\Resources\Turmas\RelationManagers;

use App\Models\GradeHorario;
use App\Services\GradeHorarioConflitoService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Grade horária semanal recorrente da turma. Cada linha vale para todas as
 * semanas do período letivo — é a origem do cronograma de aulas gerado pela
 * ação "Gerar Cronograma do Período" (na listagem de Turmas).
 */
class GradeHorariosRelationManager extends RelationManager
{
    protected static string $relationship = 'gradeHorarios';

    protected static ?string $title = 'Grade Horária';

    /**
     * @var array<int, string>
     */
    private const DIAS_SEMANA = [
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
        0 => 'Domingo',
    ];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('disciplina_id')
                    ->label('Disciplina')
                    ->relationship('disciplina', 'nome')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('professor_id')
                    ->label('Professor')
                    ->relationship('professor', 'nome')
                    ->searchable()
                    ->preload(),
                Select::make('sala_id')
                    ->label('Sala')
                    ->relationship('sala', 'nome', fn ($query) => $query->ativas())
                    ->searchable()
                    ->preload(),
                Select::make('dia_semana')
                    ->label('Dia da Semana')
                    ->options(self::DIAS_SEMANA)
                    ->required()
                    ->native(false),
                TimePicker::make('hora_inicio')
                    ->label('Início')
                    ->seconds(false)
                    ->required(),
                TimePicker::make('hora_fim')
                    ->label('Fim')
                    ->seconds(false)
                    ->required()
                    ->after('hora_inicio'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('dia_semana')
            ->columns([
                TextColumn::make('dia_semana')
                    ->label('Dia')
                    ->formatStateUsing(fn (int $state): string => self::DIAS_SEMANA[$state] ?? '—')
                    ->sortable(),
                TextColumn::make('hora_inicio')
                    ->label('Início')
                    ->time('H:i')
                    ->sortable(),
                TextColumn::make('hora_fim')
                    ->label('Fim')
                    ->time('H:i'),
                TextColumn::make('disciplina.nome')
                    ->label('Disciplina')
                    ->searchable(),
                TextColumn::make('professor.nome')
                    ->label('Professor')
                    ->placeholder('—'),
                TextColumn::make('sala.nome')
                    ->label('Sala')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->before(function (CreateAction $action, array $data) {
                        $this->validarConflito($action, $data);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->before(function (EditAction $action, array $data, GradeHorario $record) {
                        $this->validarConflito($action, $data, $record);
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validarConflito(CreateAction|EditAction $action, array $data, ?GradeHorario $record = null): void
    {
        $grade = $record ? (clone $record) : new GradeHorario;
        $grade->fill($data);
        $grade->turma_id = $this->getOwnerRecord()->getKey();

        $conflitos = app(GradeHorarioConflitoService::class)->conflitos($grade);

        if ($conflitos === []) {
            return;
        }

        Notification::make()
            ->danger()
            ->title('Conflito de horário')
            ->body(implode(' ', $conflitos))
            ->persistent()
            ->send();

        $action->halt();
    }
}
