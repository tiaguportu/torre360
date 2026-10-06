<?php

namespace App\Filament\Resources\PlanilhaLeiMensalidades\Schemas;

use App\Enums\StatusPlanilhaLei;
use App\Models\Curso;
use App\Models\Unidade;
use App\Services\PlanilhaLeiMensalidadeService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PlanilhaLeiMensalidadeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação e Escopo do Reajuste')
                    ->description('Defina os anos de referência e o segmento educacional.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('titulo')
                            ->label('Título da Planilha')
                            ->default(fn () => 'Planilha de Variação de Custos e Reajuste '.(now()->year + 1))
                            ->required()
                            ->columnSpan(2),

                        Select::make('status')
                            ->label('Status')
                            ->options(StatusPlanilhaLei::class)
                            ->default(StatusPlanilhaLei::Rascunho)
                            ->required(),

                        TextInput::make('ano_base')
                            ->label('Ano Base (Anterior)')
                            ->numeric()
                            ->default(fn () => now()->year)
                            ->required(),

                        TextInput::make('ano_letivo_destino')
                            ->label('Ano Letivo Projetado')
                            ->numeric()
                            ->default(fn () => now()->year + 1)
                            ->required(),

                        DatePicker::make('data_afixacao')
                            ->label('Data de Afixação Pública (Aviso Prévio 45 dias)')
                            ->default(now()->addDays(7)),

                        Select::make('unidade_id')
                            ->label('Unidade Escolar')
                            ->options(fn () => Unidade::pluck('nome', 'id'))
                            ->placeholder('Todas as Unidades')
                            ->searchable()
                            ->live(),

                        Select::make('curso_id')
                            ->label('Segmento / Curso')
                            ->options(fn (Get $get) => Curso::query()
                                ->when($get('unidade_id'), fn ($q) => $q->where('unidade_id', $get('unidade_id')))
                                ->pluck('nome_externo', 'id'))
                            ->placeholder('Educação Básica Geral')
                            ->searchable(),
                    ]),

                Section::make('Ano Base: Estrutura Real de Custos e Alunos')
                    ->description('Custos consolidados do exercício corrente/anterior para cálculo da base legal.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('alunos_base')
                            ->label('Alunos Pagantes no Ano Base')
                            ->numeric()
                            ->default(150)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcular($set, $get)),

                        TextInput::make('mensalidade_media_base')
                            ->label('Mensalidade Média Vigente (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(850.00)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcular($set, $get)),

                        TextInput::make('custo_pessoal_base')
                            ->label('Folha de Pessoal + Encargos (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(840000.00)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcular($set, $get)),

                        TextInput::make('custo_custeio_base')
                            ->label('Custeio e Manutenção Geral (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(380000.00)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcular($set, $get)),

                        TextInput::make('custo_investimento_base')
                            ->label('Investimentos Realizados Base (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(30000.00)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcular($set, $get)),

                        TextInput::make('custo_total_base')
                            ->label('Total de Custos Base (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->readOnly(),
                    ]),

                Section::make('Variações e Projeções (Lei 9.870/1999)')
                    ->description('Fatores de acréscimo autorizados pela legislação federal.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('percentual_dissidio_pessoal')
                            ->label('Dissídio Salarial + Encargos (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(6.00)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcular($set, $get)),

                        TextInput::make('percentual_inflacao_custeio')
                            ->label('Inflação Insumos / Custeio (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(4.50)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcular($set, $get)),

                        TextInput::make('valor_novos_investimentos')
                            ->label('Novos Investimentos Pedagógicos (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(60000.00)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcular($set, $get)),

                        TextInput::make('custo_pessoal_projetado')
                            ->label('Pessoal Projetado (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->readOnly(),

                        TextInput::make('custo_custeio_projetado')
                            ->label('Custeio Projetado (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->readOnly(),

                        TextInput::make('custo_total_projetado')
                            ->label('Total Geral Projetado (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->readOnly(),
                    ]),

                Section::make('Índice da Lei e Fixação da Nova Mensalidade')
                    ->columns(3)
                    ->schema([
                        TextInput::make('variacao_custo_total_percentual')
                            ->label('Variação de Custos Total Lei (%)')
                            ->numeric()
                            ->suffix('%')
                            ->readOnly(),

                        TextInput::make('percentual_reajuste_adotado')
                            ->label('Reajuste Efetivamente Adotado (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(7.50)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcular($set, $get)),

                        TextInput::make('mensalidade_projetada')
                            ->label('Nova Mensalidade Fixada (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->readOnly(),

                        TextInput::make('anuidade_projetada')
                            ->label('Nova Anuidade Total (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->readOnly(),

                        TextInput::make('meta_alunos_projetada')
                            ->label('Meta de Alunos no Ano Destino')
                            ->numeric()
                            ->default(150),

                        Placeholder::make('conformidade_procon')
                            ->label('Parecer de Conformidade Legal')
                            ->content(function (Get $get): string {
                                $leiPct = (float) ($get('variacao_custo_total_percentual') ?? 0);
                                $adotado = (float) ($get('percentual_reajuste_adotado') ?? 0);

                                if ($adotado <= $leiPct) {
                                    return "🟢 Totalmente em Conformidade: O reajuste adotado ({$adotado}%) é suportado pela variação de custos apurada ({$leiPct}%).";
                                }

                                return "⚠️ Atenção Jurídica: O reajuste adotado ({$adotado}%) excede a variação apurada ({$leiPct}%). Requer detalhamento das inovações pedagógicas na justificativa abaixo.";
                            }),
                    ]),

                Section::make('Justificativa Didático-Pedagógica e Melhorias (Obrigatório por Lei)')
                    ->description('Conforme Art. 1º, § 3º da Lei Federal 9.870/99, descreva as inovações, reformas e programas pedagógicos implementados.')
                    ->schema([
                        Textarea::make('justificativa_pedagogica')
                            ->label('Aprimoramentos Pedagógicos e Estruturais')
                            ->placeholder('Ex: Implantação de laboratório de robótica educacional, ampliação do programa bilíngue, capacitação continuada do corpo docente e modernização do ambiente digital.')
                            ->rows(4)
                            ->required(),
                    ]),
            ]);
    }

    public static function recalcular(Set $set, Get $get): void
    {
        $service = app(PlanilhaLeiMensalidadeService::class);

        $dados = [
            'alunos_base' => $get('alunos_base'),
            'mensalidade_media_base' => $get('mensalidade_media_base'),
            'custo_pessoal_base' => $get('custo_pessoal_base'),
            'custo_custeio_base' => $get('custo_custeio_base'),
            'custo_investimento_base' => $get('custo_investimento_base'),
            'percentual_dissidio_pessoal' => $get('percentual_dissidio_pessoal'),
            'percentual_inflacao_custeio' => $get('percentual_inflacao_custeio'),
            'valor_novos_investimentos' => $get('valor_novos_investimentos'),
            'percentual_reajuste_adotado' => $get('percentual_reajuste_adotado'),
            'meta_alunos_projetada' => $get('meta_alunos_projetada'),
        ];

        $calc = $service->calcularIndices($dados);

        $set('custo_total_base', $calc['custo_total_base']);
        $set('custo_pessoal_projetado', $calc['custo_pessoal_projetado']);
        $set('custo_custeio_projetado', $calc['custo_custeio_projetado']);
        $set('custo_total_projetado', $calc['custo_total_projetado']);
        $set('variacao_custo_total_percentual', $calc['variacao_custo_total_percentual']);
        $set('mensalidade_projetada', $calc['mensalidade_projetada']);
        $set('anuidade_projetada', $calc['anuidade_projetada']);
    }
}
