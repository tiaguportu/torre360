<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MotivoDesligamentoFuncionario;
use App\Enums\StatusProcessoFuncionario;
use App\Enums\TipoProcessoFuncionario;
use App\Models\Funcionario;
use App\Models\GradeHorario;
use App\Models\ItemProcessoFuncionario;
use App\Models\ProcessoFuncionario;
use App\Models\User;
use App\Support\ArquivoPrivado;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Admissão e desligamento de funcionários (Onda 65).
 *
 * Iniciar cria o processo com a lista de providências de config/rh_processos.php. Concluir exige os itens obrigatórios
 * resolvidos (feitos ou "não se aplica" com justificativa) e, no desligamento, só vale a partir da data do desligamento:
 * grava `data_desligamento` e tira os acessos de equipe da pessoa ao sistema.
 *
 * Regra de acesso no desligamento: sai todo papel que não seja de família (responsável ou aluno), porque a pessoa pode ser
 * pai, mãe ou aluno da escola, e a conta continua para isso. Sem nenhum papel sobrando, a conta é desativada. Super
 * administrador nunca é alterado em silêncio: vira aviso para o administrador resolver.
 */
class ProcessoFuncionarioService
{
    /**
     * Papéis que continuam depois do desligamento.
     *
     * @var list<string>
     */
    public const PAPEIS_DA_FAMILIA = ['responsavel', 'aluno'];

    /** Selo do menu (providências vencidas): fica em cache e é esquecido a cada mudança num processo. */
    public const CACHE_SELO = 'rh.processos_funcionario.selo';

    // --- Início ------------------------------------------------------------------------------------------------------

    /**
     * @throws \DomainException
     */
    public function iniciar(
        Funcionario $funcionario,
        TipoProcessoFuncionario $tipo,
        CarbonInterface|string $data,
        ?User $por = null,
        ?MotivoDesligamentoFuncionario $motivo = null,
        ?string $observacao = null,
    ): ProcessoFuncionario {
        $data = Carbon::parse($data)->startOfDay();

        return DB::transaction(function () use ($funcionario, $tipo, $data, $por, $motivo, $observacao): ProcessoFuncionario {
            // Trava o funcionário: dois cliques viram um processo só.
            $funcionario = Funcionario::query()->lockForUpdate()->findOrFail($funcionario->id);

            $jaAberto = ProcessoFuncionario::query()
                ->emAndamento()
                ->where('funcionario_id', $funcionario->id)
                ->where('tipo', $tipo->value)
                ->exists();

            if ($jaAberto) {
                throw new \DomainException('Já existe '.($tipo === TipoProcessoFuncionario::Admissao ? 'uma admissão' : 'um desligamento').' em andamento para este funcionário.');
            }

            if ($tipo === TipoProcessoFuncionario::Desligamento) {
                if (! $funcionario->isAtivo()) {
                    throw new \DomainException('Este funcionário já foi desligado em '.$funcionario->data_desligamento->format('d/m/Y').'.');
                }

                if ($motivo === null) {
                    throw new \DomainException('Informe o motivo do desligamento.');
                }
            }

            $processo = ProcessoFuncionario::create([
                'funcionario_id' => $funcionario->id,
                'tipo' => $tipo,
                'status' => StatusProcessoFuncionario::EmAndamento,
                'data_prevista' => $data,
                'motivo' => $tipo === TipoProcessoFuncionario::Desligamento ? $motivo : null,
                'observacao' => filled($observacao) ? trim($observacao) : null,
                'iniciado_por_user_id' => $por?->id,
            ]);

            foreach ((array) config("rh_processos.{$tipo->value}", []) as $posicao => $item) {
                $processo->itens()->create([
                    'categoria' => $item['categoria'],
                    'titulo' => $item['titulo'],
                    'orientacao' => $item['orientacao'] ?? null,
                    'obrigatorio' => (bool) ($item['obrigatorio'] ?? true),
                    'prazo' => isset($item['prazo_dias']) ? $data->copy()->addDays((int) $item['prazo_dias'])->toDateString() : null,
                    'ordem' => $posicao + 1,
                ]);
            }

            // O motivo do desligamento (justa causa, acordo) fica só no processo, fora do histórico de alterações.
            $this->registrarLog($processo, $por, $tipo === TipoProcessoFuncionario::Admissao ? 'Admissão iniciada.' : 'Desligamento iniciado.', [
                'funcionario_id' => $funcionario->id,
            ]);

            return $processo;
        });
    }

