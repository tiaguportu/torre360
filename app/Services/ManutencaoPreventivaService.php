<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusBemPatrimonial;
use App\Enums\StatusExecucaoManutencao;
use App\Exceptions\ManutencaoPreventivaException;
use App\Models\ExecucaoManutencao;
use App\Models\OrdemServico;
use App\Models\PlanoManutencao;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Transforma planos recorrentes em ordens de serviço no prazo certo e fecha o ciclo quando a manutenção é feita.
 *
 * Fluxo: o agendador (`manutencao:gerar-ordens`) abre a `OrdemServico` `antecedencia_dias` antes do vencimento e
 * cria a `ExecucaoManutencao` prevista; quando a manutenção é registrada (ou a ordem é concluída na tela de ordens
 * de serviço), a execução é concluída e o plano avança: a próxima data é a da execução somada à periodicidade.
 */
class ManutencaoPreventivaService
{
    /**
     * Abre as ordens dos planos que já entraram na janela de antecedência. Idempotente: roda quantas vezes for
     * preciso no mesmo dia sem duplicar.
     *
     * @return int Quantas ordens foram abertas.
     */
    public function gerarOrdensPendentes(?CarbonInterface $hoje = null): int
    {
        $hoje = ($hoje ?? today())->copy()->startOfDay();
        $abertas = 0;

        $planos = PlanoManutencao::query()->ativos()->with(['bemPatrimonial', 'sala', 'categoriaOs'])->get();

        foreach ($planos as $plano) {
            if ($this->deveGerarOrdem($plano, $hoje)) {
                $this->abrirOrdem($plano);
                $abertas++;
            }
        }

        return $abertas;
    }

    public function deveGerarOrdem(PlanoManutencao $plano, CarbonInterface $hoje): bool
    {
        if (! $plano->ativo || $this->bemBaixado($plano)) {
            return false;
        }

        $abreEm = $plano->proxima_execucao->copy()->startOfDay()->subDays($plano->antecedencia_dias);

        if ($hoje->copy()->startOfDay()->lt($abreEm)) {
            return false;
        }

        return ! $this->existeExecucaoParaVencimento($plano);
    }

    /**
     * Abre a ordem de serviço do próximo vencimento agora, sem esperar a janela de antecedência.
     *
     * @throws ManutencaoPreventivaException
     */
    public function gerarOrdem(PlanoManutencao $plano): ExecucaoManutencao
    {
        if (! $plano->ativo) {
            throw new ManutencaoPreventivaException('Este plano está inativo.');
        }

        if ($this->bemBaixado($plano)) {
            throw new ManutencaoPreventivaException('O bem deste plano foi baixado do patrimônio.');
        }

        if ($this->existeExecucaoParaVencimento($plano)) {
            throw new ManutencaoPreventivaException('Já existe uma execução para o vencimento de '.$plano->proxima_execucao->format('d/m/Y').'. Registre-a ou reprograme o plano.');
        }

        return $this->abrirOrdem($plano);
    }

    /**
     * Registra a manutenção feita. Conclui a execução prevista (e a ordem de serviço ligada a ela) ou, se não
     * havia ordem aberta, cria a execução já concluída. O plano avança a partir da data da execução.
     *
     * @param  list<array{item?: string, ok?: bool, observacao?: ?string}>  $checklistResultado
     *
     * @throws ManutencaoPreventivaException
     */
    public function registrarExecucao(PlanoManutencao $plano, CarbonInterface $data, array $checklistResultado = [], ?string $observacoes = null, ?User $por = null): ExecucaoManutencao
    {
        if (! $plano->ativo) {
            throw new ManutencaoPreventivaException('Este plano está inativo.');
        }

        if ($data->copy()->startOfDay()->isFuture()) {
            throw new ManutencaoPreventivaException('A data da execução não pode ser futura.');
        }

        return DB::transaction(function () use ($plano, $data, $checklistResultado, $observacoes, $por): ExecucaoManutencao {
            $plano = PlanoManutencao::query()->lockForUpdate()->findOrFail($plano->id);

            $execucao = $plano->execucoes()->where('status', StatusExecucaoManutencao::Prevista->value)->orderBy('data_prevista')->first();

            if (! $execucao) {
                // Sem ordem aberta: a execução é criada já concluída. Se o vencimento atual já tem execução
                // (concluída ou cancelada antes), usa a data da execução para respeitar a unicidade.
                $dataPrevista = $this->existeExecucaoParaVencimento($plano) ? $data->copy()->startOfDay() : $plano->proxima_execucao->copy();

                $execucao = ExecucaoManutencao::create([
                    'plano_manutencao_id' => $plano->id,
                    'data_prevista' => $dataPrevista->toDateString(),
                    'status' => StatusExecucaoManutencao::Prevista,
                ]);
            }

            $execucao->update([
                'status' => StatusExecucaoManutencao::Concluida,
                'concluida_em' => $data->toDateString(),
                'checklist_resultado' => $checklistResultado !== [] ? $checklistResultado : null,
                'observacoes' => $observacoes,
                'registrado_por_user_id' => $por?->id,
            ]);

            $os = $execucao->ordemServico;
            if ($os && in_array($os->status, ['Aberta', 'Em Andamento'], true)) {
                $os->update(['status' => 'Concluída', 'percentual_conclusao' => 100]);
            }

            $plano->update([
                'ultima_execucao' => $data->toDateString(),
                'proxima_execucao' => $data->copy()->startOfDay()->addDays($plano->periodicidade_dias)->toDateString(),
            ]);

            return $execucao->refresh();
        });
    }

