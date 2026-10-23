<?php

declare(strict_types=1);

namespace App\Services\Relatorios;

use App\Contracts\FonteDeRelatorio;
use App\Enums\FormatoRelatorio;
use App\Enums\OrigemExecucaoRelatorio;
use App\Enums\StatusExecucaoRelatorio;
use App\Enums\TipoColunaRelatorio;
use App\Models\ExecucaoRelatorio;
use App\Models\RelatorioSalvo;
use App\Models\User;
use App\Notifications\RelatorioProntoNotification;
use App\Support\CsvSanitizer;
use App\Support\Relatorios\ColunaRelatorio;
use App\Support\Relatorios\DataDeFiltro;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Executa um relatório configurável (Onda 72): confere quem pode ver os dados, pede as linhas à fonte, ordena, escreve o
 * arquivo (planilha ou PDF), guarda-o e registra a execução.
 *
 * O relatório nunca vira SQL: as colunas e os filtros guardados só valem se a fonte os declara, e a consulta é da fonte. A
 * permissão da fonte é conferida a cada execução (a pessoa pode ter perdido o acesso depois de salvar o relatório) e, no
 * agendamento, para cada destinatário. O aviso por e-mail leva só o link: o arquivo com dados pessoais fica no sistema.
 */
class RelatorioExecutorService
{
    public function __construct(private readonly CatalogoDeRelatorios $catalogo) {}

