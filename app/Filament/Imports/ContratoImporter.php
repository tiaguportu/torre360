<?php

namespace App\Filament\Imports;

use App\Models\Contrato;
use App\Models\Matricula;
use App\Models\TemplateContrato;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class ContratoImporter extends Importer
{
    protected static ?string $model = Contrato::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('id')
                ->label('ID')
                ->numeric()
                ->rules(['nullable', 'integer']),
            ImportColumn::make('matricula_id')
                ->label('ID da Matrícula')
                ->numeric()
                ->rules(['nullable', 'integer', 'exists:matricula,id']),
            ImportColumn::make('matricula_aluno_nome')
                ->label('Aluno (Nome)')
                ->ignore(true)
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('template_contrato_id')
                ->label('ID do Template')
                ->numeric()
                ->rules(['nullable', 'integer', 'exists:template_contratos,id']),
            ImportColumn::make('template_contrato_nome')
                ->label('Template (Nome)')
                ->ignore(true)
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('valor_total')
                ->label('Valor Total')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'numeric', 'min:0']),
            ImportColumn::make('data_aceite')
                ->label('Data de Aceite')
                ->rules(['nullable', 'date']),
            ImportColumn::make('assinafy_id')
                ->label('Assinafy ID')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('assinafy_status')
                ->label('Assinafy Status')
                ->rules(['nullable', 'string', 'max:255']),
        ];
    }

    public function resolveRecord(): Contrato
    {
        $contrato = null;

        if ($this->data['id'] ?? null) {
            $contrato = Contrato::firstOrNew([
                'id' => (int) $this->data['id'],
            ]);
        } else {
            $contrato = new Contrato;
        }

        // Resolução inteligente da Matrícula por Nome do Aluno
        if (empty($this->data['matricula_id']) && ! empty($this->data['matricula_aluno_nome'])) {
            $alunoNome = trim($this->data['matricula_aluno_nome']);
            $matricula = Matricula::whereHas('pessoa', function ($query) use ($alunoNome) {
                $query->where('nome', 'like', "%{$alunoNome}%");
            })->first();

            if ($matricula) {
                $contrato->matricula_id = $matricula->id;
            }
        }

        // Resolução inteligente do Template por Nome
        if (empty($this->data['template_contrato_id']) && ! empty($this->data['template_contrato_nome'])) {
            $templateNome = trim($this->data['template_contrato_nome']);
            $template = TemplateContrato::where('nome', 'like', "%{$templateNome}%")->first();

            if ($template) {
                $contrato->template_contrato_id = $template->id;
            }
        }

        return $contrato;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'A importação de contratos foi concluída e '.Number::format($import->successful_rows).' '.str('linha')->plural($import->successful_rows).' foram importadas.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao importar.';
        }

        return $body;
    }
}
