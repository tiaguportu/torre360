<?php

declare(strict_types=1);

namespace App\Filament\Resources\CancelamentosMatricula\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\CancelamentosMatricula\CancelamentoMatriculaResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCancelamentosMatricula extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = CancelamentoMatriculaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Registrar pedido'),
            $this->ajudaAction('Cancelamento de Matrícula', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('📄', 'Cancelamento de Matrícula', 'Pedidos de cancelamento, o destino de cada fatura e o distrato.')
            ->passos('🔁 Como o pedido anda', [
                'A família pede pelo Portal (ou você registra aqui, se ela avisou por telefone ou pessoalmente).',
                'Abra "Situação financeira" para ver o que a família deve (com multa e juros até hoje) e o que já pagou adiantado.',
                'Clique em "Aprovar" e decida o destino de cada fatura: manter, cancelar ou devolver o que foi pago. Informe a multa rescisória só se o contrato a previr.',
                'Imprima o "Distrato (PDF)", colha a assinatura da família e anexe a cópia assinada.',
                'A partir da data de saída, clique em "Concluir": a matrícula é cancelada e as faturas marcadas também.',
                'Se houver dinheiro a devolver, devolva à família e registre a data em "Registrar devolução".',
            ])
            ->secao('🎯 O que o sistema sugere para cada fatura', [
                ['✅', 'Manter', 'A entrada e tudo o que vence até o fim do mês da saída. O mês da saída é cobrado inteiro.'],
                ['🚫', 'Cancelar', 'O que vence depois do mês da saída e ainda não foi pago.'],
                ['↩️', 'Devolver', 'O que vence depois do mês da saída e já foi pago adiantado.'],
            ])
            ->secao('📌 Bom saber', [
                ['📑', 'Declaração de Transferência', $user?->can('Create:CancelamentoMatricula') ? 'Marque a opção ao aprovar para abrir a saída em Transferências Escolares. A declaração sai mesmo com débitos: a lei não permite reter esse documento.' : 'A Declaração de Transferência sai mesmo com débitos: a lei não permite reter esse documento.'],
                ['🔒', 'Motivo e dados', 'O detalhe que a família escreve fica criptografado e não aparece no histórico de alterações.'],
                ['↩️', 'Encerrar pedido', 'Use quando a família desistiu. A matrícula e as faturas não mudam.'],
            ])
            ->alerta('Multa por rescisão só vale se o contrato a previr e não pode ser abusiva (Código de Defesa do Consumidor, art. 51, IV). Em dúvida, consulte o jurídico da escola antes de cobrar.')
            ->dica('Se a rematrícula do próximo ano ainda não tinha turma nem contrato, ela é cancelada junto. Se já foi efetivada, o sistema só avisa: o contrato dela é outro.');
    }
}
