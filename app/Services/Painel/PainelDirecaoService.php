<?php

declare(strict_types=1);

namespace App\Services\Painel;

use App\Contracts\IndicadorDoPainel;
use App\Models\IndicadorMeta;
use App\Models\PainelSnapshot;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Pendencias\MesaDePendencias;
use App\Support\Painel\ContextoPainel;
use App\Support\Pendencias\FilaDePendencias;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Painel da Direção (Onda 45): junta os indicadores cadastrados em `config/painel_direcao.php`, entrega a cada pessoa só os que a
 * permissão dela permite, compara com a fotografia de 30 dias atrás e com a do ano anterior, e aplica a meta e o semáforo.
 *
 * Cada indicador tem uma definição só (a classe dele); o painel não recalcula nada por conta própria. O valor de cada indicador fica em
 * cache por alguns minutos e a rotina diária guarda uma fotografia para as séries.
 */
class PainelDirecaoService
{
    public function __construct(private readonly MesaDePendencias $mesa) {}

    /**
     * @return list<IndicadorDoPainel>
     */
    public function indicadores(): array
    {
        return array_values(array_map(fn (string $classe): IndicadorDoPainel => app($classe), (array) config('painel_direcao.indicadores', [])));
    }

    /**
     * @return list<IndicadorDoPainel>
     */
    public function visiveisPara(User $usuario): array
    {
        return array_values(array_filter($this->indicadores(), fn (IndicadorDoPainel $i): bool => $usuario->can($i->permissao())));
    }

