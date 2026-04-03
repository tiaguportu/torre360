@php
    $matricula = $getRecord();
    $aluno = $matricula->pessoa;
@endphp
<div class="flex flex-col md:flex-row items-center gap-6 px-4 mb-4 mt-2">
    <div class="relative">
        @if($aluno->foto)
            <img 
                src="{{ Storage::url($aluno->foto) }}" 
                alt="{{ $aluno->nome }}"
                class="h-24 w-24 object-cover rounded-2xl border-4 border-white dark:border-gray-800 shadow-xl ring-2 ring-primary-500/20"
            />
        @else
            <div class="h-24 w-24 rounded-2xl bg-primary-500/10 flex items-center justify-center border-4 border-white dark:border-gray-800 shadow-xl ring-2 ring-primary-500/20">
                <x-heroicon-o-user class="h-10 w-10 text-primary-600 dark:text-primary-400" />
            </div>
        @endif
        <div class="absolute -bottom-1 -right-1 h-6 w-6 bg-success-500 border-2 border-white dark:border-gray-800 rounded-full shadow-sm"></div>
    </div>
    
    <div class="flex-1 text-center md:text-left space-y-1">
        <h2 class="text-3xl font-extrabold tracking-tight text-gray-950 dark:text-white">
            {{ $aluno->nome }}
        </h2>
        <div class="flex flex-wrap justify-center md:justify-start items-center gap-x-6 gap-y-2 text-sm font-medium">
            <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400">
                <span>CPF: {{ $aluno->cpf ?? 'Não inf.' }}</span>
            </div>
            <span class="w-1 h-1 bg-gray-300 dark:bg-gray-700 rounded-full hidden sm:inline"></span>
            <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400">
                <span>Nascimento: {{ $aluno->data_nascimento?->format('d/m/Y') ?? 'Não inf.' }}</span>
            </div>
            <span class="w-1 h-1 bg-gray-300 dark:bg-gray-700 rounded-full hidden sm:inline"></span>
            <div class="flex items-center gap-1.5 text-primary-600 dark:text-primary-400">
                <span>Matrícula: {{ $matricula->id }}</span>
            </div>
        </div>
    </div>
</div>
