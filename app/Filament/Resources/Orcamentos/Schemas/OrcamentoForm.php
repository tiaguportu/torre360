<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orcamentos\Schemas;

use App\Models\Orcamento;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrcamentoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Novo orçamento')
                ->description('Comece em branco ou copie outro orçamento com um reajuste. Depois preencha a planilha e aprove: o orçamento aprovado não muda, só ganha revisões.')
                ->columns(2)
                ->schema([
                    Select::make('ano')
                        ->label('Ano')
                        ->options(fn (): array => collect(range(now()->year - 1, now()->year + 2))->mapWithKeys(fn (int $ano): array => [$ano => (string) $ano])->all())
                        ->default(now()->year + (now()->month >= 9 ? 1 : 0))
                        ->native(false)
                        ->required(),
                    TextInput::make('nome')
                        ->label('Nome')
                        ->placeholder('Ex.: Orçamento 2027')
                        ->required()
                        ->maxLength(120),
                    Select::make('base_id')
                        ->label('Copiar de')
                        ->options(fn (): array => Orcamento::query()->orderByDesc('ano')->orderByDesc('versao')->get()->mapWithKeys(fn (Orcamento $o): array => [$o->id => "{$o->ano} · {$o->rotulo()}"])->all())
                        ->placeholder('Em branco')
                        ->native(false),
                    TextInput::make('reajuste')
                        ->label('Reajuste sobre o copiado (%)')
                        ->numeric()
                        ->default(0)
                        ->minValue(-100)
                        ->maxValue(1000)
                        ->helperText('Ex.: 5 aumenta todos os valores copiados em 5%.'),
                ]),
        ]);
    }
}
