<?php

namespace App\Filament\Resources\DisponibilidadeVisitas;

use App\Filament\Resources\DisponibilidadeVisitas\Pages\ManageDisponibilidadeVisitas;
use App\Models\DisponibilidadeVisita;
use App\Models\Unidade;
use App\Models\User;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Horários em que cada consultor recebe visitas (Onda 86). Os horários livres da página pública `/visita` saem daqui.
 */
class DisponibilidadeVisitaResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = DisponibilidadeVisita::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static \UnitEnum|string|null $navigationGroup = 'CRM / Comercial';

    protected static ?string $modelLabel = 'Horário de visita';

    protected static ?string $pluralModelLabel = 'Agenda de Visitas';

    protected static ?string $slug = 'agenda-visitas';

    protected static ?int $navigationSort = 36;

    public static function getPermissionPrefixes(): array
    {
        return ['view_any', 'view', 'create', 'update', 'delete'];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['consultor', 'unidade']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label('Consultor')
                ->options(fn (): array => User::query()->ativos()->orderBy('name')->pluck('name', 'id')->all())
                ->default(fn () => auth()->id())
                ->searchable()
                ->required(),
            Select::make('unidade_id')
                ->label('Unidade')
                ->helperText('Em branco: recebe visitas de qualquer unidade.')
                ->options(fn (): array => Unidade::emAtividade()->orderBy('nome')->pluck('nome', 'id')->all())
                ->placeholder('Qualquer unidade'),
            Select::make('dia_semana')
                ->label('Dia da semana')
                ->options(DisponibilidadeVisita::diasDaSemana())
                ->required(),
            Grid::make(3)->schema([
                TextInput::make('hora_inicio')->label('Das')->type('time')->required(),
                TextInput::make('hora_fim')->label('Às')->type('time')->required()->after('hora_inicio'),
                TextInput::make('duracao_minutos')->label('Duração de cada visita (min)')->numeric()->minValue(10)->maxValue(240)->default(30)->required(),
            ]),
            Grid::make(2)->schema([
                TextInput::make('antecedencia_minima_horas')->label('Antecedência mínima (horas)')->numeric()->minValue(0)->maxValue(720)->default(24)->required(),
                TextInput::make('antecedencia_maxima_dias')->label('Oferecer até (dias à frente)')->numeric()->minValue(1)->maxValue(120)->default(30)->required(),
            ]),
            Toggle::make('ativo')->label('Oferecer este horário à família')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('consultor.name')->label('Consultor')->searchable(),
                TextColumn::make('dia_semana')->label('Dia')->formatStateUsing(fn (int $state): string => DisponibilidadeVisita::diasDaSemana()[$state] ?? (string) $state)->sortable(),
                TextColumn::make('hora_inicio')->label('Das')->time('H:i'),
                TextColumn::make('hora_fim')->label('Às')->time('H:i'),
                TextColumn::make('duracao_minutos')->label('Duração')->suffix(' min'),
                TextColumn::make('unidade.nome')->label('Unidade')->placeholder('Qualquer'),
                IconColumn::make('ativo')->label('Ativo')->boolean(),
            ])
            ->defaultSort('dia_semana')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDisponibilidadeVisitas::route('/')];
    }
}