    /**
     * Gera o arquivo e registra a execução. Falha de negócio (sem permissão, fonte que sumiu, coluna inválida) vira uma
     * execução com erro, para o agendador seguir com os outros relatórios.
     */
    public function executar(RelatorioSalvo $relatorio, OrigemExecucaoRelatorio $origem, ?User $por = null): ExecucaoRelatorio
    {
        $formato = $relatorio->formato ?? FormatoRelatorio::Csv;
        $quem = $por ?? $relatorio->criadoPor;

        try {
            $fonte = $this->catalogo->da($relatorio->fonte);

            if (! $fonte) {
                throw new \DomainException('A fonte de dados deste relatório não existe mais.');
            }

            if (! $quem || ! $this->catalogo->usuarioPode($quem, $fonte)) {
                throw new \DomainException('Sem permissão para os dados desta fonte ("'.$fonte->titulo().'").');
            }

            $colunas = $this->colunasValidas($fonte, (array) $relatorio->colunas);

            if ($colunas === []) {
                throw new \DomainException('Escolha ao menos uma coluna que a fonte ainda ofereça.');
            }

            $limite = max(1, (int) config($formato === FormatoRelatorio::Pdf ? 'relatorios.limite_linhas_pdf' : 'relatorios.limite_linhas'));
            $linhas = $fonte->linhas($this->filtrosValidos($fonte, (array) $relatorio->filtros), $limite + 1);
            $truncado = count($linhas) > $limite;
            $linhas = $this->ordenar(array_slice($linhas, 0, $limite), $colunas, $relatorio->ordenar_por, $relatorio->direcao === 'desc' ? 'desc' : 'asc');

            $conteudo = $formato === FormatoRelatorio::Pdf
                ? $this->pdf($relatorio, $fonte, $colunas, $linhas, $truncado, $quem)
                : $this->csv($colunas, $linhas);

            $caminho = "relatorios/{$relatorio->id}/".Str::uuid().'.'.$formato->value;
            Storage::disk('local')->put($caminho, $conteudo);

            $execucao = ExecucaoRelatorio::create([
                'relatorio_salvo_id' => $relatorio->id,
                'origem' => $origem,
                'status' => StatusExecucaoRelatorio::Sucesso,
                'formato' => $formato,
                'linhas' => count($linhas),
                'truncado' => $truncado,
                'arquivo_path' => $caminho,
                'arquivo_hash' => hash('sha256', $conteudo),
                'executado_por_user_id' => $quem->id,
                'expira_em' => today()->addDays(max(1, (int) config('relatorios.retencao_dias', 30)))->toDateString(),
            ]);
        } catch (\DomainException $e) {
            $execucao = $this->registrarErro($relatorio, $origem, $formato, $quem, $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Falha ao gerar o relatório configurável.', ['relatorio_id' => $relatorio->id, 'erro' => $e->getMessage()]);
            $execucao = $this->registrarErro($relatorio, $origem, $formato, $quem, 'Falha inesperada ao gerar o relatório. Tente de novo e, se repetir, avise o suporte.');
        }

        $registro = activity('relatorios')->performedOn($relatorio)->withProperties([
            'execucao' => $execucao->id,
            'origem' => $origem->value,
            'status' => $execucao->status->value,
            'linhas' => $execucao->linhas,
        ]);

        if ($quem) {
            $registro->causedBy($quem);
        }

        $registro->log($execucao->status === StatusExecucaoRelatorio::Sucesso ? 'Relatório gerado.' : 'Relatório com erro.');

        return $execucao;
    }

    /**
     * Avisa os destinatários do agendamento: só quem está ativo e tem a permissão da fonte recebe. Devolve quantos foram
     * avisados.
     */
    public function avisarDestinatarios(RelatorioSalvo $relatorio, ExecucaoRelatorio $execucao): int
    {
        $fonte = $this->catalogo->da($relatorio->fonte);

        if (! $fonte || ! $execucao->temArquivo()) {
            return 0;
        }

        $avisados = 0;

        foreach (User::query()->whereIn('id', (array) $relatorio->destinatarios)->get() as $destinatario) {
            if (! $this->catalogo->usuarioPode($destinatario, $fonte)) {
                continue;
            }

            $destinatario->notify(new RelatorioProntoNotification($relatorio, $execucao));
            $avisados++;
        }

        return $avisados;
    }

    // --- Entradas ----------------------------------------------------------------------------------------------------

    /**
     * As colunas guardadas que a fonte ainda oferece, na ordem em que foram escolhidas.
     *
     * @param  list<string>  $guardadas
     * @return list<ColunaRelatorio>
     */
    public function colunasValidas(FonteDeRelatorio $fonte, array $guardadas): array
    {
        $oferecidas = $fonte->colunas();
        $colunas = [];

        foreach ($guardadas as $chave) {
            if (is_string($chave) && isset($oferecidas[$chave]) && ! isset($colunas[$chave])) {
                $colunas[$chave] = $oferecidas[$chave];
            }
        }

        return array_values($colunas);
    }

    /**
     * Só passa o que a fonte declara: `chave` (opção ou número) e `chave_de`/`chave_ate` (período). Opção fora da lista é
     * descartada.
     *
     * @param  array<string, mixed>  $guardados
     * @return array<string, mixed>
     */
    public function filtrosValidos(FonteDeRelatorio $fonte, array $guardados): array
    {
        $validos = [];

        foreach ($fonte->filtros() as $chave => $filtro) {
            if ($filtro->tipo === 'data') {
                foreach (['de', 'ate'] as $lado) {
                    $data = DataDeFiltro::normalizar($guardados["{$chave}_{$lado}"] ?? null);

                    if ($data !== null) {
                        $validos["{$chave}_{$lado}"] = $data;
                    }
                }

                continue;
            }

            $valor = $guardados[$chave] ?? null;

            if (! is_scalar($valor) || (string) $valor === '') {
                continue;
            }

            if ($filtro->tipo === 'opcao' && ! array_key_exists((string) $valor, $filtro->opcoes)) {
                continue;
            }

            if ($filtro->tipo === 'numero' && ! is_numeric($valor)) {
                continue;
            }

            $validos[$chave] = $valor;
        }

        return $validos;
    }

    // --- Saídas ------------------------------------------------------------------------------------------------------

    /**
     * @param  list<ColunaRelatorio>  $colunas
     * @param  list<array<string, mixed>>  $linhas
     * @return list<array<string, mixed>>
     */
    private function ordenar(array $linhas, array $colunas, ?string $ordenarPor, string $direcao): array
    {
        $coluna = collect($colunas)->first(fn (ColunaRelatorio $c): bool => $c->chave === $ordenarPor);

        if (! $coluna) {
            return $linhas;
        }

        $chave = fn (array $linha): mixed => $this->valorParaOrdenar($linha[$coluna->chave] ?? null, $coluna);

        usort($linhas, function (array $a, array $b) use ($chave, $direcao): int {
            [$va, $vb] = [$chave($a), $chave($b)];

            // Vazio sempre no fim, qualquer que seja a direção.
            if ($va === null || $vb === null) {
                return ($va === null) <=> ($vb === null);
            }

            $comparacao = $va <=> $vb;

            return $direcao === 'desc' ? -$comparacao : $comparacao;
        });

        return $linhas;
    }

    private function valorParaOrdenar(mixed $valor, ColunaRelatorio $coluna): int|float|string|null
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return match ($coluna->tipo) {
            TipoColunaRelatorio::Inteiro, TipoColunaRelatorio::Decimal, TipoColunaRelatorio::Moeda => (float) $valor,
            TipoColunaRelatorio::Data => $valor instanceof \DateTimeInterface ? $valor->getTimestamp() : (strtotime((string) $valor) ?: null),
            TipoColunaRelatorio::Texto => mb_strtolower((string) $valor),
        };
    }

