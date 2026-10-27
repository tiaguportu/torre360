<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContasRecorrentes\Schemas;

use App\Enums\PeriodicidadeContaRecorrente;
use App\Models\CentroCusto;
use App\Models\Fornecedor;
use App\Models\PlanoConta;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContaRecorrenteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('A despesa')
                ->columns(2)
                ->schema([
                    TextInput::make('descricao')->label('Descrição')->placeholder('Ex.: Aluguel do prédio')->required()->maxLength(160)->columnSpanFull(),
                    Select::make('fornecedor_id')
                        ->label('Fornecedor')
                        ->options(fn (): array => Fornecedor::query()->orderBy('razao_social')->pluck('razao_social', 'id')->all())
                        ->searchable()
                        ->native(false),
                    Select::make('plano_conta_id')
                        ->label('Conta do plano (despesa)')
                        ->options(fn (): array => PlanoConta::query()->where('tipo', 'despesa')->where('is_active', true)->orderBy('codigo')->get()->mapWithKeys(fn (PlanoConta $p): array => [$p->id => "{$p->codigo} - {$p->nome}"])->all())
                        ->searchable()
                        ->native(false),
                    Select::make('centro_custo_id')
                        ->label('Centro de custo')
                        ->options(fn (): array => CentroCusto::query()->where('is_active', true)->orderBy('nome')->pluck('nome', 'id')->all())
                        ->searchable()
                        ->native(false),
                    TextInput::make('valor')->label('Valor (R$)')->numeric()->minValue(0.01)->required()->prefix('R$'),
                    Toggle::make('valor_estimado')->label('Valor estimado (varia a cada mês)')->helperText('Energia e água, por exemplo: a conta nasce "a revisar" e quem paga confere o valor da fatura.')->columnSpanFull(),
                ]),
            Section::make('Quando vence')
                ->columns(2)
                ->schema([
                    Select::make('periodicidade')->label('Periodicidade')->options(PeriodicidadeContaRecorrente::class)->default(PeriodicidadeContaRecorrente::Mensal->value)->native(false)->required(),
                    TextInput::make('dia_vencimento')->label('Dia do vencimento')->numeric()->minValue(1)->maxValue(31)->default(10)->required()->helperText('Dia 31 em mês curto vence no último dia do mês.'),
                    DatePicker::make('vigencia_inicio')->label('Vale a partir de')->native(false)->required()->default(now()),
                    DatePicker::make('vigencia_fim')->label('Vale até')->native(false)->helperText('Deixe em branco enquanto o contrato valer.')->afterOrEqual('vigencia_inicio'),
                    TextInput::make('antecedencia_dias')->label('Gerar a conta com quantos dias de antecedência')->numeric()->minValue(0)->maxValue(365)->default(10)->required(),
                    DatePicker::make('proximo_reajuste_em')->label('Próximo reajuste do contrato')->native(false)->helperText('A Minha Mesa avisa quando a data se aproxima.'),
                ]),
            Section::make('Observações')
                ->schema([
                    Textarea::make('observacao')->label('Observação')->rows(2),
                    Toggle::make('is_active')->label('Ativa')->default(true)->helperText('Desligue quando o contrato acabar: as contas já geradas continuam.'),
                ]),
        ]);
    }
}
