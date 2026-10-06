<div class="space-y-6 text-sm text-gray-800 dark:text-gray-200">
    <!-- Cabeçalho Oficial -->
    <div class="border-b border-gray-200 dark:border-gray-700 pb-4 text-center">
        <h2 class="text-xl font-bold tracking-tight text-primary-600 dark:text-primary-400">
            DEMONSTRATIVO DE VARIAÇÃO DE CUSTOS E FIXAÇÃO DE ANUIDADE ESCOLAR
        </h2>
        <p class="text-xs text-gray-500 uppercase tracking-widest mt-1">
            Em estrito cumprimento à Lei Federal nº 9.870/1999 e Decreto Federal nº 3.274/1999
        </p>
        <div class="mt-3 flex justify-center gap-6 text-xs text-gray-600 dark:text-gray-400">
            <span><strong>Ano Base:</strong> {{ $planilha->ano_base }}</span>
            <span><strong>Ano Letivo Projetado:</strong> {{ $planilha->ano_letivo_destino }}</span>
            <span><strong>Unidade:</strong> {{ $unidadeNome }}</span>
            <span><strong>Segmento:</strong> {{ $cursoNome }}</span>
        </div>
    </div>

    <!-- Tabela Comparativa de Custos -->
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-800/60 text-xs font-semibold text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700">
                    <th class="p-3">Componente de Custo da Instituição</th>
                    <th class="p-3 text-right">Ano Base ({{ $planilha->ano_base }})</th>
                    <th class="p-3 text-center">Variação (%)</th>
                    <th class="p-3 text-right">Ano Projetado ({{ $planilha->ano_letivo_destino }})</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <tr>
                    <td class="p-3 font-medium">1. Pessoal Docente e Administrativo (Folha + Encargos)</td>
                    <td class="p-3 text-right">R$ {{ number_format((float) $planilha->custo_pessoal_base, 2, ',', '.') }}</td>
                    <td class="p-3 text-center text-amber-600 font-semibold">+{{ number_format((float) $planilha->percentual_dissidio_pessoal, 2, ',', '.') }}%</td>
                    <td class="p-3 text-right font-medium">R$ {{ number_format((float) $planilha->custo_pessoal_projetado, 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="p-3 font-medium">2. Custeio Operacional Geral (Manutenção, Insumos, Serviços)</td>
                    <td class="p-3 text-right">R$ {{ number_format((float) $planilha->custo_custeio_base, 2, ',', '.') }}</td>
                    <td class="p-3 text-center text-amber-600 font-semibold">+{{ number_format((float) $planilha->percentual_inflacao_custeio, 2, ',', '.') }}%</td>
                    <td class="p-3 text-right font-medium">R$ {{ number_format((float) $planilha->custo_custeio_projetado, 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="p-3 font-medium">3. Investimentos em Melhorias Pedagógicas & Infraestrutura</td>
                    <td class="p-3 text-right">R$ {{ number_format((float) $planilha->custo_investimento_base, 2, ',', '.') }}</td>
                    <td class="p-3 text-center text-blue-600 font-semibold">Novo Aporte</td>
                    <td class="p-3 text-right font-medium">R$ {{ number_format((float) $planilha->custo_investimento_projetado, 2, ',', '.') }}</td>
                </tr>
                <tr class="bg-primary-50/50 dark:bg-primary-950/20 font-bold text-gray-900 dark:text-white border-t-2 border-primary-500">
                    <td class="p-3">TOTAL GERAL DE CUSTOS ANUAIS</td>
                    <td class="p-3 text-right">R$ {{ number_format((float) $planilha->custo_total_base, 2, ',', '.') }}</td>
                    <td class="p-3 text-center text-primary-600 font-bold">+{{ number_format((float) $planilha->variacao_custo_total_percentual, 2, ',', '.') }}%</td>
                    <td class="p-3 text-right text-primary-600 font-bold">R$ {{ number_format((float) $planilha->custo_total_projetado, 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Indicadores de Reajuste e Alunos -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 p-4 rounded-lg bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-700">
        <div>
            <span class="block text-xs text-gray-500">Alunos no Ano Base</span>
            <span class="text-base font-bold text-gray-900 dark:text-white">{{ $planilha->alunos_base }} alunos</span>
        </div>
        <div>
            <span class="block text-xs text-gray-500">Mensalidade Média Base</span>
            <span class="text-base font-bold text-gray-900 dark:text-white">R$ {{ number_format((float) $planilha->mensalidade_media_base, 2, ',', '.') }}</span>
        </div>
        <div>
            <span class="block text-xs text-gray-500">Reajuste Homologado</span>
            <span class="text-base font-bold text-emerald-600 dark:text-emerald-400">+{{ number_format((float) $planilha->percentual_reajuste_adotado, 2, ',', '.') }}%</span>
        </div>
        <div>
            <span class="block text-xs text-gray-500">Nova Mensalidade Fixada</span>
            <span class="text-base font-bold text-primary-600 dark:text-primary-400">R$ {{ number_format((float) $planilha->mensalidade_projetada, 2, ',', '.') }}</span>
        </div>
    </div>

    <!-- Justificativa Pedagógica e Aprimoramentos -->
    @if(filled($planilha->justificativa_pedagogica))
        <div class="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <h4 class="font-semibold text-xs uppercase tracking-wider text-gray-500 mb-2">
                Justificativa das Inovações e Aprimoramentos Didático-Pedagógicos (Art. 1º, § 3º)
            </h4>
            <p class="text-xs leading-relaxed text-gray-700 dark:text-gray-300 whitespace-pre-line">
                {{ $planilha->justificativa_pedagogica }}
            </p>
        </div>
    @endif

    <!-- Rodapé Jurídico de Publicação -->
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4 text-xs text-gray-500 flex flex-col md:flex-row justify-between items-center gap-2">
        <span>Data da Fixação e Publicação Legal: <strong>{{ $dataAfixacaoFmt }}</strong> (Respeitado o prazo de 45 dias)</span>
        <span>Status da Planilha: <strong class="uppercase text-primary-600">{{ $planilha->status->getLabel() }}</strong></span>
    </div>
</div>
