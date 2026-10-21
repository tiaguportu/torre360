<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusAutoavaliacaoDocente;
use App\Exceptions\DesenvolvimentoDocenteException;
use App\Models\AutoavaliacaoDocente;
use App\Models\AutoavaliacaoNota;
use App\Models\CicloAvaliacaoDocente;
use App\Models\Funcionario;
use App\Models\User;
use App\Support\FuncionarioDoUsuario;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida da autoavaliação do professor: iniciar (já com uma linha por critério do instrumento do ciclo),
 * enviar e reabrir. Só o próprio professor inicia e envia; depois de enviada ela não muda (só a administração
 * reabre). A pontuação usa a mesma média ponderada da avaliação da coordenação, para os números serem comparáveis.
 */
class AutoavaliacaoDocenteService
{
    public function __construct(
        private readonly AvaliacaoDocenteService $avaliacoes,
    ) {}

    /**
     * Inicia a autoavaliação do ciclo ou devolve a que já existe (abrir duas vezes não duplica).
     *
     * @throws DesenvolvimentoDocenteException
     */
    public function iniciar(CicloAvaliacaoDocente $ciclo, Funcionario $funcionario, User $por): AutoavaliacaoDocente
    {
        $ciclo->loadMissing('instrumento.criterios');

        if (! FuncionarioDoUsuario::ehOProprio($por, $funcionario)) {
            throw new DesenvolvimentoDocenteException('Você só pode fazer a sua própria autoavaliação.');
        }

        if (! $funcionario->isAtivo()) {
            throw new DesenvolvimentoDocenteException('Funcionário desligado não faz autoavaliação.');
        }

        if (! $ciclo->aceitaObservacaoEm(today())) {
            throw new DesenvolvimentoDocenteException('Este ciclo não está aberto para autoavaliação.');
        }

        if ($ciclo->instrumento->criterios->isEmpty()) {
            throw new DesenvolvimentoDocenteException('O instrumento deste ciclo não tem critérios cadastrados.');
        }

        return DB::transaction(function () use ($ciclo, $funcionario): AutoavaliacaoDocente {
            $existente = AutoavaliacaoDocente::query()
                ->where('ciclo_id', $ciclo->id)
                ->where('funcionario_id', $funcionario->id)
                ->lockForUpdate()
                ->first();

            if ($existente) {
                return $existente;
            }

            $autoavaliacao = AutoavaliacaoDocente::create([
                'ciclo_id' => $ciclo->id,
                'funcionario_id' => $funcionario->id,
                'status' => StatusAutoavaliacaoDocente::Rascunho,
            ]);

            foreach ($ciclo->instrumento->criterios as $criterio) {
                AutoavaliacaoNota::create([
                    'autoavaliacao_id' => $autoavaliacao->id,
                    'criterio_id' => $criterio->id,
                ]);
            }

            return $autoavaliacao;
        });
    }

    /**
     * @throws DesenvolvimentoDocenteException
     */
    public function enviar(AutoavaliacaoDocente $autoavaliacao, User $por): AutoavaliacaoDocente
    {
        return DB::transaction(function () use ($autoavaliacao, $por): AutoavaliacaoDocente {
            $autoavaliacao = AutoavaliacaoDocente::query()
                ->lockForUpdate()
                ->with(['notas.criterio', 'ciclo.instrumento', 'funcionario'])
                ->findOrFail($autoavaliacao->id);

            if (! $autoavaliacao->pertenceA($por)) {
                throw new DesenvolvimentoDocenteException('Só o próprio professor envia a sua autoavaliação.');
            }

            if ($autoavaliacao->estaEnviada()) {
                throw new DesenvolvimentoDocenteException('Esta autoavaliação já foi enviada.');
            }

            $escala = (int) $autoavaliacao->ciclo->instrumento->escala_maxima;

            $semNota = $autoavaliacao->notas->filter(fn (AutoavaliacaoNota $n): bool => $n->nota === null);
            if ($semNota->isNotEmpty()) {
                throw new DesenvolvimentoDocenteException('Faltam notas nos critérios: '.$semNota->map(fn (AutoavaliacaoNota $n): string => (string) $n->criterio?->nome)->implode(', ').'.');
            }

            if ($autoavaliacao->notas->contains(fn (AutoavaliacaoNota $n): bool => $n->nota < 1 || $n->nota > $escala)) {
                throw new DesenvolvimentoDocenteException("As notas precisam estar entre 1 e {$escala}.");
            }

            $autoavaliacao->update([
                'status' => StatusAutoavaliacaoDocente::Enviada,
                'pontuacao_percentual' => $this->avaliacoes->calcularPercentual($autoavaliacao->notas, $escala),
                'enviada_em' => now(),
            ]);

            return $autoavaliacao->refresh();
        });
    }

    /**
     * Devolve a autoavaliação ao professor para correção (por exemplo, enviada por engano).
     *
     * @throws DesenvolvimentoDocenteException
     */
    public function reabrir(AutoavaliacaoDocente $autoavaliacao, User $por): AutoavaliacaoDocente
    {
        return DB::transaction(function () use ($autoavaliacao, $por): AutoavaliacaoDocente {
            $autoavaliacao = AutoavaliacaoDocente::query()->lockForUpdate()->findOrFail($autoavaliacao->id);

            if (! $autoavaliacao->estaEnviada()) {
                throw new DesenvolvimentoDocenteException('Só uma autoavaliação enviada pode ser reaberta.');
            }

            $autoavaliacao->disableLogging();
            $autoavaliacao->update([
                'status' => StatusAutoavaliacaoDocente::Rascunho,
                'pontuacao_percentual' => null,
                'enviada_em' => null,
            ]);
            $autoavaliacao->enableLogging();

            activity('autoavaliacao_docente')
                ->performedOn($autoavaliacao)
                ->causedBy($por)
                ->log('Autoavaliação docente reaberta para correção.');

            return $autoavaliacao->refresh();
        });
    }
}