    /**
     * Acrescenta uma providência que a lista padrão não tem.
     *
     * @param  array{titulo: string, categoria?: string, orientacao?: ?string, obrigatorio?: bool, prazo?: mixed}  $dados
     *
     * @throws \DomainException
     */
    public function adicionarItem(ProcessoFuncionario $processo, array $dados, ?User $por = null): ItemProcessoFuncionario
    {
        $this->exigirEmAndamento($processo);

        if (blank($dados['titulo'] ?? null)) {
            throw new \DomainException('Informe o título da providência.');
        }

        $item = $processo->itens()->create([
            'categoria' => $dados['categoria'] ?? 'outro',
            'titulo' => trim((string) $dados['titulo']),
            'orientacao' => filled($dados['orientacao'] ?? null) ? trim((string) $dados['orientacao']) : null,
            'obrigatorio' => (bool) ($dados['obrigatorio'] ?? false),
            'prazo' => filled($dados['prazo'] ?? null) ? Carbon::parse($dados['prazo'])->toDateString() : null,
            'ordem' => ((int) $processo->itens()->reorder()->max('ordem')) + 1,
        ]);

        $this->registrarLog($processo, $por, 'Providência acrescentada ao processo.', ['item_id' => $item->id]);

        return $item;
    }

    // --- Itens -------------------------------------------------------------------------------------------------------

    /**
     * Marca a providência como feita, com observação e comprovante opcionais.
     *
     * @throws \DomainException
     */
    public function marcarItem(ItemProcessoFuncionario $item, ?User $por = null, ?string $observacao = null, ?UploadedFile $arquivo = null): ItemProcessoFuncionario
    {
        $processo = $this->processoEmAndamentoDoItem($item);

        if ($arquivo) {
            $this->guardarArquivo($item, $arquivo);
        }

        $item->forceFill([
            'concluido_em' => $item->concluido_em ?? now(),
            'nao_se_aplica' => false,
            'observacao' => filled($observacao) ? trim($observacao) : $item->observacao,
            'concluido_por_user_id' => $item->concluido_por_user_id ?? $por?->id,
        ])->save();

        $this->registrarLog($processo, $por, 'Providência concluída.', ['item_id' => $item->id]);

        return $item;
    }

    /**
     * Resolve a providência como "não se aplica": a justificativa é obrigatória, porque um item obrigatório sai da frente.
     *
     * @throws \DomainException
     */
    public function dispensarItem(ItemProcessoFuncionario $item, ?User $por, string $justificativa): ItemProcessoFuncionario
    {
        $processo = $this->processoEmAndamentoDoItem($item);

        if (blank($justificativa)) {
            throw new \DomainException('Explique por que esta providência não se aplica.');
        }

        $item->forceFill([
            'concluido_em' => now(),
            'nao_se_aplica' => true,
            'observacao' => trim($justificativa),
            'concluido_por_user_id' => $por?->id,
        ])->save();

        $this->registrarLog($processo, $por, 'Providência dispensada (não se aplica).', ['item_id' => $item->id]);

        return $item;
    }

    /**
     * @throws \DomainException
     */
    public function reabrirItem(ItemProcessoFuncionario $item, ?User $por = null): ItemProcessoFuncionario
    {
        $processo = $this->processoEmAndamentoDoItem($item);

        $item->forceFill([
            'concluido_em' => null,
            'nao_se_aplica' => false,
            'concluido_por_user_id' => null,
        ])->save();

        $this->registrarLog($processo, $por, 'Providência reaberta.', ['item_id' => $item->id]);

        return $item;
    }

