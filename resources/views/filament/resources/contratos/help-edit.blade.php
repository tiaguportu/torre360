<div class="space-y-4">
    <p>Esta página permite editar as informações de um contrato de matrícula, gerenciar o faturamento e iniciar o processo de assinatura digital.</p>

    <h3 class="text-lg font-bold mt-4">⚙️ Funcionalidades Disponíveis</h3>
    <ul class="list-disc ml-6 space-y-1">
        <li><strong>Gerar / Regenerar Faturas:</strong> Permite gerar automaticamente ou recalcular o parcelamento do contrato (entrada + parcelas). Faturas protegidas (com pagamentos/transações, cobrança emitida no gateway ou status pago/parcial) impedem a regeneração automática por segurança financeira, devendo ser ajustadas manualmente. Se o valor total do contrato for modificado após faturamento emitido, um alerta é registrado no log de auditoria.</li>
        <li><strong>Visualizar Contrato:</strong> Abre a página de visualização do contrato, onde é possível assinar digitalmente via integração com a Assinafy ou baixar o documento PDF.</li>
        <li><strong>Notificações em Tempo Real:</strong> Quando todas as partes concluírem as assinaturas digitais na Assinafy, a equipe da secretaria e gestores recebem uma notificação em tempo real no painel administrativo com atalho direto para o contrato.</li>
        <li><strong>Importar Faturas:</strong> Permite carregar cobranças em lote via planilha (CSV/XLSX) diretamente para este contrato. Reimportar uma planilha exportada sem edições é um *no-op* total e seguro (não altera faturas nem reseta assinaturas). Faturas protegidas não podem ser modificadas por importação.</li>
        <li><strong>Exportar Faturas:</strong> Gera planilha (CSV/XLSX) com todas as faturas deste contrato, detalhando aluno, vencimento, itens, descontos, status e saldo restante.</li>
        <li><strong>Exclusão de Faturas:</strong> Permite remover faturas pendentes avulsas deste contrato. Faturas protegidas (com pagamentos/transações, cobrança emitida no gateway ou status pago/parcial) são bloqueadas contra exclusão individual para preservação do histórico contábil.</li>
    </ul>

    <hr class="my-4">
    <h3 class="text-lg font-bold">🛡️ Permissões e Acesso</h3>
    <ul class="list-disc ml-6 space-y-1">
        @if ($user->can('Update:Contrato'))
            <li>✅ Você tem permissão para editar os dados básicos do contrato.</li>
        @endif
        @if ($user->can('Delete:Contrato'))
            <li>✅ Você tem permissão para excluir este contrato do sistema.</li>
        @endif
        @if ($user->can('Import:Fatura'))
            <li>✅ Você tem permissão para importar faturas na planilha deste contrato.</li>
        @endif
        @if ($user->can('Export:Fatura'))
            <li>✅ Você tem permissão para exportar as faturas deste contrato.</li>
        @endif
    </ul>
</div>
