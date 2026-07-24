<?php

namespace App\Filament\Resources\Pessoas\Schemas;

use App\Enums\CorRaca;
use App\Enums\Sexo;
use App\Models\Pais;
use App\Services\GovCpfService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                    ->dehydrateStateUsing(fn (?string $state) => $state ? preg_replace('/[^0-9]/', '', $state) : null)
                    ->suffixAction(
                        Action::make('consultarCpfGov')
                            ->icon('heroicon-m-magnifying-glass')
                            ->tooltip('Consultar dados no Cadastro Base do Cidadão (Gov.br)')
                            ->action(function (Get $get, Set $set, GovCpfService $cpfService) {
                                $cpfStr = $get('cpf');
                                $cpfLimpo = preg_replace('/[^0-9]/', '', $cpfStr ?? '');

                                if (empty($cpfLimpo) || strlen($cpfLimpo) !== 11) {
                                    Notification::make()
                                        ->warning()
                                        ->title('CPF Inválido')
                                        ->body('Informe um CPF válido com 11 dígitos para realizar a consulta.')
                                        ->send();

                                    return;
                                }

                                try {
                                    $dados = $cpfService->consultarEPopularPessoa($cpfLimpo);

                                    if (! empty($dados['nome'])) {
                                        $set('nome', $dados['nome']);
                                    }
                                    if (! empty($dados['data_nascimento'])) {
                                        $set('data_nascimento', $dados['data_nascimento']);
                                    }
                                    if (! empty($dados['sexo'])) {
                                        $set('sexo', $dados['sexo']);
                                    }
                                    if (! empty($dados['nacionalidade_id'])) {
                                        $set('nacionalidade_id', $dados['nacionalidade_id']);
                                    }
                                    if (! empty($dados['naturalidade_id'])) {
                                        $set('naturalidade_id', $dados['naturalidade_id']);
                                    }

                                    Notification::make()
                                        ->success()
                                        ->title('Consulta realizada com sucesso!')
                                        ->body('As informações de Nome, Data de Nascimento, Sexo, Nacionalidade e Naturalidade foram preenchidas a partir da API do Governo.')
                                        ->send();
                                } catch (\Exception $e) {
                                    Notification::make()
                                        ->danger()
                                        ->title('Falha na Consulta de CPF')
                                        ->body($e->getMessage())
                                        ->send();
                                }
                            })
                    ),

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