    /**
     * Move o próximo vencimento. A execução prevista em aberto é cancelada (a ordem de serviço, não: quem a abriu
     * decide o que fazer com ela).
     *
     * @throws ManutencaoPreventivaException
     */
    public function reprogramar(PlanoManutencao $plano, CarbonInterface $novaData): PlanoManutencao
    {
        if ($novaData->copy()->startOfDay()->lt(today())) {
            throw new ManutencaoPreventivaException('A nova data não pode estar no passado.');
        }

        return DB::transaction(function () use ($plano, $novaData): PlanoManutencao {
            $plano->execucoes()
                ->where('status', StatusExecucaoManutencao::Prevista->value)
                ->update(['status' => StatusExecucaoManutencao::Cancelada->value, 'observacoes' => 'Vencimento reprogramado.']);

            $plano->update(['proxima_execucao' => $novaData->toDateString()]);

            return $plano->refresh();
        });
    }

    /**
     * Reflete na manutenção o que aconteceu nas ordens de serviço: ordem concluída fecha a execução e avança o plano;
     * ordem cancelada cancela a execução (o plano fica vencido até alguém reprogramá-lo).
     *
     * @return int Quantas execuções foram atualizadas.
     */
    public function sincronizarComOrdensDeServico(): int
    {
        $atualizadas = 0;

        $execucoes = ExecucaoManutencao::query()
            ->where('status', StatusExecucaoManutencao::Prevista->value)
            ->whereNotNull('ordem_servico_id')
            ->with(['ordemServico', 'plano'])
            ->get();

        foreach ($execucoes as $execucao) {
            $os = $execucao->ordemServico;

            if (! $os || ! $execucao->plano) {
                continue;
            }

            if ($os->status === 'Concluída') {
                $dataConclusao = $os->updated_at->copy()->startOfDay();
                $dataConclusao = $dataConclusao->isFuture() ? today() : $dataConclusao;

                try {
                    $this->registrarExecucao($execucao->plano, $dataConclusao, [], 'Concluída pela ordem de serviço #'.$os->id.'.');
                    $atualizadas++;
                } catch (ManutencaoPreventivaException) {
                    // Plano desativado depois da abertura da ordem: a execução fica como está, sem derrubar o agendador.
                    continue;
                }
            } elseif ($os->status === 'Cancelada') {
                $execucao->update(['status' => StatusExecucaoManutencao::Cancelada, 'observacoes' => 'Ordem de serviço #'.$os->id.' cancelada.']);
                $atualizadas++;
            }
        }

        return $atualizadas;
    }

    private function existeExecucaoParaVencimento(PlanoManutencao $plano): bool
    {
        return $plano->execucoes()->whereDate('data_prevista', $plano->proxima_execucao->toDateString())->exists();
    }

    private function bemBaixado(PlanoManutencao $plano): bool
    {
        return $plano->bemPatrimonial?->status === StatusBemPatrimonial::Baixado;
    }

    private function abrirOrdem(PlanoManutencao $plano): ExecucaoManutencao
    {
        return DB::transaction(function () use ($plano): ExecucaoManutencao {
            $ordem = OrdemServico::create([
                'titulo' => 'Manutenção preventiva: '.$plano->titulo,
                'descricao' => $this->descricaoDaOrdem($plano),
                'prazo_conclusao' => $plano->proxima_execucao->toDateString(),
                'prioridade' => $plano->prioridade,
                'status' => 'Aberta',
                'percentual_conclusao' => 0,
            ]);

            if ($plano->categoria_os_id) {
                $ordem->categorias()->attach($plano->categoria_os_id);
            }

            return ExecucaoManutencao::create([
                'plano_manutencao_id' => $plano->id,
                'ordem_servico_id' => $ordem->id,
                'data_prevista' => $plano->proxima_execucao->toDateString(),
                'status' => StatusExecucaoManutencao::Prevista,
            ]);
        });
    }

    private function descricaoDaOrdem(PlanoManutencao $plano): string
    {
        $linhas = [
            'Gerada automaticamente pelo plano de manutenção preventiva.',
            'Alvo: '.$plano->alvoDescricao(),
            "Periodicidade: a cada {$plano->periodicidade_dias} dias.",
            'Vencimento: '.$plano->proxima_execucao->format('d/m/Y').'.',
        ];

        if (filled($plano->descricao)) {
            $linhas[] = '';
            $linhas[] = (string) $plano->descricao;
        }

        $checklist = array_values(array_filter((array) $plano->checklist, fn ($item): bool => filled($item)));
        if ($checklist !== []) {
            $linhas[] = '';
            $linhas[] = 'Checklist:';
            foreach ($checklist as $item) {
                $linhas[] = '- '.(is_array($item) ? ($item['item'] ?? '') : $item);
            }
        }

        return implode("\n", $linhas);
    }
}
