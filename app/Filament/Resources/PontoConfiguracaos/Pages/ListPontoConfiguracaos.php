<?php

declare(strict_types=1);

namespace App\Filament\Resources\PontoConfiguracaos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PontoConfiguracaos\PontoConfiguracaoResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPontoConfiguracaos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = PontoConfiguracaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Configurar unidade'),
            $this->ajudaAction('Configuração do Ponto', HelpContent::make('⚙️', 'Configuração do Ponto', 'Define como cada unidade registra o ponto e o que a lei exige.')
                ->secao('📖 Modos', [
                    ['📝', 'Controle interno', 'Registra com segurança, mas não vale como REP. Serve a quem não é obrigado a controlar o ponto (até 20 trabalhadores) ou como controle complementar.'],
                    ['🤝', 'REP-A', 'Registrador alternativo: precisa de acordo ou convenção coletiva vigente (Portaria MTP 671/2021, art. 77).'],
                    ['💻', 'REP-P', 'Registrador via programa: precisa de registro do software no INPI, Atestado Técnico e Termo de Responsabilidade, assinatura ICP-Brasil, hora legal e arquivos oficiais (AFD e AEJ).'],
                ])
                ->alerta('Os modos REP-A e REP-P só são aceitos quando todos os requisitos estão cumpridos. A tela mostra o que falta e como resolver. Parte dos requisitos depende do desenvolvedor do sistema.')
                ->dica('"Verificar integridade" confere a numeração (NSR) e o encadeamento dos registros da unidade. O sistema também faz isso sozinho toda madrugada.')),
        ];
    }
}
