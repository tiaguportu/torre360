<?php

namespace App\Filament\Imports;

use App\Models\Pessoa;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class PessoaImporter extends Importer
{
    protected static ?string $model = Pessoa::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('id')
                ->label('ID')
                ->numeric(),
            ImportColumn::make('nome')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('data_nascimento')
                ->rules(['date']),
            ImportColumn::make('cpf')
                ->rules(['max:14']),
            ImportColumn::make('email')
                ->rules(['email', 'max:255']),
            ImportColumn::make('telefone')
                ->rules(['max:20']),
            ImportColumn::make('sexo_id')
                ->numeric()
                ->rules(['integer', 'exists:sexo,id']),
            ImportColumn::make('cor_raca_id')
                ->numeric()
                ->rules(['integer', 'exists:cor_raca,id']),
            ImportColumn::make('nacionalidade_id')
                ->numeric()
                ->rules(['integer', 'exists:pais,id']),
            ImportColumn::make('naturalidade_id')
                ->numeric()
                ->rules(['integer', 'exists:cidade,id']),
            ImportColumn::make('endereco_id')
                ->numeric()
                ->rules(['integer', 'exists:endereco,id']),
        ];
    }

    public function resolveRecord(): Pessoa
    {
        if ($this->data['id'] ?? null) {
            return Pessoa::firstOrNew([
                'id' => $this->data['id'],
            ]);
        }

        return new Pessoa;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'A importação de pessoas foi concluída e '.Number::format($import->successful_rows).' '.str('linha')->plural($import->successful_rows).' foram importadas.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao importar.';
        }

        return $body;
    }
}