    /**
     * Anexa (ou troca) o comprovante sem mexer na situação da providência.
     *
     * @throws \DomainException
     */
    public function anexarArquivo(ItemProcessoFuncionario $item, UploadedFile $arquivo, ?User $por = null): ItemProcessoFuncionario
    {
        $processo = $this->processoEmAndamentoDoItem($item);
        $this->guardarArquivo($item, $arquivo);

        $this->registrarLog($processo, $por, 'Comprovante anexado à providência.', ['item_id' => $item->id]);

        return $item;
    }

    private function guardarArquivo(ItemProcessoFuncionario $item, UploadedFile $arquivo): void
    {
        $anterior = $item->arquivo_path;
        $caminho = ArquivoPrivado::guardarDocumento($arquivo, "processos_funcionario/{$item->processo_id}", "item-{$item->id}-".now()->format('YmdHis'));

        $item->update(['arquivo_path' => $caminho]);

        if ($anterior && $anterior !== $caminho) {
            Storage::disk('local')->delete($anterior);
        }
    }

    // --- Andamento ---------------------------------------------------------------------------------------------------

    /**
     * Providências obrigatórias que ainda não foram resolvidas.
     *
     * @return Collection<int, ItemProcessoFuncionario>
     */
    public function pendenciasObrigatorias(ProcessoFuncionario $processo): Collection
    {
        return $processo->itens()->where('obrigatorio', true)->whereNull('concluido_em')->get();
    }

    /**
     * @return array{total: int, resolvidos: int, obrigatorios_pendentes: int, atrasados: int, percentual: int}
     */
    public function progresso(ProcessoFuncionario $processo): array
    {
        $itens = $processo->itens()->get();
        $total = $itens->count();
        $resolvidos = $itens->filter(fn (ItemProcessoFuncionario $item): bool => $item->estaResolvido())->count();

        return [
            'total' => $total,
            'resolvidos' => $resolvidos,
            'obrigatorios_pendentes' => $itens->filter(fn (ItemProcessoFuncionario $item): bool => $item->obrigatorio && ! $item->estaResolvido())->count(),
            'atrasados' => $itens->filter(fn (ItemProcessoFuncionario $item): bool => $item->estaAtrasado())->count(),
            'percentual' => $total > 0 ? (int) round($resolvidos / $total * 100) : 0,
        ];
    }

    /**
     * @return array{usuarios_sem_acesso: int, usuarios_desativados: int, avisos: list<string>}
     *
     * @throws \DomainException
     */
    public function concluir(ProcessoFuncionario $processo, User $por): array
    {
        $resumo = ['usuarios_sem_acesso' => 0, 'usuarios_desativados' => 0, 'avisos' => []];

        DB::transaction(function () use ($processo, $por, &$resumo): void {
            $atual = ProcessoFuncionario::query()->lockForUpdate()->findOrFail($processo->id);
            $this->exigirEmAndamento($atual);

            $pendentes = $this->pendenciasObrigatorias($atual);

            if ($pendentes->isNotEmpty()) {
                $titulos = $pendentes->take(3)->pluck('titulo')->implode('; ');
                $resto = $pendentes->count() - 3;

                throw new \DomainException('Faltam providências obrigatórias: '.$titulos.($resto > 0 ? " e mais {$resto}" : '').'.');
            }

            $funcionario = Funcionario::query()->with('pessoa.users.roles')->findOrFail($atual->funcionario_id);

            if ($atual->ehDesligamento()) {
                if ($atual->data_prevista->isFuture()) {
                    throw new \DomainException('O desligamento está marcado para '.$atual->data_prevista->format('d/m/Y').'. Conclua a partir dessa data.');
                }

                $funcionario->update(['data_desligamento' => $atual->data_prevista]);
                $this->retirarAcessos($funcionario, $resumo);
                $this->avisarSobreAulas($funcionario, $resumo);
            }

            $atual->forceFill([
                'status' => StatusProcessoFuncionario::Concluido,
                'concluido_em' => now(),
                'concluido_por_user_id' => $por->id,
            ])->save();

            $this->registrarLog($atual, $por, $atual->ehDesligamento() ? 'Desligamento concluído.' : 'Admissão concluída.', [
                'funcionario_id' => $funcionario->id,
                'usuarios_sem_acesso' => $resumo['usuarios_sem_acesso'],
                'usuarios_desativados' => $resumo['usuarios_desativados'],
            ]);
        });

        return $resumo;
    }

