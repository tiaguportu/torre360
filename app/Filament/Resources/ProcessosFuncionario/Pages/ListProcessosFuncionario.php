<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcessosFuncionario\Pages;

use App\Enums\TipoProcessoFuncionario;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ProcessosFuncionario\Actions\IniciarProcesso;
use App\Filament\Resources\ProcessosFuncionario\ProcessoFuncionarioResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\ListRecords;

class ListProcessosFuncionario extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = ProcessoFuncionarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            IniciarProcesso::make(TipoProcessoFuncionario::Admissao),
            IniciarProcesso::make(TipoProcessoFuncionario::Desligamento),
            $this->ajudaAction('Admissão e Desligamento', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('🧑‍💼', 'Admissão e Desligamento', 'A lista de providências de quem entra e de quem sai da escola.')
            ->passos('🔁 Como usar', [
                'Cadastre a pessoa em Funcionários e clique em "Iniciar admissão" (ou "Iniciar desligamento") informando a data.',
                'O sistema cria a lista de providências com o prazo de cada uma: documentos, exame médico, contrato e eSocial, acessos, equipamentos, treinamento e financeiro.',
                'Abra o processo e marque cada providência como feita. Você pode anexar o comprovante, ou marcar "não se aplica" explicando o motivo.',
                'Com as providências obrigatórias resolvidas, clique em "Concluir processo".',
            ])
            ->secao('🎯 O que acontece ao concluir', [
                ['👤', 'Admissão', 'Encerra o processo e guarda o histórico. O cadastro do funcionário não muda.'],
                ['🚪', 'Desligamento', 'Só a partir do último dia de trabalho: grava a data de desligamento e retira os acessos de equipe ao sistema.'],
                ['👨‍👩‍👧', 'Pai, mãe ou aluno', 'Quem também é responsável ou aluno da escola continua com a conta, só sem os papéis de equipe.'],
                ['🛡️', 'Super administrador', 'Nunca é alterado sozinho: o sistema avisa para o administrador resolver.'],
            ])
            ->alerta('A lista é um ponto de partida para escola privada com contratação pela CLT. Confira os prazos e as exigências com o contador e o jurídico, e ajuste em config/rh_processos.php.')
            ->dica('O número no menu mostra as providências com prazo vencido em processos em andamento.');
    }
}