    /**
     * @param  list<ColunaRelatorio>  $colunas
     * @param  list<array<string, mixed>>  $linhas
     */
    private function csv(array $colunas, array $linhas): string
    {
        $saida = fopen('php://temp', 'w+');
        // BOM para o Excel abrir o UTF-8 com acentos; ponto e vírgula, o separador do Excel em português.
        fwrite($saida, "\xEF\xBB\xBF");
        fputcsv($saida, array_map(fn (ColunaRelatorio $c): string => $c->rotulo, $colunas), ';');

        foreach ($linhas as $linha) {
            fputcsv($saida, array_map(function (ColunaRelatorio $c) use ($linha): string {
                $texto = $this->formatar($linha[$c->chave] ?? null, $c);

                // Só o texto livre pode carregar fórmula; números e datas já saem em formato fixo.
                return $c->tipo === TipoColunaRelatorio::Texto ? (string) CsvSanitizer::sanitize($texto) : $texto;
            }, $colunas), ';');
        }

        rewind($saida);
        $conteudo = (string) stream_get_contents($saida);
        fclose($saida);

        return $conteudo;
    }

    /**
     * @param  list<ColunaRelatorio>  $colunas
     * @param  list<array<string, mixed>>  $linhas
     */
    private function pdf(RelatorioSalvo $relatorio, FonteDeRelatorio $fonte, array $colunas, array $linhas, bool $truncado, User $quem): string
    {
        $tabela = array_map(fn (array $linha): array => array_map(fn (ColunaRelatorio $c): string => $this->formatar($linha[$c->chave] ?? null, $c), $colunas), $linhas);

        return Pdf::loadView('pdfs.relatorio-configuravel', [
            'titulo' => $relatorio->nome,
            'fonte' => $fonte->titulo(),
            'escola' => config('app.name'),
            'colunas' => $colunas,
            'linhas' => $tabela,
            'truncado' => $truncado,
            'limite' => (int) config('relatorios.limite_linhas_pdf'),
            'geradoPor' => $quem->name,
            'geradoEm' => now()->format('d/m/Y H:i'),
            'temDadoPessoal' => collect($colunas)->contains(fn (ColunaRelatorio $c): bool => $c->sensivel),
        ])->setPaper('a4', 'landscape')->output();
    }

