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
    private static function rotuloDoLead(Interessado $lead): string
    {
        return ($lead->pessoa?->nome ?? 'Lead #'.$lead->id).' (Score: '.($lead->lead_score ?? 0).')';
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dados da Indicação')
                ->description('Vínculo entre a família que indicou e o novo lead interessado.')
                ->schema([
                    Grid::make(2)->schema([
                        // As duas listas eram carregadas inteiras (todas as pessoas; todos os leads, com a pessoa de cada
                        // um) a cada abertura do formulário. Agora a busca vem do servidor, limitada a 50 resultados.
                        Select::make('indicador_pessoa_id')
                            ->label('Família Indicadora (Quem indicou)')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Pessoa::query()->busca($search)->orderBy('nome')->limit(50)->pluck('nome', 'id')->all())
                            ->getOptionLabelUsing(fn ($value): ?string => Pessoa::query()->whereKey($value)->value('nome'))
                            ->required(),

                        Select::make('interessado_id')
                            ->label('Lead Interessado (Quem foi indicado)')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Interessado::query()
                                ->with('pessoa')
                                ->whereHas('pessoa', fn ($pessoa) => $pessoa->busca($search))
                                ->latest('id')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (Interessado $lead): array => [$lead->id => self::rotuloDoLead($lead)])
                                ->all())
                            ->getOptionLabelUsing(fn ($value): ?string => ($lead = Interessado::query()->with('pessoa')->find($value)) ? self::rotuloDoLead($lead) : null)
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
