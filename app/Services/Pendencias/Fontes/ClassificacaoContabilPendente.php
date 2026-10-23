<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Enums\RegimeContabil;
use App\Filament\Pages\ExportacaoContabil;
use App\Models\User;
use App\Services\Contabil\ExportacaoContabilService;
use App\Support\Pendencias\ItemPendencia;
use Illuminate\Support\Facades\Cache;

/**
 * Exportação contábil (Onda 73): o que ainda falta classificar para entregar o mês anterior à contabilidade (conta do plano
 * sem o código do escritório, banco sem conta contábil, conta padrão sem definir). Depois do dia combinado com o escritório, a
 * fila inteira passa a atrasada.
 */
final class ClassificacaoContabilPendente implements FontePendencia
{
    /** @var list<array{mensagem: string, quantidade: int, exemplos: list<string>}>|null */
    private ?array $linhas = null;

    public function chave(): string
    {
        return 'classificacao_contabil_pendente';
    }

    public function titulo(): string
    {
        return 'Classificação contábil pendente';
    }

    public function area(): string
    {
        return 'Financeiro';
    }

    public function icone(): string
    {
        return 'heroicon-o-calculator';
    }

    public function orientacao(): string
    {
        return 'Preencha o código do escritório nas contas do plano e nos bancos e defina as contas padrão em Parâmetros contábeis.';
    }

    public function permissao(): string
    {
        return 'View:ExportacaoContabil';
    }

    public function url(): string
    {
        return ExportacaoContabil::getUrl();
    }

    public function contar(User $usuario): int
    {
        return array_sum(array_column($this->pendencias(), 'quantidade'));
    }

    public function atrasados(User $usuario): int
    {
        $prazo = (int) config('mesa_pendencias.contabilidade_dia_limite', 10);

        return today()->day > $prazo ? $this->contar($usuario) : 0;
    }

    public function itens(User $usuario, int $limite): array
    {
        $atrasado = $this->atrasados($usuario) > 0;

        return collect($this->pendencias())
            ->take($limite)
            ->map(fn (array $linha): ItemPendencia => new ItemPendencia(
                titulo: $linha['mensagem'],
                detalhe: $linha['quantidade'].' ocorrência(s): '.implode(' · ', $linha['exemplos']),
                url: $this->url(),
                atrasado: $atrasado,
            ))
            ->values()
            ->all();
    }

    public function esquecer(): void
    {
        Cache::forget($this->chaveDoCache());

        $this->linhas = null;
    }

    /**
     * @return list<array{mensagem: string, quantidade: int, exemplos: list<string>}>
     */
    private function pendencias(): array
    {
        return $this->linhas ??= Cache::remember(
            $this->chaveDoCache(),
            max(1, (int) config('mesa_pendencias.selo_cache_segundos', 300)),
            function (): array {
                $mes = today()->subMonthNoOverflow();
                $apuracao = app(ExportacaoContabilService::class)->apurar($mes->copy()->startOfMonth(), $mes->copy()->endOfMonth(), RegimeContabil::Caixa);

                return array_map(
                    fn (array $p): array => ['mensagem' => $p['mensagem'], 'quantidade' => $p['quantidade'], 'exemplos' => $p['exemplos']],
                    $apuracao['pendencias'],
                );
            },
        );
    }

    private function chaveDoCache(): string
    {
        return 'mesa-pendencias:classificacao-contabil:'.today()->subMonthNoOverflow()->format('Y-m');
    }
}
