@php
    $matricula = $getRecord();
    $aluno = $matricula->pessoa;
    $turma = $matricula->turma;
    $serie = $turma?->serie;
    $curso = $serie?->curso;
@endphp

<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-6 mb-6 font-sans">
    <!-- Cabeçalho Institucional -->
    <div class="flex flex-col md:flex-row items-center justify-between gap-6 pb-6 mb-6 border-b border-gray-100 dark:border-gray-800">
        <div class="flex flex-col md:flex-row items-center gap-6 text-center md:text-left">
            <img src="{{ asset('logo-dashboard.png') }}" class="h-16 md:h-20 object-contain" alt="Logo">
            <div class="space-y-1">
                <h1 class="text-2xl font-black tracking-tight text-gray-900 dark:text-white uppercase">Escola Torre de Marfim</h1>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Rua Gaspar Magalhães, 361 CEP 21920140 - Rio de Janeiro - RJ</p>
                <p class="text-xs font-bold text-primary-600 dark:text-primary-400 tracking-wider">CNPJ: 56.729.131/0001-69</p>
            </div>
        </div>
        <div class="hidden lg:block text-right">
            <h2 class="text-lg font-black text-gray-900 dark:text-white uppercase tracking-widest bg-gray-50 dark:bg-white/5 px-4 py-2 rounded-lg border border-gray-200 dark:border-white/10">
                BOLETIM ESCOLAR
            </h2>
        </div>
    </div>

    <!-- Título do Boletim Mobile -->
    <div class="lg:hidden text-center mb-6">
        <h2 class="text-lg font-black text-gray-900 dark:text-white uppercase tracking-widest bg-gray-50 dark:bg-white/5 px-4 py-2 rounded-lg border border-gray-200 dark:border-white/10">
            BOLETIM ESCOLAR
        </h2>
    </div>

    <!-- Grid de Informações do Aluno -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-x-8 gap-y-4">
        <div class="lg:col-span-2">
            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-0.5">Aluno(a)</label>
            <p class="text-sm font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-1">{{ $aluno->nome }}</p>
        </div>
        <div>
            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-0.5">Código de Matrícula</label>
            <p class="text-sm font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-1">{{ $matricula->codigo ?? $matricula->id }}</p>
        </div>
        <div>
            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-0.5">Período Letivo</label>
            <p class="text-sm font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-1">{{ $turma?->periodoLetivo?->ano ?? now()->year }}</p>
        </div>
        
        <div class="lg:col-span-2">
            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-0.5">Curso / Nível</label>
            <p class="text-sm font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-1">{{ $curso?->nome ?? 'Não informado' }}</p>
        </div>
        <div>
            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-0.5">Turma</label>
            <p class="text-sm font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-1">{{ $turma?->nome ?? 'N/A' }}</p>
        </div>
        <div>
            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-0.5">Data de Emissão</label>
            <p class="text-sm font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-1">{{ now()->format('d/m/Y') }}</p>
        </div>
    </div>
</div>

