<?php

namespace App\Filament\Resources\AcordoInadimplencias\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\AcordoInadimplencias\AcordoInadimplenciaResource;
use App\Services\AcordoInadimplenciaService;
use App\Support\HelpContent;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAcordoInadimplencia extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = AcordoInadimplenciaResource::class;

    protected function getHeaderActions(): array
    {
        $conteudo = HelpContent::make(
            '🆕',
            'Simular e Registrar Acordo de Inadimplência',
            'Selecione a matrícula inadimplente para puxar o saldo devedor. Ajuste o desconto, defina o número de parcelas e o primeiro vencimento para gerar o plano de pagamento.'
        );

        return [
            $this->ajudaAction('Criar Acordo', $conteudo),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $service = app(AcordoInadimplenciaService::class);

        return $service->criarAcordo($data, auth()->user());
    }
}
