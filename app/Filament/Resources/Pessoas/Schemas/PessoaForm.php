<?php

namespace App\Filament\Resources\Pessoas\Schemas;

use App\Enums\CorRaca;
use App\Enums\Sexo;
use App\Models\Pais;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PessoaForm
{
    protected static ?int $brasilId = null;

    protected static function getBrasilId(): ?int
    {
        return self::$brasilId ??= Pais::where('nome', 'Brasil')->value('id');
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('foto')
                    ->image()
                    ->imageEditor()
                    ->imageCropAspectRatio('3:4')
                    ->imageEditorAspectRatios(['3:4'])
                    ->directory('pessoas_fotos'),

                TextInput::make('nome')
                    ->required()
                    ->maxLength(255),

                DatePicker::make('data_nascimento')
                    ->label('Data de Nascimento'),

                Select::make('nacionalidade_id')
                    ->relationship('nacionalidade', 'nome', fn ($query) => $query->whereNotNull('nome'))
                    ->default(fn () => self::getBrasilId())
                    ->getOptionLabelFromRecordUsing(fn ($record) => ($record->sigla ? mb_convert_encoding('&#'.(127397 + ord(strtoupper($record->sigla[0]))).';&#'.(127397 + ord(strtoupper($record->sigla[1]))).';', 'UTF-8', 'HTML-ENTITIES').' ' : '').$record->nome
                    )
                    ->live()
                    ->searchable()
                    ->preload(),

                Select::make('naturalidade_id')
                    ->relationship('naturalidade', 'nome', fn ($query) => $query->whereNotNull('nome'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->nome}-{$record->estado?->sigla}")
                    ->searchable()
                    ->preload()
                    ->visible(fn ($get) => $get('nacionalidade_id') &&
                        $get('nacionalidade_id') == self::getBrasilId()
                    ),

                TextInput::make('cpf')
                    ->mask('999.999.999-99')
                    ->unique(ignoreRecord: true)
                    ->maxLength(14)
                    ->dehydrateStateUsing(fn (?string $state) => $state ? preg_replace('/[^0-9]/', '', $state) : null),

                TextInput::make('email')
                    ->email()
                    ->maxLength(255),

                Select::make('estado_civil')
                    ->options([
                        'Solteiro(a)' => 'Solteiro(a)',
                        'Casado(a)' => 'Casado(a)',
                        'Divorciado(a)' => 'Divorciado(a)',
                        'Viúvo(a)' => 'Viúvo(a)',
                        'União Estável' => 'União Estável',
                    ])
                    ->searchable(),

                TextInput::make('profissao')
                    ->label('Profissão')
                    ->maxLength(255),

                TextInput::make('identidade')
                    ->label('Identidade (RG)')
                    ->maxLength(255),

                TextInput::make('telefone')
                    ->tel()
                    ->maxLength(20),

                Select::make('sexo')
                    ->options(Sexo::class)
                    ->searchable()
                    ->preload(),

                Select::make('cor_raca')
                    ->label('Cor / Raça')
                    ->options(CorRaca::class)
                    ->searchable()
                    ->preload(),

                TextInput::make('codigo')
                    ->label('Código do Aluno na Entidade/Escola')
                    ->maxLength(20),

                TextInput::make('codigo_inep')
                    ->label('Identificação Única (INEP)')
                    ->maxLength(12),

                TextInput::make('certidao_nascimento')
                    ->label('Certidão de Nascimento (Matrícula Civil)')
                    ->maxLength(32),

                TextInput::make('filiacao_1')
                    ->label('Filiação 1 (Mãe/Responsável 1)')
                    ->maxLength(100),

                TextInput::make('filiacao_2')
                    ->label('Filiação 2 (Pai/Responsável 2)')
                    ->maxLength(100),

                Select::make('users')
                    ->label('Usuários do Sistema')
                    ->relationship('users', 'name')
                    ->multiple()
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Este campo é apenas informativo. O vínculo de usuários deve ser gerenciado no recurso de Usuários.'),
            ]);
    }
}
