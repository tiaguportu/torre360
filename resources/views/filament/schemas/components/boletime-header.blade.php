@php
    $matricula = $getRecord()?->matriculas()?->first();
    $aluno = $matricula?->pessoa;
    $turma = $matricula?->turma;
    $serie = $turma?->serie;
    $curso = $serie?->curso;
@endphp

<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-6 mb-6 font-sans">
    <!-- Cabeçalho Simplificado -->
    <div class="flex flex-col md:flex-row items-center justify-between gap-6 pb-6 mb-6 border-b border-gray-100 dark:border-gray-800">
        <div class="flex flex-col md:flex-row items-center gap-6 text-center md:text-left">
            <div class="space-y-1">
                <h1 class="text-3xl font-black tracking-tighter text-gray-900 dark:text-white uppercase italic">BOLETIM ESCOLAR</h1>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Documento de acompanhamento pedagógico individual</p>
            </div>
        </div>
        <div class="hidden lg:block text-right">
            <div class="px-4 py-2 rounded-xl bg-primary-50 dark:bg-primary-950/30 border border-primary-100 dark:border-primary-900/50">
                <span class="text-[10px] font-black text-primary-600 dark:text-primary-400 uppercase tracking-[3px]">Ano Letivo {{ $turma?->periodoLetivo?->ano ?? now()->year }}</span>
            </div>
        </div>
    </div>

    <!-- Título do Boletim Mobile -->
    <div class="lg:hidden text-center mb-6">
        <h2 class="text-lg font-black text-gray-900 dark:text-white uppercase tracking-widest bg-gray-50 dark:bg-white/5 px-4 py-2 rounded-lg border border-gray-200 dark:border-white/10">
            BOLETIM ESCOLAR
        </h2>
    </div>

    <!-- Grid de Informações do Aluno -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-x-12 gap-y-6">
        <div class="md:col-span-2 lg:col-span-3">
            <label class="text-[10px] font-black text-gray-400 uppercase tracking-[2px] block mb-1">Nome do Aluno(a)</label>
            <p class="text-lg font-black text-gray-900 dark:text-white border-b-2 border-gray-100 dark:border-gray-800 pb-2 leading-tight">{{ $aluno?->nome ?? $getRecord()?->nome }}</p>
        </div>
        <div class="lg:col-span-1">
            <label class="text-[10px] font-black text-gray-400 uppercase tracking-[2px] block mb-1">Matrícula</label>
            <p class="text-sm font-bold text-gray-900 dark:text-white border-b-2 border-gray-100 dark:border-gray-800 pb-2">{{ $matricula?->codigo ?? $matricula?->id ?? 'N/A' }}</p>
        </div>
        <div class="lg:col-span-1">
            <label class="text-[10px] font-black text-gray-400 uppercase tracking-[2px] block mb-1">Turma</label>
            <p class="text-sm font-bold text-gray-900 dark:text-white border-b-2 border-gray-100 dark:border-gray-800 pb-2">{{ $turma?->nome ?? 'N/A' }}</p>
        </div>
        <div class="lg:col-span-1">
            <label class="text-[10px] font-black text-gray-400 uppercase tracking-[2px] block mb-1">Data Emissão</label>
            <p class="text-sm font-bold text-gray-900 dark:text-white border-b-2 border-gray-100 dark:border-gray-800 pb-2">{{ now()->format('d/m/Y') }}</p>
        </div>
        
        <div class="md:col-span-2 lg:col-span-3">
            <label class="text-[10px] font-black text-gray-400 uppercase tracking-[2px] block mb-1">Curso / Nível de Ensino</label>
            <p class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ $curso?->nome ?? 'Não informado' }}</p>
        </div>
        <div class="lg:col-span-3">
            <label class="text-[10px] font-black text-gray-400 uppercase tracking-[2px] block mb-1">Período Letivo</label>
            <p class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ $turma?->periodoLetivo?->ano ?? now()->year }}</p>
        </div>
    </div>
</div>

