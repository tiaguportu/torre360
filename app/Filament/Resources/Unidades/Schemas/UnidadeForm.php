<?php

namespace App\Filament\Resources\Unidades\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UnidadeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações Gerais')
                    ->schema([
                        TextInput::make('nome')
                            ->required(),
                        TextInput::make('cnpj')
                            ->mask('99.999.999/9999-99'),
                        TextInput::make('codigo_inep')
                            ->label('Código INEP')
                            ->maxLength(255),
                        Select::make('situacao_funcionamento')
                            ->label('Situação de Funcionamento')
                            ->options([
                                '1' => '1-Em atividade',
                                '2' => '2-Paralisada',
                                '3' => '3-Extinta',
                            ])
                            ->default('1')
                            ->required(),
                        Select::make('instituicao_ensino_id')
                            ->label('Instituição de Ensino')
                            ->relationship('instituicaoEnsino', 'nome')
                            ->searchable()
                            ->preload(),
                        Select::make('endereco_id')
                            ->label('Endereço')
                            ->relationship('endereco', 'logradouro')
                            ->searchable()
                            ->preload(),
                    ])->columns(2),

                Section::make('Dados Administrativos e Censo/INEP')
                    ->schema([
                        TextInput::make('codigo_orgao_regional_ensino')
                            ->label('Código do órgão regional de ensino')
                            ->maxLength(255),
                        Select::make('localizacao_zona')
                            ->label('Localização / Zona da escola')
                            ->options([
                                '1' => '1-Urbana',
                                '2' => '2-Rural',
                            ]),
                        Select::make('localizacao_diferenciada')
                            ->label('Localização diferenciada da escola')
                            ->options([
                                '1' => '1-Área de assentamento',
                                '2' => '2-Terra indígena',
                                '3' => '3-Comunidade quilombola',
                                '7' => '7-Não está em área de localização diferenciada',
                                '8' => '8-Área onde se localizam povos e comunidades tradicionais',
                            ]),
                        Select::make('dependencia_administrativa')
                            ->label('Dependência administrativa')
                            ->options([
                                '1' => '1-Federal',
                                '2' => '2-Estadual',
                                '3' => '3-Municipal',
                                '4' => '4-Privada',
                            ]),
                        TextInput::make('orgao_vinculado_escola_publica')
                            ->label('Órgão ao qual a escola pública está vinculada')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Vínculos com Órgãos Públicos / Mantenedores')
                    ->schema([
                        Toggle::make('flag_secretaria_educacao_mec')
                            ->label('Secretaria de Educação/Ministério da Educação'),
                        Toggle::make('flag_seguranca_publica_forcas_armadas')
                            ->label('Secretaria de Segurança Pública/Forças Armadas/Militar'),
                        Toggle::make('flag_secretaria_saude')
                            ->label('Secretaria da Saúde/Ministério da Saúde'),
                        Toggle::make('flag_outro_orgao_publico')
                            ->label('Outro órgão da administração pública'),
                    ])->columns(2),

                Section::make('Contato e Redes Sociais')
                    ->description('Configurações de contato exclusivas da unidade.')
                    ->schema([
                        TextInput::make('telefone')
                            ->label('Telefone')
                            ->placeholder('(99)99999-999')
                            ->mask('(99)99999-9999'),
                        TextInput::make('email')
                            ->label('E-mail')
                            ->email(),
                        TextInput::make('celular_whatsapp')
                            ->label('Celular / WhatsApp')
                            ->placeholder('(00) 00000-0000'),
                        TextInput::make('instagram')
                            ->label('Instagram URL')
                            ->placeholder('https://instagram.com/unidade'),
                        TextInput::make('facebook')
                            ->label('Facebook URL')
                            ->placeholder('https://facebook.com/unidade'),
                        TextInput::make('youtube')
                            ->label('YouTube URL')
                            ->placeholder('https://youtube.com/c/unidade'),
                    ])->columns(2),
            ]);
    }
}
