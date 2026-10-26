<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Contracts\GuardaPendenciasEmCache;
use App\Filament\Pages\OrcadoRealizado;
use App\Models\Orcamento;
use App\Models\User;
use App\Services\OrcamentoService;
use App\Support\Pendencias\ItemPendencia;
use Illuminate\Support\Facades\Cache;

/**
 * Despesas que chegaram perto do previsto (atenção) ou passaram dele (estouro) no acumulado do ano até o mês corrente (Onda 43). Usa
 * o orçamento aprovado do ano e a mesma conta da tela *Orçado × Realizado*. Só o estouro é atrasado. A conta percorre as linhas do
 * orçamento, então o resultado fica no cache por alguns minutos, para todos os usuários.
 */
final class OrcamentoEstourado implements FontePendencia, GuardaPendenciasEmCache
{
    /** @var list<array{conta: string, centro: ?string, previsto: float, realizado: float, percentual: ?float, situacao: string}>|null */
    private ?array $linhas = null;

    public function chave(): string
    {
        return 'orcamento_estourado';
    }

    public function titulo(): string
    {
        return 'Despesas acima do orçamento';
    }

    public function area(): string
    {
        return 'Financeiro';
    }

    public function icone(): string
    {
        return 'heroicon-o-banknotes';
    }

    public function orientacao(): string
    {
        return 'Veja a conta em Orçado × Realizado: classifique o que ficou sem conta, segure novas compras ou abra uma revisão do orçamento.';
    }

    public function permissao(): string
    {
        return 'View:OrcadoRealizado';
    }

    public function url(): string
    {
        return OrcadoRealizado::getUrl();
    }

    public function contar(User $usuario): int
    {
        return count($this->alertas());
    }

    public function atrasados(User $usuario): int
    {
        return count(array_filter($this->alertas(), fn (array $l): bool => $l['situacao'] === 'estourado'));
    }

    public function itens(User $usuario, int $limite): array
    {
        $ordenadas = $this->alertas();
        usort($ordenadas, fn (array $a, array $b): int => [$b['situacao'] === 'estourado', $b['percentual'] ?? 0] <=> [$a['situacao'] === 'estourado', $a['percentual'] ?? 0]);

        return array_map(fn (array $l): ItemPendencia => new ItemPendencia(
            titulo: $l['conta'].($l['centro'] ? " · {$l['centro']}" : ''),
            detalhe: 'Previsto R$ '.number_format($l['previsto'], 2, ',', '.').' · realizado R$ '.number_format($l['realizado'], 2, ',', '.').($l['percentual'] !== null ? ' ('.number_format($l['percentual'], 0, ',', '.').'%)' : ''),
            url: $this->url(),
            atrasado: $l['situacao'] === 'estourado',
        ), array_slice($ordenadas, 0, $limite));
    }

    public function esquecer(): void
    {
        Cache::forget($this->chaveDoCache());

        $this->linhas = null;
    }

    /**
     * @return list<array{conta: string, centro: ?string, previsto: float, realizado: float, percentual: ?float, situacao: string}>
     */
    private function alertas(): array
    {
        return $this->linhas ??= Cache::remember(
            $this->chaveDoCache(),
            max(1, (int) config('mesa_pendencias.selo_cache_segundos', 300)),
            function (): array {
                $orcamento = Orcamento::vigenteDoAno(now()->year);

                if (! $orcamento) {
                    return [];
                }

                return array_map(fn (array $l): array => [
                    'conta' => "{$l['plano']->codigo} - {$l['plano']->nome}",
                    'centro' => $l['centro']?->nome,
                    'previsto' => $l['previsto'],
                    'realizado' => $l['realizado'],
                    'percentual' => $l['percentual'],
                    'situacao' => $l['situacao'],
                ], app(OrcamentoService::class)->despesasEmAlerta($orcamento, (int) now()->month));
            },
        );
    }

    private function chaveDoCache(): string
    {
        return 'mesa-pendencias:orcamento-estourado:'.now()->format('Y-m');
    }
}
