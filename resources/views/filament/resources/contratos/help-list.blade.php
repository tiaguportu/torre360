@php
    $canCreate = $user->can('Create:Contrato');
    $canUpdate = $user->can('Update:Contrato');
    $canImport = $user->can('import', \App\Models\Contrato::class);
    $canExport = $user->can('export', \App\Models\Contrato::class);
@endphp

<p>Aqui você gerencia os contratos financeiros vinculados às matrículas.</p>
<h3>O que você pode fazer?</h3>
<ul>
    <li><strong>Visualizar:</strong> Acompanhe o status de assinatura e valores totais dos contratos.</li>

    @if ($canCreate)
        <li><strong>Gerar Contrato:</strong> Normalmente os contratos são gerados a partir da matrícula, mas podem ser criados manualmente aqui se necessário.</li>
    @endif

    @if ($canUpdate)
        <li><strong>Editar:</strong> Ajuste valores, adicione responsáveis financeiros e registre a data de aceite.</li>
    @endif

    @if ($canImport)
        <li><strong>Importar Contratos:</strong> Permite importar contratos em lote via planilha. Ao importar com ID que já existe, os dados são atualizados; ao importar com ID que não existe, um novo contrato é criado. É possível indicar os IDs das tabelas relacionadas (Matrícula e Template) ou seus nomes equivalentes para associação automática.</li>
    @endif

    @if ($canExport)
        <li><strong>Exportar Contratos:</strong> Permite exportar a listagem de contratos atual para uma planilha de dados.</li>
    @endif

    <li><strong>Faturas:</strong> Os contratos são a base para a geração automática das faturas mensais.</li>
</ul>

<h3>Coluna "Assinatura" (assinatura digital)</h3>
<ul>
    <li><strong>Não enviado:</strong> o contrato foi criado, mas ainda não foi enviado para assinatura.</li>
    <li><strong>Pendente:</strong> enviado ao Assinafy, aguardando as assinaturas.</li>
    <li><strong>Todos assinaram:</strong> todas as assinaturas foram coletadas. É a primeira etapa depois da última assinatura.</li>
    <li><strong>Certificando:</strong> o certificado digital do documento está sendo gerado.</li>
    <li><strong>Certificado:</strong> etapa final, com o certificado gerado e o documento assinado pronto para download.</li>
    <li><strong>Recusado, Cancelado, Expirado e Erro no envio:</strong> o processo foi interrompido; o contrato precisa ser enviado novamente.</li>
</ul>
<p><strong>Todos assinaram</strong>, <strong>Certificando</strong> e <strong>Certificado</strong> contam como contrato <strong>assinado</strong>: a ação passa a ser "Ver Contrato Assinado", o contrato sai da lista de pendentes e a matrícula deixa de ter a pendência "Contrato não assinado".</p>

<h3>Data de aceite e atualização do status</h3>
<ul>
    <li><strong>Data de aceite:</strong> é o dia em que a <strong>última assinatura</strong> foi coletada (quando todos assinaram), e não o dia em que o contrato foi criado.</li>
    <li><strong>Atualização automática:</strong> o Assinafy avisa o sistema a cada assinatura e o sistema também o consulta de hora em hora para recuperar avisos que não chegaram.</li>
    <li><strong>Sincronizar Assinaturas:</strong> use esta ação na linha do contrato para consultar o Assinafy na hora e atualizar o status e quem já assinou.</li>
</ul>
