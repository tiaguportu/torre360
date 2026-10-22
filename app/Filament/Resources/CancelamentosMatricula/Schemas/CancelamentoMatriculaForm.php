<?php

declare(strict_types=1);

namespace App\Filament\Resources\CancelamentosMatricula\Schemas;

use App\Enums\MotivoCancelamentoMatricula;
use App\Models\CancelamentoMatricula;
use App\Models\Matricula;
use App\Services\CancelamentoMatriculaService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Registro do pedido pela secretaria (quando a família avisou por telefone ou pessoalmente). A família usa a página do
 * Portal; as duas passam por {@see CancelamentoMatriculaService::solicitar()}.
 */
class CancelamentoMatriculaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pedido de cancelamento')
                    ->description('Registre o pedido da família. A secretaria confere a situação financeira e aprova na lista, depois de salvar.')
                    ->columns(2)
                    ->schema([
                        Select::make('matricula_id')
                            ->label('Matrícula / Aluno')
                            ->helperText('Só aparecem matrículas que ainda podem ser canceladas e não têm pedido em andamento.')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => self::matriculasCancelaveis($search))
                            ->getOptionLabelUsing(fn ($value): ?string => self::rotulo(Matricula::query()->with('pessoa')->find($value)))
                            ->required()
                            ->columnSpanFull(),
                        Select::make('motivo')
                            ->label('Motivo')
                            ->options(MotivoCancelamentoMatricula::class)
                            ->required()
                            ->live(),
                        DatePicker::make('data_efetiva')
                            ->label('Data de saída')
                            ->helperText('A partir de quando o aluno deixa a escola. O mês da saída é cobrado inteiro.')
                            ->native(false)
                            ->default(today())
                            ->required(),
                        Textarea::make('motivo_detalhe')
                            ->label('Detalhes (opcional)')
                            ->helperText('Fica guardado criptografado. Escreva só o necessário.')
                            ->rows(2)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        Toggle::make('gerar_transferencia')
                            ->label('Emitir a Declaração de Transferência ao concluir')
                            ->helperText('Abre o processo de saída em Transferências Escolares. A declaração não depende de a família estar em dia.')
                            ->default(fn (Get $get): bool => $get('motivo') === MotivoCancelamentoMatricula::MudancaEscola->value)
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('escola_destino')
                            ->label('Escola de destino')
                            ->maxLength(160)
                            ->visible(fn (Get $get): bool => (bool) $get('gerar_transferencia'))
                            ->columnSpanFull(),
                        Textarea::make('observacao')
                            ->label('Observação interna (opcional)')
                            ->rows(2)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function matriculasCancelaveis(string $busca): array
    {
        return Matricula::query()
            ->whereIn('situacao', array_map(fn ($situacao): string => $situacao->value, CancelamentoMatriculaService::SITUACOES_CANCELAVEIS))
            ->whereNotIn('id', CancelamentoMatricula::query()->abertos()->select('matricula_id'))
            ->whereHas('pessoa', fn ($query) => $query->where('nome', 'like', "%{$busca}%"))
            ->with(['pessoa', 'turma'])
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Matricula $matricula): array => [$matricula->id => self::rotulo($matricula)])
            ->all();
    }

    private static function rotulo(?Matricula $matricula): ?string
    {
        return $matricula ? trim(($matricula->pessoa?->nome ?? 'Aluno').' — '.($matricula->turma?->nome ?? 'sem turma')) : null;
    }
}
