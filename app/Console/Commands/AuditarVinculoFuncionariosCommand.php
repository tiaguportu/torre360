<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StatusProcessoFuncionario;
use App\Enums\TipoProcessoFuncionario;
use App\Models\ContratoTrabalho;
use App\Models\Funcionario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditarVinculoFuncionariosCommand extends Command
{
    protected $signature = 'rh:auditar-vinculo
        {--funcionario= : ID de um funcionário específico para auditar}
        {--corrigir : Limita o fim de contratos da categoria A à data do desligamento}
        {--limite=100 : Máximo de funcionários avaliados por execução}';

    protected $description = 'Audita a consistência de vínculos e vigências de contratos de trabalho de funcionários.';

    public function handle(): int
    {
        $podeCorrigir = false;
        if ($this->option('corrigir')) {
            $connection = (string) config('database.default');
            $dbConfig = config("database.connections.{$connection}", []);
            $host = $dbConfig['host'] ?? 'localhost';
            $database = $dbConfig['database'] ?? 'sqlite';

            $this->warn('ATENÇÃO: A opção --corrigir foi acionada para alterar registros da Categoria A.');
            $this->line("Conexão: <fg=yellow>{$connection}</> | Host: <fg=yellow>{$host}</> | Banco: <fg=yellow>{$database}</>");

            if (! $this->input->isInteractive()) {
                $this->error('A opção --corrigir não pode ser executada em modo não-interativo (--no-interaction). Confirmação explícita é obrigatória.');

                return self::FAILURE;
            }

            if (! $this->confirm('Deseja realmente corrigir os contratos da Categoria A?', false)) {
                $this->warn('Correção cancelada pelo operador. Executando apenas leitura.');
            } else {
                $podeCorrigir = true;
            }
        }

        $query = Funcionario::query()->with(['pessoa', 'contratosTrabalho', 'processos']);

        if ($funcionarioId = $this->option('funcionario')) {
            $query->whereKey($funcionarioId);
        }

        $limite = max(1, (int) $this->option('limite'));
        $funcionarios = $query->orderBy('id', 'desc')->limit($limite)->get();

        if ($funcionarios->isEmpty()) {
            $this->info('Nenhum funcionário encontrado para auditoria.');

            return self::SUCCESS;
        }

        $linhas = [];
        $totais = [
            'A' => 0,
            'B' => 0,
            'C' => 0,
            'D' => 0,
            'E' => 0,
        ];
        $corrigidosA = 0;

        foreach ($funcionarios as $funcionario) {
            $categorias = [];
            $detalhes = [];
            $acoes = [];

            $contratos = $funcionario->contratosTrabalho
                ->sortBy(fn (ContratoTrabalho $c) => $c->vigencia_inicio?->timestamp ?? 0)
                ->values();

            // Categoria C: Sem nenhum contrato
            if ($contratos->isEmpty()) {
                $categorias[] = 'C';
                $detalhes[] = 'Sem contratos de trabalho cadastrados';
                $totais['C']++;
            }

            // Categoria A: Desligado com contrato sem fim ou terminando após desligamento
            if ($funcionario->data_desligamento !== null && $contratos->isNotEmpty()) {
                $dataDesligamento = $funcionario->data_desligamento->startOfDay();
                $contratosDivergentes = $contratos->filter(function (ContratoTrabalho $c) use ($dataDesligamento): bool {
                    return $c->vigencia_fim === null || $c->vigencia_fim->startOfDay()->greaterThan($dataDesligamento);
                });

                if ($contratosDivergentes->isNotEmpty()) {
                    $categorias[] = 'A';
                    $totais['A']++;
                    $detalhes[] = "Contrato ativo/posterior ao desligamento ({$funcionario->data_desligamento->format('d/m/Y')})";

                    if ($podeCorrigir) {
                        DB::transaction(function () use ($contratosDivergentes, $funcionario, $dataDesligamento, &$corrigidosA): void {
                            foreach ($contratosDivergentes as $c) {
                                // Se o início for posterior ao desligamento, não altera vigencia_fim para não criar início > fim
                                if ($c->vigencia_inicio && $c->vigencia_inicio->startOfDay()->greaterThan($dataDesligamento)) {
                                    continue;
                                }

                                $fimAnterior = $c->vigencia_fim?->toDateString();
                                $c->update(['vigencia_fim' => $dataDesligamento->toDateString()]);
                                $corrigidosA++;

                                activity('rh')
                                    ->performedOn($c)
                                    ->withProperties([
                                        'funcionario_id' => $funcionario->id,
                                        'vigencia_fim_anterior' => $fimAnterior,
                                        'vigencia_fim_corrigida' => $dataDesligamento->toDateString(),
                                        'origem' => 'auditoria_cli',
                                    ])
                                    ->log('Vigência de contrato limitada à data de desligamento pela auditoria CLI.');
                            }
                        });
                        $acoes[] = 'Contrato(s) Categoria A encerrado(s)';
                    }
                }
            }

            // Categoria B: Vigência mais antiga com vigencia_inicio != data_admissao
            if ($contratos->isNotEmpty() && $funcionario->data_admissao !== null) {
                $primeiro = $contratos->first();
                if ($primeiro->vigencia_inicio?->toDateString() !== $funcionario->data_admissao->toDateString()) {
                    $categorias[] = 'B';
                    $totais['B']++;
                    $inicioStr = $primeiro->vigencia_inicio?->format('d/m/Y') ?? 'nulo';
                    $detalhes[] = "1ª vigência ({$inicioStr}) ≠ admissão ({$funcionario->data_admissao->format('d/m/Y')})";
                }
            }

            // Categoria D: Sobreposição ou lacuna
            if ($contratos->count() > 1) {
                $temSobreposicao = false;
                $temLacuna = false;

                for ($i = 0; $i < $contratos->count() - 1; $i++) {
                    $atual = $contratos->get($i);
                    $proximo = $contratos->get($i + 1);

                    if ($atual->vigencia_fim === null) {
                        $temSobreposicao = true;
                    } elseif ($proximo->vigencia_inicio) {
                        $fimAtual = $atual->vigencia_fim->startOfDay();
                        $inicioProx = $proximo->vigencia_inicio->startOfDay();

                        if ($inicioProx->lessThanOrEqualTo($fimAtual)) {
                            $temSobreposicao = true;
                        } elseif ($inicioProx->greaterThan($fimAtual->copy()->addDay())) {
                            $temLacuna = true;
                        }
                    }
                }

                if ($temSobreposicao || $temLacuna) {
                    $categorias[] = 'D';
                    $totais['D']++;
                    $descD = [];
                    if ($temSobreposicao) {
                        $descD[] = 'sobreposição';
                    }
                    if ($temLacuna) {
                        $descD[] = 'lacuna';
                    }
                    $detalhes[] = 'Vigências com '.implode(' e ', $descD);
                }
            }

            // Categoria E: data_desligamento sem processo de Desligamento concluído
            if ($funcionario->data_desligamento !== null) {
                $temProcessoConcluido = $funcionario->processos
                    ->where('tipo', TipoProcessoFuncionario::Desligamento)
                    ->where('status', StatusProcessoFuncionario::Concluido)
                    ->isNotEmpty();

                if (! $temProcessoConcluido) {
                    $categorias[] = 'E';
                    $totais['E']++;
                    $detalhes[] = 'Desligado sem processo formal concluído (legado manual)';
                }
            }

            if (! empty($categorias)) {
                $linhas[] = [
                    $funcionario->id,
                    mb_strimwidth($funcionario->pessoa?->nome ?? 'Sem nome', 0, 26, '...'),
                    $funcionario->isAtivo() ? 'Ativo' : 'Desligado',
                    implode(', ', $categorias),
                    mb_strimwidth(implode('; ', $detalhes), 0, 50, '...'),
                    empty($acoes) ? 'Apenas relatório' : implode('; ', $acoes),
                ];
            }
        }

        $this->table(
            ['ID', 'Funcionário', 'Status', 'Cat.', 'Inconsistências Identificadas', 'Ação'],
            $linhas
        );

        $this->newLine();
        $this->info(sprintf(
            'Auditoria concluída em %d funcionário(s). Inconsistências: [A: %d] [B: %d] [C: %d] [D: %d] [E: %d]%s.',
            $funcionarios->count(),
            $totais['A'],
            $totais['B'],
            $totais['C'],
            $totais['D'],
            $totais['E'],
            $podeCorrigir ? " | {$corrigidosA} contrato(s) corrigido(s)" : ''
        ));

        if ($totais['A'] > 0 && ! $this->option('corrigir')) {
            $this->warn('Dica: Use --corrigir para limitar as vigências da categoria A à data de desligamento.');
        }

        return self::SUCCESS;
    }
}
