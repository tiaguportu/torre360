<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AtendimentoEnfermagemResource\Pages;
use App\Models\AtendimentoEnfermagem;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AtendimentoEnfermagemResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = AtendimentoEnfermagem::class;

    protected static ?string $modelLabel = 'Atendimento de Enfermagem';

    protected static ?string $pluralModelLabel = 'Atendimentos do Ambulatório';

    protected static string|\UnitEnum|null $navigationGroup = 'Saúde Escolar';

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

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
                Section::make('Registro do Atendimento')
                    ->schema([
                        Select::make('pessoa_id')
                            ->label('Aluno Atendido')
                            ->relationship('pessoa', 'nome')
                            ->searchable(['nome', 'cpf'])
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome.($record->cpf ? " - CPF: {$record->cpf}" : ''))
                            ->required(),
                        DateTimePicker::make('data_hora')
                            ->label('Data e Hora do Atendimento')
                            ->default(now())
                            ->native(false)
                            ->required(),
                        Select::make('atendido_por_user_id')
                            ->label('Atendido Por (Responsável do Ambulatório)')
                            ->relationship('atendidoPor', 'name')
                            ->default(auth()->id())
                            ->searchable()
                            ->required(),
                        Toggle::make('notificado_responsaveis')
                            ->label('Responsáveis Notificados')
                            ->default(false),
                    ])
                    ->columns(2),

                Section::make('Detalhes do Quadro de Saúde')
                    ->schema([
                        Textarea::make('sintomas_queixa')
                            ->label('Sintomas / Queixa do Aluno')
                            ->placeholder('Ex: Dor de cabeça, febre, náusea, queda durante a recreação...')
                            ->required()
                            ->rows(3),
                        Textarea::make('procedimento_realizado')
                            ->label('Procedimento Realizado / Conduta')
                            ->placeholder('Ex: Medição de temperatura, repouso, curativo, compressa de gelo...')
                            ->required()
                            ->rows(3),
                        TextInput::make('medicamento_ministrado')
                            ->label('Medicamento Ministrado (Conforme Receita/Ficha Médica)')
                            ->placeholder('Ex: Paracetamol 500mg, Dipirona 20 gotas...'),
                        Textarea::make('observacoes')
                            ->label('Observações Adicionais / Recomendações aos Pais')
                            ->rows(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('data_hora')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('pessoa.nome')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sintomas_queixa')
                    ->label('Sintomas / Queixa')
                    ->limit(35),
                TextColumn::make('medicamento_ministrado')
                    ->label('Medicamento')
                    ->placeholder('-'),
                TextColumn::make('atendidoPor.name')
                    ->label('Atendente'),
                IconColumn::make('notificado_responsaveis')
                    ->label('Notificado')
                    ->boolean(),
            ])
            ->defaultSort('data_hora', 'desc')
            ->stackedOnMobile();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAtendimentoEnfermagems::route('/'),
            'create' => Pages\CreateAtendimentoEnfermagem::route('/create'),
            'edit' => Pages\EditAtendimentoEnfermagem::route('/{record}/edit'),
        ];
    }
}
