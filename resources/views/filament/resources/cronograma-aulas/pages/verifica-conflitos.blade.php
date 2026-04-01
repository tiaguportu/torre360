<x-filament-panels::page>
    <style>
        .icon-container svg {
            width: 1.5rem !important;
            height: 1.5rem !important;
            display: inline-block;
        }
    </style>

    <div class="space-y-6">
        <!-- Resumo Simples (Widgets Nativos do Filament) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="fi-section p-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 flex flex-col justify-center">
                <div class="flex items-center gap-3 text-gray-500 mb-2 icon-container">
                    <x-heroicon-m-shield-exclamation />
                    <span class="text-xs font-bold uppercase tracking-wider">Total de Alertas</span>
                </div>
                <div class="text-3xl font-black text-gray-950">{{ $this->totalAlerts }}</div>
            </div>

            <div class="fi-section p-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 border-l-4 border-danger-500 flex flex-col justify-center">
                <div class="flex items-center gap-3 text-danger-600 mb-2 icon-container">
                    <x-heroicon-m-users />
                    <span class="text-xs font-bold uppercase tracking-wider">Conflitos de Turma</span>
                </div>
                <div class="text-3xl font-black text-gray-950">{{ $this->turmaConflicts }}</div>
            </div>

            <div class="fi-section p-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 border-l-4 border-warning-500 flex flex-col justify-center">
                <div class="flex items-center gap-3 text-warning-600 mb-2 icon-container">
                    <x-heroicon-m-academic-cap />
                    <span class="text-xs font-bold uppercase tracking-wider">Conflitos de Professor</span>
                </div>
                <div class="text-3xl font-black text-gray-950">{{ $this->profConflicts }}</div>
            </div>
        </div>

        <!-- Auditoria por Turma -->
        <x-filament::section icon="heroicon-m-users" icon-color="danger">
            <x-slot name="heading">Auditoria por Turma</x-slot>
            <x-slot name="description">Horários que se sobrepõem no mesmo grupo de alunos</x-slot>

            <div class="overflow-hidden rounded-xl ring-1 ring-gray-950/5 mt-4">
                <table class="w-full text-left bg-white border-separate border-spacing-0">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-xs font-black uppercase text-gray-500 tracking-widest border-b border-gray-100">Cronograma</th>
                            <th class="px-6 py-4 text-xs font-black uppercase text-gray-500 tracking-widest border-b border-gray-100">Confronto de Horário</th>
                            <th class="px-6 py-4 text-xs font-black uppercase text-gray-500 tracking-widest border-b border-gray-100 text-right">Gerenciar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($this->getConflicts() as $conflict)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-5 align-top">
                                    <div class="text-[10px] font-black text-primary-600 mb-1">{{ date('d/m/Y', strtotime($conflict->data)) }}</div>
                                    <div class="font-bold text-gray-900">{{ $conflict->turma_nome }}</div>
                                </td>
                                <td class="px-6 py-5 align-top">
                                    <div class="flex flex-col gap-4">
                                        <div class="p-3 bg-gray-50/50 rounded-lg border-l-4 border-gray-200">
                                            <div class="text-[9px] font-black text-gray-400 uppercase mb-1">Aula Principal</div>
                                            <div class="text-sm font-bold text-gray-900">{{ $conflict->a_inicio }} - {{ $conflict->a_fim }}</div>
                                            <div class="text-xs text-gray-500">{{ $conflict->a_disciplina }}</div>
                                        </div>
                                        <div class="p-3 bg-red-50/50 rounded-lg border-l-4 border-danger-500">
                                            <div class="text-[9px] font-black text-danger-600 uppercase mb-1">Aula em Conflito</div>
                                            <div class="text-sm font-bold text-gray-900">{{ $conflict->b_inicio }} - {{ $conflict->b_fim }}</div>
                                            <div class="text-xs text-gray-500">{{ $conflict->b_disciplina }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5 align-top text-right">
                                    <div class="flex flex-col gap-[3.25rem] items-end pt-1">
                                        <div class="flex items-center gap-3 bg-white border border-gray-100 p-1.5 px-3 rounded-lg shadow-sm">
                                            <span class="text-[9px] font-bold text-gray-400 uppercase">Aula A</span>
                                            <a href="{{ \App\Filament\Resources\CronogramaAulas\CronogramaAulaResource::getUrl('edit', ['record' => $conflict->a_id]) }}" class="text-primary-600 hover:text-primary-800 text-xs font-bold underline decoration-primary-200 underline-offset-4">Editar</a>
                                            <button 
                                                wire:click="deleteRecord({{ $conflict->a_id }})" 
                                                wire:confirm="CUIDADO! Esta aula será excluída permanentemente. Deseja continuar?"
                                                class="text-danger-500 hover:text-danger-700 text-xs font-bold"
                                            >Excluir</button>
                                        </div>
                                        <div class="flex items-center gap-3 bg-white border border-gray-100 p-1.5 px-3 rounded-lg shadow-sm">
                                            <span class="text-[9px] font-bold text-gray-400 uppercase">Aula B</span>
                                            <a href="{{ \App\Filament\Resources\CronogramaAulas\CronogramaAulaResource::getUrl('edit', ['record' => $conflict->b_id]) }}" class="text-primary-600 hover:text-primary-800 text-xs font-bold underline decoration-primary-200 underline-offset-4">Editar</a>
                                            <button 
                                                wire:click="deleteRecord({{ $conflict->b_id }})" 
                                                wire:confirm="CUIDADO! Esta aula será excluída permanentemente. Deseja continuar?"
                                                class="text-danger-500 hover:text-danger-700 text-xs font-bold"
                                            >Excluir</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-20 text-center text-gray-400 italic bg-gray-50/20">Sem conflitos pendentes para turmas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <!-- Auditoria por Professor -->
        <x-filament::section icon="heroicon-m-academic-cap" icon-color="warning">
            <x-slot name="heading">Auditoria por Professor</x-slot>
            <x-slot name="description">Disponibilidade do docente para o mesmo período</x-slot>

            <div class="overflow-hidden rounded-xl ring-1 ring-gray-950/5 mt-4">
                <table class="w-full text-left bg-white border-separate border-spacing-0">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-xs font-black uppercase text-gray-500 tracking-widest border-b border-gray-100">Docente</th>
                            <th class="px-6 py-4 text-xs font-black uppercase text-gray-500 tracking-widest border-b border-gray-100">Sobreposição</th>
                            <th class="px-6 py-4 text-xs font-black uppercase text-gray-500 tracking-widest border-b border-gray-100 text-right">Gerenciar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($this->getProfessorConflicts() as $conflict)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-5 align-top">
                                    <div class="text-[10px] font-black text-warning-600 mb-1">{{ date('d/m/Y', strtotime($conflict->data)) }}</div>
                                    <div class="font-extrabold text-gray-950 tracking-tight">{{ $conflict->professor_nome }}</div>
                                </td>
                                <td class="px-6 py-5 align-top">
                                    <div class="flex flex-col gap-4">
                                        <div class="p-3 bg-gray-50 rounded-lg border-l-4 border-gray-300">
                                            <div class="text-[9px] font-black text-gray-400 uppercase mb-1">Local A</div>
                                            <div class="text-sm font-bold text-gray-900">{{ $conflict->a_inicio }} - {{ $conflict->a_fim }}</div>
                                            <div class="text-[10px] font-bold text-primary-600 uppercase">{{ $conflict->a_turma }}</div>
                                            <div class="text-xs text-gray-500">{{ $conflict->a_disciplina }}</div>
                                        </div>
                                        <div class="p-3 bg-amber-50 rounded-lg border-l-4 border-warning-500">
                                            <div class="text-[9px] font-black text-warning-600 uppercase mb-1">Local B</div>
                                            <div class="text-sm font-bold text-gray-900">{{ $conflict->b_inicio }} - {{ $conflict->b_fim }}</div>
                                            <div class="text-[10px] font-bold text-primary-600 uppercase">{{ $conflict->b_turma }}</div>
                                            <div class="text-xs text-gray-500">{{ $conflict->b_disciplina }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5 align-top text-right">
                                    <div class="flex flex-col gap-[4rem] items-end pt-1">
                                        <div class="flex items-center gap-3 bg-white border border-gray-100 p-1.5 px-3 rounded-lg shadow-sm">
                                            <span class="text-[9px] font-bold text-gray-400 uppercase">A</span>
                                            <a href="{{ \App\Filament\Resources\CronogramaAulas\CronogramaAulaResource::getUrl('edit', ['record' => $conflict->a_id]) }}" class="text-primary-600 hover:text-primary-800 text-xs font-bold underline decoration-primary-200 underline-offset-4">Editar</a>
                                            <button 
                                                wire:click="deleteRecord({{ $conflict->a_id }})" 
                                                wire:confirm="CUIDADO! Esta aula será excluída permanentemente. Deseja continuar?"
                                                class="text-danger-500 hover:text-danger-700 text-xs font-bold"
                                            >Excluir</button>
                                        </div>
                                        <div class="flex items-center gap-3 bg-white border border-gray-100 p-1.5 px-3 rounded-lg shadow-sm">
                                            <span class="text-[9px] font-bold text-gray-400 uppercase">B</span>
                                            <a href="{{ \App\Filament\Resources\CronogramaAulas\CronogramaAulaResource::getUrl('edit', ['record' => $conflict->b_id]) }}" class="text-primary-600 hover:text-primary-800 text-xs font-bold underline decoration-primary-200 underline-offset-4">Editar</a>
                                            <button 
                                                wire:click="deleteRecord({{ $conflict->b_id }})" 
                                                wire:confirm="CUIDADO! Esta aula será excluída permanentemente. Deseja continuar?"
                                                class="text-danger-500 hover:text-danger-700 text-xs font-bold"
                                            >Excluir</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-20 text-center text-gray-400 italic bg-gray-50/20">Sem conflitos detectados para o corpo docente.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
