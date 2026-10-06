<?php

namespace App\Filament\Resources\PropostaComercials\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PropostaComercials\PropostaComercialResource;
use App\Models\PropostaComercial;
use App\Services\RevenueManagementService;
use App\Support\HelpContent;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePropostaComercial extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = PropostaComercialResource::class;

    protected function getHeaderActions(): array
    {
        $conteudo = HelpContent::make(
            '🆕',
            'Criar Proposta Comercial',
            'Selecione o lead ou informe os dados da família, escolha a turma e utilize o simulador em tempo real para calcular a mensalidade com o desconto pretendido.'
        )
            ->secao('💡 Dicas de Simulação', [
                ['🟢', 'Até 7.0% de Desconto', 'Aprovação imediata na alçada do consultor comercial.'],
                ['🟡', 'De 7.1% a 15.0%', 'Requer validação da Coordenação Comercial ou Secretaria.'],
                ['🔴', 'Acima de 15.0%', 'Escala para autorização da Diretoria Geral / Mantenedora.'],
            ]);

        return [
            $this->ajudaAction('Criar Proposta Comercial', $conteudo),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $service = app(RevenueManagementService::class);

        $data['codigo'] = PropostaComercial::gerarCodigo();
        $data['solicitado_por_user_id'] = auth()->id();

        // Recalcula os valores consolidados para garantir consistência financeira
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

        /** @var PropostaComercial $proposta */
        $proposta = static::getModel()::make($data);

        return $service->processarCriacao($proposta, auth()->user());
    }
}
