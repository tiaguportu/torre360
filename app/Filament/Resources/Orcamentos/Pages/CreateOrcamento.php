<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orcamentos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Orcamentos\OrcamentoResource;
use App\Models\Orcamento;
use App\Services\OrcamentoService;
use App\Support\HelpContent;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOrcamento extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = OrcamentoResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $base = filled($data['base_id'] ?? null) ? Orcamento::query()->find($data['base_id']) : null;

        return app(OrcamentoService::class)->criar((int) $data['ano'], (string) $data['nome'], $base, (float) ($data['reajuste'] ?? 0), auth()->user());
    }

    protected function getRedirectUrl(): string
    {
        return OrcamentoResource::getUrl('planilha', ['record' => $this->getRecord()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Novo orçamento', HelpContent::make('🏦', 'Novo orçamento', 'Escolha o ano e, se quiser, copie outro orçamento.')
                ->secao('🎯 Como preencher', [
                    ['📅', 'Ano e nome', 'Cada ano tem versões: a primeira é a v1; revisões do aprovado viram v2, v3...'],
                    ['📋', 'Copiar de', 'Traz as linhas de outro orçamento. O reajuste (%) multiplica todos os valores copiados.'],
                ])
                ->dica('Depois de criar, você cai na planilha para preencher os valores.')),
        ];
    }
}
