<?php

namespace App\Console\Commands;

use App\Models\Livro;
use Illuminate\Console\Command;

class ReconciliarDisponibilidadeLivrosCommand extends Command
{
    protected $signature = 'biblioteca:reconciliar-disponibilidade {--aplicar : Grava as correções (sem esta opção apenas lista as divergências)}';

    protected $description = 'Confere se quantidade_disponivel = quantidade_total − empréstimos em aberto e, com --aplicar, corrige as divergências';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $divergencias = [];
        $verificados = 0;

        Livro::query()
            ->withCount(['emprestimosEmAberto as em_aberto'])
            ->chunkById(200, function ($livros) use ($aplicar, &$divergencias, &$verificados): void {
                foreach ($livros as $livro) {
                    $verificados++;
                    $correta = max(0, (int) $livro->quantidade_total - (int) $livro->em_aberto);

                    if ((int) $livro->quantidade_disponivel === $correta) {
                        continue;
                    }

                    $divergencias[] = [
                        $livro->id,
                        $livro->codigo ?: '—',
                        mb_strimwidth((string) $livro->titulo, 0, 40, '…'),
                        $livro->quantidade_total,
                        $livro->em_aberto,
                        $livro->quantidade_disponivel,
                        $correta,
                    ];

                    if ($aplicar) {
                        $livro->forceFill(['quantidade_disponivel' => $correta])->saveQuietly();
                    }
                }
            });

        if ($divergencias === []) {
            $this->info("{$verificados} obra(s) verificada(s): nenhuma divergência de estoque.");

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Tombo', 'Título', 'Total', 'Em aberto', 'Disponível (gravado)', 'Disponível (correto)'],
            $divergencias,
        );

        $this->{$aplicar ? 'info' : 'warn'}(
            $aplicar
                ? count($divergencias).' obra(s) corrigida(s) de '.$verificados.' verificada(s).'
                : count($divergencias).' divergência(s) em '.$verificados.' obra(s). Nada foi gravado; use --aplicar para corrigir.'
        );

        return self::SUCCESS;
    }
}
