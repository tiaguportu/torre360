<?php

namespace App\Filament\Resources\PeriodoLetivos\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PeriodoLetivoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required(),
                DatePicker::make('data_inicio')
                    ->required(),
                DatePicker::make('data_fim')
                    ->required(),
                TextInput::make('nota_aprovacao')
                    ->label('Nota Mínima para Aprovação')
                    ->numeric()
                    ->default(7)
                    ->required()
                    ->helperText('Média final igual ou superior a este valor aprova o aluno na disciplina.'),
                TextInput::make('nota_recuperacao_minima')
                    ->label('Nota Mínima para Recuperação')
                    ->numeric()
                    ->default(5)
                    ->required()
                    ->helperText('Média final igual ou superior a este valor (e abaixo da nota de aprovação) coloca o aluno em recuperação. Abaixo disso, reprovado.'),

                Section::make('Recuperação e Exame Final')
                    ->description('Configurações de como o Fechamento do Ciclo Letivo trata avaliações de recuperação e exame final para este período.')
                    ->schema([
                        Toggle::make('recuperacao_por_etapa')
                            ->label('Recuperação por etapa (em vez de recuperação anual)')
                            ->default(false)
                            ->live()
                            ->helperText('Desligado (padrão): todas as avaliações de recuperação do período são somadas e substituem a menor média de etapa ("recuperação anual"). Ligado: cada avaliação de recuperação só pode substituir a média da própria etapa em que foi lançada, permitindo recuperar mais de uma etapa de forma independente.'),
                        Toggle::make('exame_final_habilitado')
                            ->label('Permitir exame final')
                            ->default(false)
                            ->live()
                            ->helperText('Quando ligado, disciplinas que ficarem em situação de Recuperação após o fechamento do ciclo podem receber uma nota de exame final na própria tela de Fechamento do Ciclo Letivo.'),
                        TextInput::make('nota_aprovacao_pos_exame')
                            ->label('Nota Mínima para Aprovação após o Exame Final')
                            ->numeric()
                            ->default(5)
                            ->required(fn (Get $get) => (bool) $get('exame_final_habilitado'))
                            ->visible(fn (Get $get) => (bool) $get('exame_final_habilitado'))
                            ->helperText('A nota final após o exame é a média simples entre a média do período e a nota do exame. Esse valor define aprovação ou reprovação — não há uma segunda recuperação.'),
                    ])
                    ->columns(1),
            ]);
    }
}