    /**
     * O valor do indicador agora (em cache por alguns minutos). Indicador que falha não derruba o painel: vira "sem dado".
     */
    public function valor(IndicadorDoPainel $indicador, ContextoPainel $contexto): ?float
    {
        $unidade = $indicador->suportaUnidade() ? $contexto->unidadeId : 0;
        $versao = (int) Cache::get('painel.versao', 1);
        $chave = "painel.valor.v{$versao}.{$indicador->chave()}.{$unidade}.{$contexto->hoje->toDateString()}";

        try {
            return Cache::remember($chave, max(1, (int) config('painel_direcao.cache_segundos', 600)), fn (): ?float => $indicador->valor(new ContextoPainel($contexto->hoje, $unidade)));
        } catch (\Throwable $e) {
            Log::warning('Painel da Direção: o indicador não carregou.', ['indicador' => $indicador->chave(), 'erro' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Esquece os valores guardados (o botão "Atualizar").
     */
    public function esquecer(): void
    {
        Cache::forever('painel.versao', (int) Cache::get('painel.versao', 1) + 1);
    }

    /**
     * Os cartões do painel para a pessoa, agrupados na ordem dos grupos.
     *
     * @return array<string, list<array<string, mixed>>> grupo => cartões
     */
    public function cartoes(User $usuario, int $unidadeId = 0, ?CarbonInterface $hoje = null): array
    {
        $hoje = CarbonImmutable::parse($hoje ?? today())->startOfDay();
        $contexto = new ContextoPainel($hoje, $unidadeId);
        $grupos = [];

        foreach ($this->visiveisPara($usuario) as $indicador) {
            $unidade = $indicador->suportaUnidade() ? $unidadeId : 0;
            $valor = $this->valor($indicador, $contexto);
            $anterior = $this->fotografiaAte($indicador->chave(), $unidade, $hoje->subDays(max(1, (int) config('painel_direcao.comparacao_dias', 30))), 60);
            $anoAnterior = $this->fotografiaAte($indicador->chave(), $unidade, $hoje->subYear(), 15);
            $meta = $this->meta($indicador->chave(), $hoje->year, $unidade);

            $grupos[$indicador->grupo()][] = [
                'chave' => $indicador->chave(),
                'titulo' => $indicador->titulo(),
                'descricao' => $indicador->descricao(),
                'formato' => $indicador->formato(),
                'melhor' => $indicador->melhor(),
                'url' => $indicador->url(),
                'escola_toda' => $unidadeId > 0 && ! $indicador->suportaUnidade(),
                'valor' => $valor,
                'valor_formatado' => $this->formatar($indicador->formato(), $valor),
                'meta' => $meta ? (float) $meta->meta : null,
                'meta_formatada' => $meta ? $this->formatar($indicador->formato(), (float) $meta->meta) : null,
                'semaforo' => $this->semaforo($indicador, $valor, $meta),
                'anterior' => $anterior ? ['valor' => $anterior->valor, 'data' => $anterior->data, 'formatado' => $this->formatar($indicador->formato(), $anterior->valor)] : null,
                'variacao' => $this->variacao($indicador, $valor, $anterior?->valor),
                'variacao_ano' => $this->variacao($indicador, $valor, $anoAnterior?->valor),
                'serie' => $this->serie($indicador->chave(), $unidade, $hoje),
            ];
        }

        return $grupos;
    }

    /**
     * Guarda a fotografia de hoje de todos os indicadores: da escola toda e, para os que suportam, de cada unidade. Indicador que
     * falha fica sem fotografia no dia (um zero falso estragaria a série).
     *
     * @return int quantas fotografias foram gravadas
     */
    public function fotografar(?CarbonInterface $data = null): int
    {
        $hoje = CarbonImmutable::parse($data ?? today())->startOfDay();
        $unidades = Unidade::query()->pluck('id')->all();
        $gravadas = 0;

        foreach ($this->indicadores() as $indicador) {
            $alvos = $indicador->suportaUnidade() ? [0, ...$unidades] : [0];

            foreach ($alvos as $unidade) {
                try {
                    $valor = $indicador->valor(new ContextoPainel($hoje, (int) $unidade));
                } catch (\Throwable $e) {
                    Log::warning('Painel da Direção: a fotografia do indicador falhou.', ['indicador' => $indicador->chave(), 'erro' => $e->getMessage()]);

                    continue;
                }

                // A busca é por whereDate: a coluna tem cast de data, que em alguns bancos guarda também a hora.
                $existente = PainelSnapshot::query()
                    ->whereDate('data', $hoje->toDateString())
                    ->where('indicador', $indicador->chave())
                    ->where('unidade_id', (int) $unidade)
                    ->first();

                if ($existente) {
                    $existente->update(['valor' => $valor]);
                } else {
                    PainelSnapshot::create(['data' => $hoje->toDateString(), 'indicador' => $indicador->chave(), 'unidade_id' => (int) $unidade, 'valor' => $valor]);
                }

                $gravadas++;
            }
        }

        return $gravadas;
    }

    /**
     * Apaga as fotografias mais antigas que a retenção.
     */
    public function limpar(?CarbonInterface $hoje = null): int
    {
        $limite = CarbonImmutable::parse($hoje ?? today())->subDays(max(30, (int) config('painel_direcao.retencao_dias', 800)));

        return PainelSnapshot::query()->whereDate('data', '<', $limite->toDateString())->delete();
    }

    /**
     * A meta do ano para a unidade, ou a da escola toda quando a unidade não tem a sua.
     */
    public function meta(string $indicador, int $ano, int $unidadeId = 0): ?IndicadorMeta
    {
        $metas = IndicadorMeta::query()->where('indicador', $indicador)->where('ano', $ano)->whereIn('unidade_id', array_unique([$unidadeId, 0]))->get()->keyBy('unidade_id');

        return $metas->get($unidadeId) ?? $metas->get(0);
    }

    /**
     * Verde se o valor atingiu a meta, amarelo se está dentro da tolerância, vermelho se passou dela. Sem meta ou sem valor, sem cor.
     */
    public function semaforo(IndicadorDoPainel $indicador, ?float $valor, ?IndicadorMeta $meta): ?string
    {
        if ($valor === null || $meta === null) {
            return null;
        }

        $alvo = (float) $meta->meta;
        $folga = abs($alvo) * (float) $meta->tolerancia_percentual / 100;

        if ($indicador->melhor() === 'maior') {
            return match (true) {
                $valor >= $alvo => 'verde',
                $valor >= $alvo - $folga => 'amarelo',
                default => 'vermelho',
            };
        }

        return match (true) {
            $valor <= $alvo => 'verde',
            $valor <= $alvo + $folga => 'amarelo',
            default => 'vermelho',
        };
    }

    /**
     * Diferença para o valor de comparação e se ela é boa ou ruim para o indicador (matrículas subindo é melhor; inadimplência
     * subindo é pior).
     *
     * @return array{absoluta: float, percentual: ?float, sentido: string}|null
     */
    public function variacao(IndicadorDoPainel $indicador, ?float $atual, ?float $anterior): ?array
    {
        if ($atual === null || $anterior === null) {
            return null;
        }

        $diferenca = round($atual - $anterior, 2);

        return [
            'absoluta' => $diferenca,
            'percentual' => abs($anterior) > 0.0001 ? round($diferenca / abs($anterior) * 100, 1) : null,
            'sentido' => match (true) {
                abs($diferenca) < 0.005 => 'igual',
                ($diferenca > 0) === ($indicador->melhor() === 'maior') => 'melhor',
                default => 'pior',
            },
        ];
    }

    public function formatar(string $formato, ?float $valor): string
    {
        if ($valor === null) {
            return '—';
        }

        return match ($formato) {
            'dinheiro' => 'R$ '.number_format($valor, 2, ',', '.'),
            'percentual' => number_format($valor, 1, ',', '.').'%',
            default => number_format($valor, 0, ',', '.'),
        };
    }

    /**
     * Itens que pedem ação hoje: as filas da Minha Mesa da pessoa que têm algo atrasado, as mais atrasadas primeiro.
     *
     * @return list<FilaDePendencias>
     */
    public function atencaoHoje(User $usuario, int $limite = 6): array
    {
        return $this->mesa->filas($usuario, 3)
            ->filter(fn (FilaDePendencias $fila): bool => $fila->atrasados > 0)
            ->sortByDesc(fn (FilaDePendencias $fila): int => $fila->atrasados)
            ->take($limite)
            ->values()
            ->all();
    }

    /**
     * A fotografia mais recente que não seja posterior à data, dentro da janela de tolerância (dias para trás).
     */
    private function fotografiaAte(string $indicador, int $unidadeId, CarbonImmutable $data, int $janelaDias): ?PainelSnapshot
    {
        return PainelSnapshot::query()
            ->where('indicador', $indicador)
            ->where('unidade_id', $unidadeId)
            ->whereNotNull('valor')
            ->whereDate('data', '<=', $data->toDateString())
            ->whereDate('data', '>=', $data->subDays($janelaDias)->toDateString())
            ->orderByDesc('data')
            ->first();
    }

    /**
     * @return list<float>
     */
    private function serie(string $indicador, int $unidadeId, CarbonImmutable $hoje): array
    {
        return PainelSnapshot::query()
            ->where('indicador', $indicador)
            ->where('unidade_id', $unidadeId)
            ->whereNotNull('valor')
            ->whereDate('data', '>=', $hoje->subDays(max(7, (int) config('painel_direcao.serie_dias', 90)))->toDateString())
            ->orderBy('data')
            ->pluck('valor')
            ->map(fn ($v): float => (float) $v)
            ->all();
    }
}
