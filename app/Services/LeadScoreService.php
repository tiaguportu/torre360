<?php

namespace App\Services;

use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\StatusInteressado;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Calcula o Lead Score (0-100): indicador automático e documentado de qualidade
 * de um lead do CRM, combinando perfil sócio-demográfico, engajamento e intenção
 * comercial. Ver docs/crm_lead_score.md para a explicação de cada fator e pesos.
 *
 * A "temperatura" (percepção manual do consultor) entra como um dos fatores, o de
maior peso, mas o score continua sendo calculado em conjunto com os demais dados.
 */
class LeadScoreService
{
    /**
     * Calcula o score (0-100) do lead a partir do estado atual no banco.
     */
    public static function calcular(Interessado $interessado): int
    {
        return collect(self::detalhar($interessado))->sum('pontos');
    }

    /**
     * Retorna o detalhamento do score por fator, para exibição na ficha do lead.
     *
     * @return array<int, array{fator: string, pontos: int, maximo: int}>
     */
    public static function detalhar(Interessado $interessado): array
    {
        $interessado->loadMissing(['pessoa', 'dependentes', 'historicos', 'status', 'origem']);
        $pesos = config('lead_score.pesos');

        return [
            ['fator' => 'Percepção do consultor', 'pontos' => self::pontosPercepcaoConsultor($interessado), 'maximo' => $pesos['percepcao_consultor']],
            ['fator' => 'Nº de filhos', 'pontos' => self::pontosFilhos($interessado), 'maximo' => $pesos['filhos']],
            ['fator' => 'Distância da escola', 'pontos' => self::pontosDistancia($interessado), 'maximo' => $pesos['distancia']],
            ['fator' => 'Meio de transporte', 'pontos' => self::pontosTransporte($interessado), 'maximo' => $pesos['transporte']],
            ['fator' => 'Profissão', 'pontos' => self::pontosProfissao($interessado), 'maximo' => $pesos['profissao']],
            ['fator' => 'Valor estimado', 'pontos' => self::pontosValorEstimado($interessado), 'maximo' => $pesos['valor_estimado']],
            ['fator' => 'Interações bem-sucedidas', 'pontos' => self::pontosInteracoesSucesso($interessado), 'maximo' => $pesos['interacoes_sucesso']],
            ['fator' => 'Total de interações', 'pontos' => self::pontosTotalInteracoes($interessado), 'maximo' => $pesos['total_interacoes']],
            ['fator' => 'Recência do contato', 'pontos' => self::pontosRecencia($interessado), 'maximo' => $pesos['recencia']],
            ['fator' => 'Completude do cadastro', 'pontos' => self::pontosCompletudeCadastro($interessado), 'maximo' => $pesos['completude_cadastro']],
            ['fator' => 'Origem do lead', 'pontos' => self::pontosOrigem($interessado), 'maximo' => $pesos['origem']],
            ['fator' => 'Estágio no funil', 'pontos' => self::pontosEstagioFunil($interessado), 'maximo' => $pesos['estagio_funil']],
        ];
    }

    /**
     * Recalcula e persiste o score do lead. Faz um update direto na tabela
     * (sem passar pelos eventos do Eloquent) para poder ser chamado com
     * segurança a partir de qualquer hook de save do próprio Interessado.
     */
    public static function recalcular(Interessado $interessado): int
    {
        $interessado->refresh();

        $score = self::calcular($interessado);

        self::gravar($interessado, $score);

        return $score;
    }

    /**
     * Maior `ordem` entre as etapas ativas, enquanto um lote está em andamento. Evita repetir a
     * consulta do fator "Estágio no funil" para cada lead (o valor é o mesmo para todos).
     */
    private static ?int $maiorOrdemAtivaEmLote = null;

    /**
     * Recalcula um lote de leads de uma vez. `recalcular()` (um lead) faz `refresh()` — que recarrega todas
     * as relações — e uma consulta de etapas por lead: para milhares de leads, dezenas de milhares de
     * consultas. Aqui as relações do lote inteiro vêm de uma consulta cada (`loadMissing`) e a etapa máxima
     * é buscada uma vez. Os leads do lote devem ter sido lidos agora (o estado em memória é o usado).
     *
     * @param  EloquentCollection<int, Interessado>  $leads
     * @return int quantos leads foram recalculados
     */
    public static function recalcularLote(EloquentCollection $leads): int
    {
        if ($leads->isEmpty()) {
            return 0;
        }

        $leads->loadMissing(['pessoa', 'dependentes', 'historicos', 'status', 'origem']);

        self::$maiorOrdemAtivaEmLote = (int) (StatusInteressado::query()->where('is_final', false)->max('ordem') ?: 1);

        try {
            foreach ($leads as $lead) {
                self::gravar($lead, self::calcular($lead));
            }
        } finally {
            self::$maiorOrdemAtivaEmLote = null;
        }

        return $leads->count();
    }

    private static function gravar(Interessado $interessado, int $score): void
    {
        DB::table('interessado')
            ->where('id', $interessado->id)
            ->update([
                'lead_score' => $score,
                'lead_score_atualizado_em' => now(),
            ]);

        $interessado->lead_score = $score;
    }

