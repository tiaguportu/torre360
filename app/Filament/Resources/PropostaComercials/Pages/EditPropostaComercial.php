<?php

namespace App\Filament\Resources\PropostaComercials\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PropostaComercials\PropostaComercialResource;
use App\Services\RevenueManagementService;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPropostaComercial extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = PropostaComercialResource::class;

    protected function getHeaderActions(): array
    {
        $conteudo = HelpContent::make(
            '✏️',
            'Editar Proposta Comercial',
            'Ajuste condições comerciais ou altere a validade da proposta antes do fechamento.'
        );

        return [
            DeleteAction::make(),
            $this->ajudaAction('Editar Proposta Comercial', $conteudo),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $service = app(RevenueManagementService::class);

        $calc = $service->calcularValores(
            (float) ($data['valor_tabela_mensal'] ?? 0),
            (string) ($data['tipo_desconto'] ?? 'percentual'),
            (float) ($data['desconto_solicitado'] ?? 0),
            (int) ($data['quantidade_alunos'] ?? 1),
            (int) ($data['quantidade_parcelas'] ?? 12)
        );

        $data['valor_desconto_mensal'] = $calc['valor_desconto_mensal'];
        $data['valor_liquido_mensal'] = $calc['valor_liquido_mensal'];
        $data['valor_total_anual'] = $calc['valor_total_anual'];
        $data['nivel_alcada_necessario'] = $calc['nivel_alcada']->value;

        $record->update($data);

        return $record;
    }
}
