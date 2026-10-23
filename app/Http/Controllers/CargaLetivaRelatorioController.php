<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PeriodoLetivo;
use App\Services\CargaLetivaService;
use App\Support\CsvSanitizer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Resumo do calendário letivo (Onda 49) em PDF e planilha, para a secretaria de educação ou o conselho de educação: por
 * turma, os dias e as horas previstos, realizados e projetados, as aulas não realizadas e as reposições.
 */
class CargaLetivaRelatorioController extends Controller
{
    public function pdf(PeriodoLetivo $periodo, CargaLetivaService $servico): Response
    {
        $this->autorizar();

        $apuracoes = $servico->apurarPeriodo($periodo);

        return Pdf::loadView('pdfs.carga-letiva', [
            'periodo' => $periodo,
            'apuracoes' => $apuracoes,
            'escola' => config('app.name'),
            'geradoEm' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape')->stream("carga-letiva-{$periodo->id}.pdf");
    }

    public function csv(PeriodoLetivo $periodo, CargaLetivaService $servico): StreamedResponse
    {
        $this->autorizar();

        $apuracoes = $servico->apurarPeriodo($periodo);

        return response()->streamDownload(function () use ($apuracoes): void {
            $saida = fopen('php://output', 'w');
            // BOM para o Excel abrir o UTF-8 com acentos; ponto e vírgula, o separador do Excel em português.
            fwrite($saida, "\xEF\xBB\xBF");
            CsvSanitizer::fputcsv($saida, [
                'Turma', 'Etapa', 'Dias previstos', 'Dias realizados', 'Dias projetados', 'Horas previstas', 'Horas realizadas',
                'Horas projetadas', 'Mínimo de horas', 'Mínimo de dias', 'Aulas não realizadas', 'Reposições', 'Aulas sem registro', 'Situação',
            ], ';');

            foreach ($apuracoes as $a) {
                CsvSanitizer::fputcsv($saida, [
                    $a['turma']->nome,
                    $a['etapa'] ?? '',
                    $a['dias_previstos'],
                    $a['dias_realizados'],
                    $a['dias_projetados'],
                    $this->numero($a['horas_previstas']),
                    $this->numero($a['horas_realizadas']),
                    $this->numero($a['horas_projetadas']),
                    $a['minimo']['horas'] ?? '',
                    $a['minimo']['dias'] ?? '',
                    $a['aulas_nao_realizadas'],
                    $a['reposicoes'],
                    $a['aulas_sem_registro'],
                    $a['situacao']->getLabel(),
                ], ';');
            }

            fclose($saida);
        }, "carga-letiva-{$periodo->id}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function autorizar(): void
    {
        abort_unless(auth()->user()?->can('CargaLetiva:Turma'), 403, 'Sem permissão para a carga letiva.');
    }

    private function numero(float $valor): string
    {
        return number_format($valor, 1, ',', '');
    }
}
