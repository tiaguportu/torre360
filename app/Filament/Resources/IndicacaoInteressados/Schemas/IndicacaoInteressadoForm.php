<?php

declare(strict_types=1);

namespace App\Filament\Resources\IndicacaoInteressados\Schemas;

use App\Models\Interessado;
use App\Models\Pessoa;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IndicacaoInteressadoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dados da Indicação')
                ->description('Vínculo entre a família que indicou e o novo lead interessado.')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('indicador_pessoa_id')
                            ->label('Família Indicadora (Quem indicou)')
                            ->options(fn () => Pessoa::orderBy('nome')->pluck('nome', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('interessado_id')
                            ->label('Lead Interessado (Quem foi indicado)')
                            ->options(fn () => Interessado::with('pessoa')->get()->mapWithKeys(fn ($lead) => [
                                $lead->id => ($lead->pessoa?->nome ?? 'Lead #'.$lead->id).' (Score: '.($lead->lead_score ?? 0).')',
                            ]))
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('codigo_indicacao')
                            ->label('Código Promocional / Cupom')
                            ->placeholder('Ex: FAM-SILVA-8821')
                            ->maxLength(50),

                        Select::make('status')
                            ->label('Situação da Indicação')
                            ->options([
                                'pendente' => '🟡 Pendente (Em Prospecção)',
                                'matriculado' => '🎉 Matriculado (Elegível a Recompensa)',
                                'recompensado' => '✅ Recompensado (Benefício Aplicado)',
                                'cancelado' => '❌ Cancelado',
                            ])
                            ->default('pendente')
                            ->required(),
                    ]),
                ]),

            Section::make('Benefício / Recompensa')
                ->description('Controle do incentivo concedido à família que indicou.')
                ->schema([
                    Grid::make(3)->schema([
                        Select::make('recompensa_tipo')
                            ->label('Tipo de Recompensa')
                            ->options([
                                'desconto_mensalidade' => 'Desconto em Mensalidade',
                                'desconto_rematricula' => 'Desconto na Rematrícula',
                                'brinde' => 'Kit Escolar / Uniforme',
                                'outro' => 'Outro Benefício',
                            ]),

                        TextInput::make('valor_recompensa')
                            ->label('Valor Estimado (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->placeholder('0,00'),

                        TextInput::make('recompensa_detalhe')
                            ->label('Detalhe da Recompensa')
                            ->placeholder('Ex: R$ 200 abatidos na fatura de março')
                            ->maxLength(255),
                    ]),

                    Textarea::make('observacoes')
                        ->label('Observações Internas')
                        ->rows(3)
                        ->placeholder('Anotações da coordenação ou secretaria sobre a indicação...'),
                ]),
        ]);
    }
}
