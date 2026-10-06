<?php

namespace App\Filament\Resources\PropostaComercials\Schemas;

use App\Enums\NivelAlcadaComercial;
use App\Models\Curso;
use App\Models\Interessado;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Services\RevenueManagementService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PropostaComercialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação do Responsável e Lead')
                    ->description('Vincule a um lead existente do CRM ou preencha os dados da família.')
                    ->columns(3)
                    ->schema([
                        Select::make('interessado_id')
                            ->label('Lead do CRM (Opcional)')
                            ->options(fn () => Interessado::latest()->limit(50)->pluck('nome', 'id'))
                            ->searchable()
                            ->preload()
                            ->default(request()->query('interessado_id'))
                            ->live()
                            ->afterStateHydrated(function (?string $state, Set $set): void {
                                if (! $state) {
                                    return;
                                }
                                $lead = Interessado::with('dependentes')->find($state);
                                if ($lead) {
                                    $set('responsavel_nome', $lead->nome);
                                    $set('responsavel_telefone', $lead->telefone);
                                    $set('responsavel_email', $lead->email);
                                    if ($lead->dependentes->isNotEmpty()) {
                                        $set('aluno_nome', $lead->dependentes->first()->nome);
                                    }
                                    if ($lead->unidade_id) {
                                        $set('unidade_id', $lead->unidade_id);
                                    }
                                }
                            })
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if (! $state) {
                                    return;
                                }
                                $lead = Interessado::with('dependentes')->find($state);
                                if ($lead) {
                                    $set('responsavel_nome', $lead->nome);
                                    $set('responsavel_telefone', $lead->telefone);
                                    $set('responsavel_email', $lead->email);
                                    if ($lead->dependentes->isNotEmpty()) {
                                        $set('aluno_nome', $lead->dependentes->first()->nome);
                                    }
                                    if ($lead->unidade_id) {
                                        $set('unidade_id', $lead->unidade_id);
                                    }
                                }
                            })
                            ->columnSpan(1),

                        TextInput::make('responsavel_nome')
                            ->label('Nome do Responsável')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),

                        TextInput::make('aluno_nome')
                            ->label('Nome do Aluno')
                            ->maxLength(255)
                            ->columnSpan(1),

                        TextInput::make('responsavel_telefone')
                            ->label('WhatsApp / Telefone')
                            ->tel()
                            ->maxLength(20),

                        TextInput::make('responsavel_email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255),

                        DatePicker::make('validade')
                            ->label('Validade da Proposta')
                            ->default(now()->addDays(7))
                            ->required(),
                    ]),

                Section::make('Segmento Escolar e Turma')
                    ->columns(3)
                    ->schema([
                        Select::make('unidade_id')
                            ->label('Unidade')
                            ->options(fn () => Unidade::pluck('nome', 'id'))
                            ->searchable()
                            ->required()
                            ->live(),

                        Select::make('curso_id')
                            ->label('Curso / Nível')
                            ->options(function (Get $get) {
                                $unidadeId = $get('unidade_id');

                                return Curso::query()
                                    ->when($unidadeId, fn ($q) => $q->where('unidade_id', $unidadeId))
                                    ->pluck('nome_externo', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->live(),

                        Select::make('serie_id')
                            ->label('Série / Ano')
                            ->options(function (Get $get) {
                                $cursoId = $get('curso_id');

                                return Serie::query()
                                    ->when($cursoId, fn ($q) => $q->where('curso_id', $cursoId))
                                    ->pluck('nome', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->live(),

                        Select::make('turma_id')
                            ->label('Turma de Interesse (Opcional)')
                            ->options(function (Get $get) {
                                $serieId = $get('serie_id');

                                return Turma::query()
                                    ->when($serieId, fn ($q) => $q->where('serie_id', $serieId))
                                    ->pluck('nome', 'id');
                            })
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                                if ($state) {
                                    $turma = Turma::find($state);
                                    if ($turma && (float) $turma->mensalidade_base > 0) {
                                        $set('valor_tabela_mensal', (float) $turma->mensalidade_base);
                                        self::recalcularValores($set, $get);
                                    }
                                }
                            }),

                        Select::make('turno_id')
                            ->label('Turno')
                            ->options(fn () => Turno::pluck('nome', 'id'))
                            ->searchable(),

                        TextInput::make('quantidade_alunos')
                            ->label('Qtd. de Filhos / Alunos')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcularValores($set, $get)),
                    ]),

                Section::make('Simulação Financeira & Alçadas de Desconto')
                    ->description('Informe o valor base e a condição comercial solicitada pela família.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('valor_tabela_mensal')
                            ->label('Mensalidade de Tabela (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcularValores($set, $get)),

                        Select::make('tipo_desconto')
                            ->label('Tipo de Desconto')
                            ->options([
                                'percentual' => 'Percentual (%)',
                                'fixo' => 'Valor Fixo (R$)',
                            ])
                            ->default('percentual')
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcularValores($set, $get)),

                        TextInput::make('desconto_solicitado')
                            ->label('Desconto Pretendido')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcularValores($set, $get)),

                        TextInput::make('valor_desconto_mensal')
                            ->label('Desconto Mensal (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->readOnly(),

                        TextInput::make('valor_liquido_mensal')
                            ->label('Mensalidade Líquida (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->readOnly(),

                        TextInput::make('valor_total_anual')
                            ->label('Total Anual do Contrato (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->readOnly(),

                        Placeholder::make('alcada_preview')
                            ->label('Nível de Alçada Necessário')
                            ->content(function (Get $get): string {
                                $service = app(RevenueManagementService::class);
                                $tabela = (float) ($get('valor_tabela_mensal') ?? 0);
                                $tipo = (string) ($get('tipo_desconto') ?? 'percentual');
                                $desc = (float) ($get('desconto_solicitado') ?? 0);
                                $calc = $service->calcularValores($tabela, $tipo, $desc);
                                $pct = $calc['percentual_desconto'];
                                $alcada = $calc['nivel_alcada'];

                                return match ($alcada) {
                                    NivelAlcadaComercial::Consultor => "🟢 Alçada Consultor ({$pct}%): Aprovação Imediata",
                                    NivelAlcadaComercial::Coordenacao => "🟡 Alçada Coordenação ({$pct}%): Requer autorização da Secretaria/Coordenação",
                                    NivelAlcadaComercial::Diretoria => "🔴 Alçada Diretoria Geral ({$pct}%): Requer aprovação da Mantenedora",
                                };
                            })
                            ->columnSpanFull(),

                        Textarea::make('motivo_desconto')
                            ->label('Justificativa / Motivo Comercial')
                            ->placeholder('Ex: Família com 2 filhos matriculados, proposta agressiva do Colégio concorrente X, convênio corporativo.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function recalcularValores(Set $set, Get $get): void
    {
        $service = app(RevenueManagementService::class);

        $tabela = (float) ($get('valor_tabela_mensal') ?? 0);
        $tipo = (string) ($get('tipo_desconto') ?? 'percentual');
        $desc = (float) ($get('desconto_solicitado') ?? 0);
        $qtdAlunos = (int) ($get('quantidade_alunos') ?? 1);
        $parcelas = (int) ($get('quantidade_parcelas') ?? 12);

        $resultado = $service->calcularValores($tabela, $tipo, $desc, $qtdAlunos, $parcelas);

        $set('valor_desconto_mensal', $resultado['valor_desconto_mensal']);
        $set('valor_liquido_mensal', $resultado['valor_liquido_mensal']);
        $set('valor_total_anual', $resultado['valor_total_anual']);
    }
}
