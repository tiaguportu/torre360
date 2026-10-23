<?php

declare(strict_types=1);

namespace App\Filament\Resources\CasosFrequencia\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Pages\FaltasExcessivas;
use App\Filament\Resources\CasosFrequencia\CasoFrequenciaResource;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListCasosFrequencia extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = CasoFrequenciaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('apurar')
                ->label('Alunos acima do limite')
                ->icon('heroicon-o-magnifying-glass')
                ->visible(fn (): bool => (bool) auth()->user()?->can('Apurar:CasoFrequencia'))
                ->url(fn (): string => FaltasExcessivas::getUrl()),
            $this->ajudaAction('Casos de Faltas', HelpContent::make('🛡️', 'Casos de Faltas', 'Alunos com faltas acima do limite e o que a escola já fez por cada um.')
                ->passos('🔁 Como o caso anda', [
                    'Em "Alunos acima do limite", a coordenação vê quem passou do limite e abre o caso.',
                    'Registre cada tentativa de contato com a família (telefone, WhatsApp, conversa...). É a prova de que a escola esgotou os recursos.',
                    'Gere o ofício ao Conselho Tutelar, confira e envie. Se quiser, mande para assinatura da direção pelo Assinafy.',
                    'Registre o envio (data e protocolo) e, depois, a resposta do Conselho.',
                    'Encerre o caso quando a frequência se regularizar, o aluno sair ou o Conselho responder.',
                ])
                ->secao('📌 Bom saber', [
                    ['⚖️', 'O limite', 'A LDB manda notificar quando as faltas passam de 30% do percentual permitido: com 75% de frequência mínima, 7,5% das aulas do ano.'],
                    ['📞', 'Contato antes do ofício', 'O sistema só deixa registrar o envio depois de pelo menos um contato com a família.'],
                    ['🔒', 'Dados da criança', 'O que a família diz e a resposta do Conselho ficam criptografados; o ofício leva só o necessário.'],
                ])
                ->alerta('O sistema monta a relação e guarda o histórico; o envio ao Conselho Tutelar é feito pela direção da escola.')),
        ];
    }
}