    /**
     * Cor Filament (success/warning/danger) correspondente à faixa do score.
     */
    public static function cor(?int $score): string
    {
        $faixas = config('lead_score.faixas_cor');

        return match (true) {
            $score === null => 'gray',
            $score >= $faixas['quente'] => 'success',
            $score >= $faixas['morno'] => 'warning',
            default => 'danger',
        };
    }

    private static function pontosPercepcaoConsultor(Interessado $interessado): int
    {
        $tabela = config('lead_score.percepcao_consultor');
        $chave = in_array($interessado->temperatura, ['quente', 'morno', 'frio'], true) ? $interessado->temperatura : 'nao_informado';

        return (int) ($tabela[$chave] ?? 0);
    }

    private static function pontosFilhos(Interessado $interessado): int
    {
        $total = $interessado->dependentes->count();

        foreach (config('lead_score.filhos') as $faixa) {
            if ($total >= $faixa['minimo']) {
                return $faixa['pontos'];
            }
        }

        return 0;
    }

    private static function pontosDistancia(Interessado $interessado): int
    {
        return config('lead_score.faixa_distancia_escola')[$interessado->faixa_distancia_escola] ?? 0;
    }

    private static function pontosTransporte(Interessado $interessado): int
    {
        return config('lead_score.meio_transporte')[$interessado->meio_transporte] ?? 0;
    }

    private static function pontosProfissao(Interessado $interessado): int
    {
        $profissao = $interessado->pessoa?->profissao;

        if (blank($profissao)) {
            return config('lead_score.profissao_padrao');
        }

        $normalizada = Str::lower(Str::ascii($profissao));

        foreach (config('lead_score.profissoes') as $palavraChave => $pontos) {
            if (str_contains($normalizada, Str::lower(Str::ascii($palavraChave)))) {
                return $pontos;
            }
        }

        return config('lead_score.profissao_padrao');
    }

    private static function pontosValorEstimado(Interessado $interessado): int
    {
        $valor = (float) ($interessado->valor_estimado ?? 0);

        foreach (config('lead_score.valor_estimado') as $faixa) {
            if ($valor >= $faixa['minimo']) {
                return $faixa['pontos'];
            }
        }

        return 0;
    }

    private static function pontosInteracoesSucesso(Interessado $interessado): int
    {
        $config = config('lead_score.interacoes_sucesso');
        $maximo = config('lead_score.pesos.interacoes_sucesso');

        $total = self::interacoes($interessado)
            ->whereIn('resultado', $config['resultados'])
            ->count();

        return min($maximo, $total * $config['pontos_por_interacao']);
    }

    private static function pontosTotalInteracoes(Interessado $interessado): int
    {
        $config = config('lead_score.total_interacoes');
        $maximo = config('lead_score.pesos.total_interacoes');

        return min($maximo, self::interacoes($interessado)->count() * $config['pontos_por_interacao']);
    }

    /**
     * Histórico que conta como interação: registros automáticos do sistema/IA não indicam engajamento
     * da família nem trabalho do consultor, então não pontuam (nem zeram a recência).
     *
     * @return Collection<int, HistoricoContato>
     */
    private static function interacoes(Interessado $interessado): Collection
    {
        return $interessado->historicos->where('automatico', false);
    }

    private static function pontosRecencia(Interessado $interessado): int
    {
        $referencia = self::interacoes($interessado)->max('data_contato') ?? $interessado->created_at;
        $diasSemContato = (int) $referencia->diffInDays(now());

        foreach (config('lead_score.recencia') as $faixa) {
            if ($faixa['maximo_dias'] === null || $diasSemContato <= $faixa['maximo_dias']) {
                return $faixa['pontos'];
            }
        }

        return 0;
    }

    private static function pontosCompletudeCadastro(Interessado $interessado): int
    {
        $checagens = [
            filled($interessado->pessoa?->telefone),
            filled($interessado->pessoa?->email),
            filled($interessado->pessoa?->profissao),
            $interessado->dependentes->isNotEmpty(),
        ];

        $maximo = config('lead_score.pesos.completude_cadastro');
        $preenchidos = count(array_filter($checagens));

        return (int) round(($preenchidos / count($checagens)) * $maximo);
    }

    private static function pontosOrigem(Interessado $interessado): int
    {
        $nome = $interessado->origem?->nome;

        if (blank($nome)) {
            return config('lead_score.origem_padrao');
        }

        return config('lead_score.origem')[Str::lower($nome)] ?? config('lead_score.origem_padrao');
    }

    private static function pontosEstagioFunil(Interessado $interessado): int
    {
        $maximo = config('lead_score.pesos.estagio_funil');

        if (! $interessado->status || $interessado->status->is_final) {
            return $interessado->status?->is_ganho ? $maximo : 0;
        }

        $maiorOrdemAtiva = self::$maiorOrdemAtivaEmLote ?? (StatusInteressado::where('is_final', false)->max('ordem') ?: 1);

        return (int) round(min(1, $interessado->status->ordem / $maiorOrdemAtiva) * $maximo);
    }
}
