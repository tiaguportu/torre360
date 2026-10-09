<?php

namespace App\Console\Commands;

use App\Services\LivroCapaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class LimparCapasOrfasCommand extends Command
{
    protected $signature = 'biblioteca:limpar-capas-orfas
        {--apagar : Apaga de verdade (sem esta opção o comando apenas lista)}
        {--dias=7 : Preserva arquivos modificados nos últimos N dias (mínimo 1)}
        {--force : Não pede confirmação e permite apagar mesmo sem nenhuma capa em uso no banco}';

    protected $description = 'Lista (e, com --apagar, remove) arquivos de livros/capas que nenhum livro usa mais';

    public function handle(LivroCapaService $capas): int
    {
        $dias = max(1, (int) $this->option('dias'));
        $apagar = (bool) $this->option('apagar');
        $force = (bool) $this->option('force');

        $conexao = (string) config('database.default');

        // Quem executa precisa ver QUAL disco está sendo comparado com QUAL banco antes de qualquer exclusão.
        $this->line('Disco:  '.Storage::disk('public')->path('livros/capas'));
        $this->line('Banco:  '.config("database.connections.{$conexao}.host").' / '.config("database.connections.{$conexao}.database"));
        $this->newLine();

        $diag = $capas->diagnosticarOrfas($dias);

        $this->line("Capas em uso pelos livros: {$diag['referenciadas']} (".($diag['referenciadas'] - $diag['ausentes']).' existem neste disco)');
        $this->line("Arquivos em uso encontrados no disco: {$diag['em_uso']}");
        $this->line("Arquivos sem uso, mas recentes (menos de {$dias} dia(s)), preservados: {$diag['recentes']}");
        $this->newLine();

        // Se nenhuma capa em uso existe aqui, este armazenamento provavelmente não pertence a este banco
        // (ex.: o .env aponta para o banco de produção mas os arquivos são de outra máquina): tudo pareceria órfão.
        if ($diag['referenciadas'] > 0 && $diag['ausentes'] === $diag['referenciadas']) {
            $this->error('Nenhuma das capas em uso no banco existe neste disco: este armazenamento provavelmente NÃO pertence a este banco.');
            $this->error('Nada foi apagado. Rode o comando no servidor em que as capas estão.');

            return self::FAILURE;
        }

        if ($diag['orfas'] === []) {
            $this->info('Nenhuma capa órfã encontrada.');

            return self::SUCCESS;
        }

        $total = array_sum(array_column($diag['orfas'], 'bytes'));

        $this->table(
            ['Arquivo órfão', 'Tamanho', 'Modificado em'],
            array_map(fn (array $o): array => [$o['caminho'], $this->formatarTamanho($o['bytes']), date('d/m/Y H:i', $o['modificado'])], array_slice($diag['orfas'], 0, 100)),
        );

        if (count($diag['orfas']) > 100) {
            $this->line('... e mais '.(count($diag['orfas']) - 100).' arquivo(s) não exibidos.');
        }

        $resumo = count($diag['orfas']).' arquivo(s) ('.$this->formatarTamanho($total).')';

        if (! $apagar) {
            $this->warn("{$resumo} órfão(s). Nada foi apagado. Para apagar: php artisan biblioteca:limpar-capas-orfas --apagar");

            return self::SUCCESS;
        }

        if ($diag['referenciadas'] === 0 && ! $force) {
            $this->error('Nenhum livro usa capa local neste banco, então todos os arquivos pareceriam órfãos. Se este é mesmo o banco certo, repita com --force.');

            return self::FAILURE;
        }

        if (! $force && ! $this->confirm("Apagar {$resumo}?", false)) {
            $this->warn('Cancelado. Nada foi apagado.');

            return self::SUCCESS;
        }

        $apagados = $capas->apagarOrfas(array_column($diag['orfas'], 'caminho'));

        $this->info("{$apagados} arquivo(s) apagado(s).");

        return self::SUCCESS;
    }

    private function formatarTamanho(int $bytes): string
    {
        return $bytes < 1048576
            ? number_format($bytes / 1024, 1, ',', '.').' KB'
            : number_format($bytes / 1048576, 2, ',', '.').' MB';
    }
}