    /**
     * @throws \DomainException
     */
    public function cancelar(ProcessoFuncionario $processo, User $por): ProcessoFuncionario
    {
        return DB::transaction(function () use ($processo, $por): ProcessoFuncionario {
            $atual = ProcessoFuncionario::query()->lockForUpdate()->findOrFail($processo->id);
            $this->exigirEmAndamento($atual);

            $atual->forceFill(['status' => StatusProcessoFuncionario::Cancelado, 'concluido_em' => now(), 'concluido_por_user_id' => $por->id])->save();

            $this->registrarLog($atual, $por, 'Processo cancelado.');

            return $atual;
        });
    }

    // --- Desligamento ------------------------------------------------------------------------------------------------

    /**
     * @param  array{usuarios_sem_acesso: int, usuarios_desativados: int, avisos: list<string>}  $resumo
     */
    private function retirarAcessos(Funcionario $funcionario, array &$resumo): void
    {
        foreach ($funcionario->pessoa?->users ?? [] as $usuario) {
            if ($usuario->hasRole('super_admin')) {
                $resumo['avisos'][] = "O usuário {$usuario->email} é super administrador e não foi alterado: ajuste o acesso dele manualmente.";

                continue;
            }

            $papeis = $usuario->roles->pluck('name');
            $papeisDaFamilia = $papeis->intersect(self::PAPEIS_DA_FAMILIA)->values()->all();

            if ($papeis->count() > count($papeisDaFamilia)) {
                $resumo['usuarios_sem_acesso']++;
            }

            // Permissão dada direto ao usuário também é acesso de equipe: sai junto.
            $usuario->syncRoles($papeisDaFamilia);
            $usuario->syncPermissions([]);

            if ($papeisDaFamilia === []) {
                $usuario->forceFill(['deactivated_at' => now()])->save();
                $resumo['usuarios_desativados']++;
            }
        }
    }

    /**
     * @param  array{usuarios_sem_acesso: int, usuarios_desativados: int, avisos: list<string>}  $resumo
     */
    private function avisarSobreAulas(Funcionario $funcionario, array &$resumo): void
    {
        $aulas = GradeHorario::query()->where('professor_id', $funcionario->pessoa_id)->count();

        if ($aulas > 0) {
            $resumo['avisos'][] = "Há {$aulas} aula(s) na grade horária com este professor: reatribua em Calendário e Horários.";
        }
    }

    // --- Apoio -------------------------------------------------------------------------------------------------------

    /**
     * @throws \DomainException
     */
    private function exigirEmAndamento(ProcessoFuncionario $processo): void
    {
        // Consulta o banco: o model recebido pode estar desatualizado (dois administradores no mesmo processo).
        $emAndamento = ProcessoFuncionario::query()->emAndamento()->whereKey($processo->id)->exists();

        if (! $emAndamento) {
            throw new \DomainException('Este processo já foi encerrado.');
        }
    }

    /**
     * O processo do item, já conferido como em andamento. Busca por id para não depender da relação carregada.
     */
    private function processoEmAndamentoDoItem(ItemProcessoFuncionario $item): ProcessoFuncionario
    {
        $processo = ProcessoFuncionario::query()->findOrFail($item->processo_id);
        $this->exigirEmAndamento($processo);

        return $processo;
    }

    /**
     * Log sem o texto livre (justa causa, saúde, acordos) e sem dados da pessoa.
     *
     * @param  array<string, mixed>  $propriedades
     */
    private function registrarLog(ProcessoFuncionario $processo, ?User $por, string $mensagem, array $propriedades = []): void
    {
        // Toda mudança num processo passa por aqui: o selo do menu precisa contar de novo.
        Cache::forget(self::CACHE_SELO);

        $log = activity('rh')
            ->performedOn($processo)
            ->withProperties(['tipo' => $processo->tipo->value] + $propriedades);

        if ($por) {
            $log->causedBy($por);
        }

        $log->log($mensagem);
    }
}
