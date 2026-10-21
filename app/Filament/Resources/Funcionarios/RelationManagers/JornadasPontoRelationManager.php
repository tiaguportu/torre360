<?php

declare(strict_types=1);

namespace App\Filament\Resources\Funcionarios\RelationManagers;

use App\Models\PontoJornada;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Jornada contratada (minutos previstos por dia da semana). Define o que a apuração trata como falta e como hora extra.
 * Não se apaga: uma mudança de jornada encerra a vigência e abre outra.
 */
class JornadasPontoRelationManager extends RelationManager
{
    protected static string $relationship = 'jornadasPonto';

    protected static ?string $title = 'Jornada do Ponto';

    public static function canViewForRecord(Model $ownerRecord, string $pageName): bool
    {
        return (bool) auth()->user()?->can('viewAny', PontoJornada::class);
    }

    public function form(Schema $schema): Schema
    {
        $dias = ['seg' => 'Segunda', 'ter' => 'Terça', 'qua' => 'Quarta', 'qui' => 'Quinta', 'sex' => 'Sexta', 'sab' => 'Sábado', 'dom' => 'Domingo'];

        return $schema
            ->components([
                DatePicker::make('vigencia_inicio')->label('Início da vigência')->native(false)->displayFormat('d/m/Y')->required(),
                DatePicker::make('vigencia_fim')->label('Fim da vigência')->native(false)->displayFormat('d/m/Y')->afterOrEqual('vigencia_inicio')->helperText('Deixe em branco para a jornada em vigor.'),
                Grid::make(4)->schema(collect($dias)->map(fn (string $nome, string $sigla) => TextInput::make("minutos_{$sigla}")
                    ->label($nome)
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(1440)
                    ->default($sigla === 'sab' || $sigla === 'dom' ? 0 : 480)
                    ->suffix('min')
                    ->required())->values()->all())->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('vigencia_inicio')
            ->defaultSort('vigencia_inicio', 'desc')
            ->columns([
                TextColumn::make('vigencia_inicio')->label('Início')->date('d/m/Y'),
                TextColumn::make('vigencia_fim')->label('Fim')->date('d/m/Y')->placeholder('Em vigor'),
                TextColumn::make('semanal')
                    ->label('Carga semanal')
                    ->state(fn (PontoJornada $r): string => sprintf('%02d:%02d', intdiv($r->minutosSemanais(), 60), $r->minutosSemanais() % 60)),
            ])
            ->headerActions([
                CreateAction::make()->label('Nova jornada')->visible(fn (): bool => (bool) auth()->user()?->can('create', PontoJornada::class)),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (PontoJornada $record): bool => (bool) auth()->user()?->can('update', $record)),
            ])
            ->stackedOnMobile();
    }
}
