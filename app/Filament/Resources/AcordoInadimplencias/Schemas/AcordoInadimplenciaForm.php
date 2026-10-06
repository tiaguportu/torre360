<?php

namespace App\Filament\Resources\AcordoInadimplencias\Schemas;

use App\Enums\StatusAcordoInadimplencia;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Services\AcordoInadimplenciaService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class AcordoInadimplenciaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação da Matrícula e do Responsável Devedor')
                    ->description('Selecione o estudante matriculado para carregar as pendências financeiras.')
                    ->columns(2)
                    ->schema([
                        Select::make('matricula_id')
                            ->label('Estudante / Matrícula')
                            ->options(function () {
                                return Matricula::with('pessoa')
                                    ->latest()
                                    ->limit(100)
                                    ->get()
                                    ->mapWithKeys(fn (Matricula $m) => [
                                        $m->id => "{$m->pessoa?->nome} (Matrícula #{$m->id} - {$m->turma?->nome})",
                                    ]);
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                                if (! $state) {
                                    return;
                                }

                                $matricula = Matricula::with(['pessoa', 'contrato.responsaveisFinanceiros.pessoa'])->find($state);
                                if (! $matricula) {
                                    return;
                                }

                                // Identifica o responsável financeiro prioritário
                                $resp = $matricula->contrato?->responsaveisFinanceiros?->first()?->pessoa ?? $matricula->pessoa;
                                if ($resp) {
                                    $set('responsavel_pessoa_id', $resp->id);
                                }

                                // Localiza faturas vencidas
                                $service = app(AcordoInadimplenciaService::class);
                                $faturasVencidas = $service->localizarFaturasVencidas((int) $state);

                                $totalOriginal = 0;
                                $faturasIds = [];
                                foreach ($faturasVencidas as $fat) {
                                    $totalOriginal += (float) ($fat->valor ?? 0);
                                    $faturasIds[] = $fat->id;
                                }

                                if ($totalOriginal <= 0) {
                                    $totalOriginal = 1500.00; // Padrão se não houver faturas com vencimento vencido no momento
                                }

                                $multa = round($totalOriginal * 0.02, 2); // 2% multa padrão
                                $juros = round($totalOriginal * 0.01, 2); // 1% juros padrão

                                $set('valor_original_total', $totalOriginal);
                                $set('valor_multa_original', $multa);
                                $set('valor_juros_original', $juros);
                                $set('faturas_originais_ids', $faturasIds);
                                $set('quantidade_faturas_originais', max(1, count($faturasIds)));

                                self::recalcularAcordo($set, $get);
                            }),

                        Select::make('responsavel_pessoa_id')
                            ->label('Responsável Legal / Devedor Principal')
                            ->options(function () {
                                return Pessoa::query()
                                    ->latest()
                                    ->limit(100)
                                    ->get()
                                    ->mapWithKeys(fn (Pessoa $p) => [
                                        $p->id => "{$p->nome} (CPF: ".($p->cpf ?? 'S/ CPF').')',
                                    ]);
                            })
                            ->searchable()
                            ->required(),
                    ]),

                Section::make('Simulação e Condições do Acordo')
                    ->description('Defina o percentual de desconto nos encargos e o plano de parcelamento facilitado.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('valor_original_total')
                            ->label('Dívida Original das Mensalidades')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcularAcordo($set, $get)),

                        TextInput::make('valor_multa_original')
                            ->label('Multa Acumulada (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcularAcordo($set, $get)),

                        TextInput::make('valor_juros_original')
                            ->label('Juros Acumulados (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcularAcordo($set, $get)),

                        TextInput::make('percentual_desconto_concedido')
                            ->label('Desconto Negociado (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(100)
                            ->helperText('Ex: 100% de desconto sobre multa e juros para viabilizar quitação.')
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcularAcordo($set, $get)),

                        TextInput::make('valor_desconto')
                            ->label('Desconto Concedido (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->readOnly(),

                        TextInput::make('valor_total_acordo')
                            ->label('Valor Líquido do Acordo (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->readOnly(),

                        TextInput::make('valor_entrada')
                            ->label('Valor da Entrada (Opcional)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcularAcordo($set, $get)),

                        TextInput::make('quantidade_parcelas')
                            ->label('Quantidade de Parcelas')
                            ->numeric()
                            ->default(4)
                            ->minValue(1)
                            ->maxValue(24)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcularAcordo($set, $get)),

                        TextInput::make('valor_parcela')
                            ->label('Valor de Cada Parcela (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->readOnly(),

                        TextInput::make('dia_vencimento_parcelas')
                            ->label('Dia de Vencimento')
                            ->numeric()
                            ->default(10)
                            ->minValue(1)
                            ->maxValue(28)
                            ->required(),

                        DatePicker::make('primeiro_vencimento')
                            ->label('Primeiro Vencimento do Parcelamento')
                            ->default(fn () => now()->addMonth()->day(10))
                            ->required(),

                        Select::make('status')
                            ->label('Status Inicial')
                            ->options(StatusAcordoInadimplencia::class)
                            ->default(StatusAcordoInadimplencia::AguardandoAceite)
                            ->required(),
                    ]),

                Section::make('Observações Internas')
                    ->schema([
                        Textarea::make('observacoes')
                            ->label('Observações do Acordo')
                            ->placeholder('Ex: Negociação realizada por telefone com a mãe do aluno; compromisso de pagamento da entrada via Pix.')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function recalcularAcordo(Set $set, Get $get): void
    {
        $service = app(AcordoInadimplenciaService::class);

        $valorOriginal = (float) ($get('valor_original_total') ?? 0);
        $valorMulta = (float) ($get('valor_multa_original') ?? 0);
        $valorJuros = (float) ($get('valor_juros_original') ?? 0);
        $pctDesconto = (float) ($get('percentual_desconto_concedido') ?? 0);
        $entrada = (float) ($get('valor_entrada') ?? 0);
        $parcelas = (int) ($get('quantidade_parcelas') ?? 1);
        $diaVenc = (int) ($get('dia_vencimento_parcelas') ?? 10);
        $primeiroVenc = $get('primeiro_vencimento');

        $calc = $service->simularAcordo(
            $valorOriginal,
            $valorMulta,
            $valorJuros,
            $pctDesconto,
            $entrada,
            $parcelas,
            $diaVenc,
            $primeiroVenc
        );

        $set('valor_desconto', $calc['valor_desconto']);
        $set('valor_total_acordo', $calc['valor_total_acordo']);
        $set('valor_parcela', $calc['valor_parcela']);
    }
}
