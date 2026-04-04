@php
    $matricula = $schemaComponent->getMatricula();
    $aluno = $schemaComponent->getAluno();
    $turma = $schemaComponent->getTurma();
    $serie = $schemaComponent->getSerie();
    $curso = $serie?->curso;
@endphp

<div class="mb-8">
    <x-filament::section icon="heroicon-o-identification" class="fi-sc-header-section">
        <x-slot name="heading">Informações da Matrícula</x-slot>
        
        <x-slot name="description">Detalhes cadastrais e acadêmicos do aluno nesta unidade de ensino</x-slot>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-8 gap-x-16">
            <div class="space-y-1">
                <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">Nome do Aluno(a)</p>
                <p class="text-lg font-black text-gray-950 dark:text-white leading-tight uppercase border-b-2 border-gray-100 dark:border-gray-800 pb-1">
                    {{ $aluno?->nome ?? $schemaComponent->getRecord()?->nome }}
                </p>
            </div>
            
            <div class="space-y-1">
                <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">Matrícula / RA</p>
                <p class="text-lg font-black text-gray-950 dark:text-white font-mono uppercase border-b-2 border-gray-100 dark:border-gray-800 pb-1">
                    {{ $matricula?->codigo ?? $matricula?->id ?? 'N/A' }}
                </p>
            </div>

            <div class="space-y-1">
                <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">Turma Atual</p>
                <p class="text-lg font-black text-gray-950 dark:text-white uppercase border-b-2 border-gray-100 dark:border-gray-800 pb-1">
                    {{ $turma?->nome ?? 'Sem Turma' }}
                </p>
            </div>

            <div class="space-y-1">
                <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">Curso / Nível de Ensino</p>
                <p class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase italic">
                    {{ $curso?->nome ?? 'Não informado' }}
                </p>
            </div>

            <div class="space-y-1">
                <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">Ano Pedagógico</p>
                <p class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase italic">
                    {{ $turma?->periodoLetivo?->ano ?? now()->year }}
                </p>
            </div>

            <div class="space-y-1">
                <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">Data Emissão</p>
                <p class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase font-mono italic">
                    {{ now()->format('d/m/Y') }}
                </p>
            </div>
        </div>
    </x-filament::section>
</div>