    private function formatar(mixed $valor, ColunaRelatorio $coluna): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return match ($coluna->tipo) {
            TipoColunaRelatorio::Inteiro => (string) (int) $valor,
            TipoColunaRelatorio::Decimal, TipoColunaRelatorio::Moeda => number_format((float) $valor, 2, ',', ''),
            TipoColunaRelatorio::Data => $this->data($valor),
            TipoColunaRelatorio::Texto => $valor instanceof \DateTimeInterface ? $valor->format('d/m/Y H:i') : (string) $valor,
        };
    }

    private function data(mixed $valor): string
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('d/m/Y');
        }

        try {
            return Carbon::parse((string) $valor)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $valor;
        }
    }

    private function registrarErro(RelatorioSalvo $relatorio, OrigemExecucaoRelatorio $origem, FormatoRelatorio $formato, ?User $quem, string $mensagem): ExecucaoRelatorio
    {
        return ExecucaoRelatorio::create([
            'relatorio_salvo_id' => $relatorio->id,
            'origem' => $origem,
            'status' => StatusExecucaoRelatorio::Erro,
            'formato' => $formato,
            'linhas' => 0,
            'truncado' => false,
            'erro' => $mensagem,
            'executado_por_user_id' => $quem?->id,
        ]);
    }

    // --- Agendamento -------------------------------------------------------------------------------------------------

    /**
     * Roda os relatórios agendados cuja hora chegou. A próxima execução é reservada antes de gerar o arquivo, para duas
     * rodadas simultâneas do agendador não gerarem o mesmo relatório duas vezes. Três falhas seguidas desligam o
     * agendamento e avisam o dono.
     *
     * @return array{executados: int, com_erro: int, desligados: int}
     */
    public function executarAgendados(?CarbonInterface $agora = null, ?int $limite = null): array
    {
        $agora = $agora ?? now();
        $limite = max(1, $limite ?? (int) config('relatorios.limite_por_rodada', 10));
        $resumo = ['executados' => 0, 'com_erro' => 0, 'desligados' => 0];

        $vencidos = RelatorioSalvo::query()
            ->with('criadoPor')
            ->where('agendado', true)
            ->whereNotNull('proxima_execucao_em')
            ->where('proxima_execucao_em', '<=', $agora)
            ->orderBy('proxima_execucao_em')
            ->limit($limite)
            ->get();

        foreach ($vencidos as $relatorio) {
            $reservado = RelatorioSalvo::query()
                ->whereKey($relatorio->id)
                ->where('proxima_execucao_em', $relatorio->proxima_execucao_em)
                ->update(['proxima_execucao_em' => $relatorio->calcularProximaExecucao($agora), 'ultima_execucao_em' => $agora]);

            if ($reservado === 0) {
                continue;
            }

            $execucao = $this->executar($relatorio, OrigemExecucaoRelatorio::Agendada);
            $resumo['executados']++;

            if ($execucao->status === StatusExecucaoRelatorio::Erro) {
                $resumo['com_erro']++;

                if ($this->desligarSeFalhouSempre($relatorio)) {
                    $resumo['desligados']++;
                }

                continue;
            }

            $this->avisarDestinatarios($relatorio, $execucao);
        }

        return $resumo;
    }

    /**
     * As três últimas execuções falharam: o agendamento para, em vez de errar para sempre em silêncio.
     */
    private function desligarSeFalhouSempre(RelatorioSalvo $relatorio): bool
    {
        $ultimas = ExecucaoRelatorio::query()->where('relatorio_salvo_id', $relatorio->id)->orderByDesc('id')->limit(3)->pluck('status');

        if ($ultimas->count() < 3 || $ultimas->contains(fn ($status): bool => $status !== StatusExecucaoRelatorio::Erro)) {
            return false;
        }

        RelatorioSalvo::query()->whereKey($relatorio->id)->update(['agendado' => false, 'proxima_execucao_em' => null]);

        $dono = $relatorio->criadoPor;

        if ($dono) {
            Notification::make()
                ->title('Agendamento desligado')
                ->body("O relatório \"{$relatorio->nome}\" falhou nas três últimas execuções e foi desagendado. Abra o histórico para ver o motivo.")
                ->danger()
                ->sendToDatabase($dono);
        }

        return true;
    }

    /**
     * Apaga os arquivos cuja retenção acabou. O registro da execução fica (quando, quem, quantas linhas).
     */
    public function limparArquivosVencidos(?CarbonInterface $hoje = null): int
    {
        $hoje = $hoje ?? today();
        $apagados = 0;

        ExecucaoRelatorio::query()
            ->whereNotNull('arquivo_path')
            ->whereDate('expira_em', '<', $hoje->toDateString())
            ->orderBy('id')
            ->chunkById(200, function ($execucoes) use (&$apagados): void {
                foreach ($execucoes as $execucao) {
                    Storage::disk('local')->delete((string) $execucao->arquivo_path);
                    $execucao->update(['arquivo_path' => null, 'arquivo_hash' => null]);
                    $apagados++;
                }
            });

        return $apagados;
    }
}
