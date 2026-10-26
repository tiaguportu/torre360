<?php

namespace App\Console\Commands;

use App\Models\Interessado;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class PreencherPrimeiroContatoCommand extends Command
{
    protected $signature = 'crm:preencher-primeiro-contato
                            {--aplicar : Grava as datas. Sem esta opção só simula e nada é alterado}';

    protected $description = 'Preenche data_primeiro_contato (encerra o SLA de 1ª resposta) dos leads que já tinham contato humano lançado pela Linha do Tempo ou pelo Histórico antes da correção que passou a preenchê-la';

    /** Quantos leads de exemplo aparecem na simulação. */
    private const AMOSTRA = 10;

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');

        // Mesma regra de LeadFunilService::registrarPrimeiroContato()/reavaliarPrimeiroContato(), agora sobre o
        // histórico antigo (HistoricoContato::contatosDaEscola): contato que uma pessoa registrou (usuario_id), não
        // gerado pelo sistema nem do tipo administrativo. A data do primeiro contato é a do registro mais antigo.
        $contatosQueContam = fn (Builder $query): Builder => $query->contatosDaEscola();

        $candidatos = 0;
        $atualizados = 0;
        $amostra = [];

        // chunkById (e não chunk): gravar a data tira o lead do filtro `whereNull`, o que faria um `chunk` por
        // página pular leads.
        Interessado::query()
            ->whereNull('data_primeiro_contato')
            ->whereHas('historicos', $contatosQueContam)
            ->withMin(['historicos as primeiro_contato_em' => $contatosQueContam], 'data_contato')
            ->chunkById(500, function ($lote) use ($aplicar, &$candidatos, &$atualizados, &$amostra): void {
                foreach ($lote as $lead) {
                    $candidatos++;

                    if (count($amostra) < self::AMOSTRA) {
                        $amostra[] = [$lead->id, $lead->primeiro_contato_em];
                    }

                    if (! $aplicar) {
                        continue;
                    }

                    // `toBase()`: correção de dado, não edição do lead (não mexe em `updated_at` nem dispara eventos).
                    // O `whereNull` mantém a execução repetível e não sobrescreve quem foi preenchido no meio do caminho.
                    $atualizados += Interessado::query()
                        ->whereKey($lead->id)
                        ->whereNull('data_primeiro_contato')
                        ->toBase()
                        ->update(['data_primeiro_contato' => $lead->primeiro_contato_em]);
                }
            });

        if ($candidatos === 0) {
            $this->info('Nenhum lead com contato lançado e sem data de primeiro contato.');

            return self::SUCCESS;
        }

        if (! $aplicar) {
            $this->table(['Lead', 'Primeiro contato (data do registro mais antigo)'], $amostra);
            $this->warn("Simulação: {$candidatos} lead(s) receberiam a data de primeiro contato. Nada foi alterado. Rode com --aplicar para gravar.");

            return self::SUCCESS;
        }

        Log::info('crm:preencher-primeiro-contato aplicado', ['candidatos' => $candidatos, 'atualizados' => $atualizados]);
        $this->info("Data de primeiro contato preenchida em {$atualizados} de {$candidatos} lead(s).");

        return self::SUCCESS;
    }
}
