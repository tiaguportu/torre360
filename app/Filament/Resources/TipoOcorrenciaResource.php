<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoOcorrenciaResource\Pages;
use App\Models\TipoOcorrencia;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TipoOcorrenciaResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = TipoOcorrencia::class;

    protected static ?string $modelLabel = 'Tipo de Ocorrência';

    protected static ?string $pluralModelLabel = 'Tipos de Ocorrências';

    protected static string|\UnitEnum|null $navigationGroup = 'Convivência e Disciplina';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Configuração do Tipo de Ocorrência')
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome do Tipo de Ocorrência')
                            ->placeholder('Ex: Atraso na Chegada, Uniforme Incompleto, Elogio Pedagógico...')
                            ->required(),
                        Select::make('categoria')
                            ->label('Categoria')
                            ->options([
                                'disciplinar' => 'Disciplinar',
                                'operacional' => 'Operacional / Rotina',
                                'pedagogico' => 'Pedagógico',
                                'saude' => 'Saúde / Bem-Estar',
                            ])
                            ->required(),
                        Select::make('gravidade')
                            ->label('Gravidade / Impacto')
                            ->options([
                                'positiva' => 'Positiva / Elogio',
                                'leve' => 'Leve',
                                'media' => 'Média',
                                'grave' => 'Grave',
                            ])
                            ->required(),
                        Toggle::make('notificar_responsaveis_padrao')
                            ->label('Notificar Responsáveis por Padrão')
                            ->helperText('Define se o envio de alerta aos pais vem marcado por padrão ao registrar essa ocorrência.')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('categoria')
                    ->label('Categoria')
                    ->badge(),
                TextColumn::make('gravidade')
                    ->label('Gravidade')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'positiva' => 'success',
                        'leve' => 'info',
                        'media' => 'warning',
                        'grave' => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('notificar_responsaveis_padrao')
                    ->label('Notificar Pais (Padrão)')
                    ->boolean(),
            ])
            ->stackedOnMobile();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTipoOcorrencias::route('/'),
            'create' => Pages\CreateTipoOcorrencia::route('/create'),
            'edit' => Pages\EditTipoOcorrencia::route('/{record}/edit'),
        ];
    }
}
