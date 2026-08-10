<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FichaMedicaResource\Pages;
use App\Models\FichaMedica;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FichaMedicaResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = FichaMedica::class;

    protected static ?string $modelLabel = 'Ficha Médica e Saúde';

    protected static ?string $pluralModelLabel = 'Fichas Médicas e Restrições';

    protected static string|\UnitEnum|null $navigationGroup = 'Saúde Escolar';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

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
                Section::make('Identificação do Aluno')
                    ->schema([
                        Select::make('pessoa_id')
                            ->label('Aluno')
                            ->relationship('pessoa', 'nome', fn ($query) => $query->whereHas('matriculas'))
                            ->searchable(['nome', 'cpf'])
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome.($record->cpf ? " - CPF: {$record->cpf}" : ''))
                            ->required()
                            ->unique(ignoreRecord: true),
                        Select::make('tipo_sanguineo')
                            ->label('Tipo Sanguíneo')
                            ->options([
                                'A+' => 'A+',
                                'A-' => 'A-',
                                'B+' => 'B+',
                                'B-' => 'B-',
                                'AB+' => 'AB+',
                                'AB-' => 'AB-',
                                'O+' => 'O+',
                                'O-' => 'O-',
                            ]),
                        TextInput::make('plano_saude')
                            ->label('Plano de Saúde / Convênio'),
                        TextInput::make('numero_carteira_sus')
                            ->label('Nº Carteira do SUS'),
                        TextInput::make('hospital_preferencia')
                            ->label('Hospital de Preferência'),
                    ])
                    ->columns(2),

                Section::make('Restrições Alimentares & Alergias (Alerta para Cantina/Cozinha)')
                    ->description('Marque as alergias alimentares ativas para emissão de alertas na escola.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Toggle::make('has_alergia_lactose')
                                    ->label('Alergia / Intolerância a Lactose')
                                    ->colors(['on' => 'danger', 'off' => 'gray']),
                                Toggle::make('has_alergia_gluten')
                                    ->label('Intolerância a Glúten (Celíaco)')
                                    ->colors(['on' => 'danger', 'off' => 'gray']),
                                Toggle::make('has_alergia_amendoim')
                                    ->label('Alergia a Amendoim / Oleaginosas')
                                    ->colors(['on' => 'danger', 'off' => 'gray']),
                            ]),
                        Textarea::make('outras_alergias_alimentares')
                            ->label('Outras Alergias Alimentares ou Medicamentosas')
                            ->placeholder('Ex: Frutos do mar, corantes amarelados, dipirona...')
                            ->rows(2),
                        Textarea::make('observacoes_alimentares')
                            ->label('Recomendações Diárias para Cantina e Merenda')
                            ->placeholder('Instruções para substituição de refeições...')
                            ->rows(2),
                    ]),

                Section::make('Medicamentos de Uso Contínuo')
                    ->schema([
                        Repeater::make('medicamentos')
                            ->relationship('medicamentos')
                            ->schema([
                                TextInput::make('nome_medicamento')
                                    ->label('Nome do Medicamento')
                                    ->required(),
                                TextInput::make('dosagem')
                                    ->label('Dosagem')
                                    ->placeholder('Ex: 5ml ou 1 comprimido'),
                                TextInput::make('horario_administracao')
                                    ->label('Horário de Administração')
                                    ->placeholder('Ex: 14:00'),
                                Toggle::make('autorizado_responsaveis')
                                    ->label('Autorizado pelos Pais')
                                    ->default(true),
                                Textarea::make('instrucoes')
                                    ->label('Instruções Especiais')
                                    ->columnSpanFull(),
                            ])
                            ->columns(4)
                            ->itemLabel(fn (array $state): ?string => $state['nome_medicamento'] ?? null)
                            ->collapsible(),
                    ]),

                Section::make('Contatos de Emergência')
                    ->schema([
                        Repeater::make('contatosEmergencia')
                            ->relationship('contatosEmergencia')
                            ->schema([
                                TextInput::make('nome')
                                    ->label('Nome do Contato')
                                    ->required(),
                                TextInput::make('parentesco_grau')
                                    ->label('Grau de Parentesco')
                                    ->placeholder('Mãe, Pai, Tio, Vizinho')
                                    ->required(),
                                TextInput::make('telefone_principal')
                                    ->label('Telefone Principal')
                                    ->required(),
                                TextInput::make('telefone_secundario')
                                    ->label('Telefone Secundário'),
                            ])
                            ->columns(4)
                            ->collapsible(),
                    ]),

                Section::make('Observações Gerais de Saúde')
                    ->schema([
                        Textarea::make('observacoes_gerais')
                            ->label('Outras Condições Médicas ou Observações')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pessoa.nome')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tipo_sanguineo')
                    ->label('Tipo Sang.')
                    ->badge()
                    ->color('info'),
                IconColumn::make('has_alergia_lactose')
                    ->label('Lactose')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check')
                    ->trueColor('danger')
                    ->falseColor('gray'),
                IconColumn::make('has_alergia_gluten')
                    ->label('Glúten')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check')
                    ->trueColor('danger')
                    ->falseColor('gray'),
                IconColumn::make('has_alergia_amendoim')
                    ->label('Amendoim')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check')
                    ->trueColor('danger')
                    ->falseColor('gray'),
                TextColumn::make('medicamentos_count')
                    ->label('Medicamentos')
                    ->counts('medicamentos')
                    ->badge(),
                TextColumn::make('contatos_emergencia_count')
                    ->label('Contatos')
                    ->counts('contatosEmergencia')
                    ->badge(),
            ])
            ->stackedOnMobile();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFichaMedicas::route('/'),
            'create' => Pages\CreateFichaMedica::route('/create'),
            'edit' => Pages\EditFichaMedica::route('/{record}/edit'),
        ];
    }
}
