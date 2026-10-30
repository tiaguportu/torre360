<?php

declare(strict_types=1);

namespace App\Filament\Resources\AtestadosAluno\Schemas;

use App\Enums\SituacaoMatricula;
use App\Models\Matricula;
use App\Services\AtestadoAlunoService;
use App\Support\TiposArquivo;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Registro do atestado pela secretaria. Passa por {@see AtestadoAlunoService::registrar()}, que guarda o comprovante em
 * área privada e abona as faltas já lançadas no período.
 */
class AtestadoAlunoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Atestado')
                    ->description('Anexe o comprovante e informe o período de afastamento. As faltas do aluno nesse período viram justificadas; as que ainda vão ser lançadas também. Se o atestado também abona as faltas, marque o abono.')
                    ->columns(2)
                    ->schema([
                        Select::make('matricula_id')
                            ->label('Aluno (matrícula ativa)')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => self::matriculasAtivas($search))
                            ->getOptionLabelUsing(fn ($value): ?string => self::rotulo(Matricula::query()->with(['pessoa', 'turma'])->find($value)))
                            ->required()
                            ->columnSpanFull(),
                        DatePicker::make('data_inicio')
                            ->label('Afastado a partir de')
                            ->native(false)
                            ->default(today())
                            ->live()
                            ->required(),
                        DatePicker::make('data_fim')
                            ->label('Até')
                            ->helperText('Último dia do afastamento (pode ser o mesmo dia). Máximo de '.AtestadoAlunoService::PERIODO_MAXIMO_DIAS.' dias.')
                            ->native(false)
                            ->default(today())
                            ->afterOrEqual('data_inicio')
                            ->required(),
                        FileUpload::make('comprovante')
                            ->label('Comprovante (PDF ou foto)')
                            ->acceptedFileTypes(TiposArquivo::documentos())
                            ->storeFiles(false)
                            ->maxSize(10240)
                            ->required()
                            ->helperText('Fica guardado em área privada: só a secretaria e a coordenação abrem.')
                            ->columnSpanFull(),
                        Toggle::make('abona_faltas')
                            ->label('Registrar também como abono de falta')
                            ->helperText('Desmarcado, as faltas ficam só justificadas e continuam contando na frequência. Marcado, ficam justificadas e abonadas: deixam de contar como falta (boletim, Portal da Família, Censo e risco de evasão). O abono é registrado no atestado e em cada falta.')
                            ->default(false)
                            ->columnSpanFull(),
                        Textarea::make('observacao')
                            ->label('Observação (opcional)')
                            ->helperText('Fica guardada criptografada. Não é preciso informar diagnóstico nem CID.')
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function matriculasAtivas(string $busca): array
    {
        return Matricula::query()
            ->where('situacao', SituacaoMatricula::ATIVA->value)
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
