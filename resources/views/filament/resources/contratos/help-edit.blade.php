<div class="space-y-4">
    <p>Esta página permite editar as informações de um contrato de matrícula, gerenciar o faturamento e iniciar o processo de assinatura digital.</p>

    <h3 class="text-lg font-bold mt-4">⚙️ Funcionalidades Disponíveis</h3>
    <ul class="list-disc ml-6 space-y-1">
        <li><strong>Gerar Faturas Automaticamente:</strong> Permite criar o parcelamento do contrato (entrada + parcelas). Esta ação <em>só fica visível se o contrato não possuir nenhuma fatura gerada anteriormente</em>.</li>
        <li><strong>Visualizar Contrato:</strong> Abre a página de visualização do contrato, onde é possível assinar digitalmente via integração com a Assinafy ou baixar o documento PDF.</li>
        <li><strong>Importar Faturas:</strong> Permite carregar cobranças em lote via planilha (CSV/XLSX) diretamente para este contrato, com validação de datas, valores, tipos de desconto e salvaguardas financeiras.</li>
        <li><strong>Exportar Faturas:</strong> Gera planilha (CSV/XLSX) com todas as faturas deste contrato, detalhando aluno, vencimento, itens, descontos, status e saldo restante.</li>
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
