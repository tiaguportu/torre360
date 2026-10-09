<?php

namespace App\Services;

use App\Enums\StatusSolicitacaoDocumento;
use App\Enums\TipoTemplateDocumento;
use App\Models\Matricula;
use App\Models\SituacaoFinalDisciplina;
use App\Models\SolicitacaoDocumento;
use App\Models\TemplateDocumento;
use App\Models\TipoVinculo;
use App\Models\Unidade;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class DocumentoService
{
    public function __construct(
        protected QrCodeService $qrCodeService
    ) {}

    /**
     * Substitui todas as variáveis dinâmicas no conteúdo do template.
     */
    public function preencherMacros(TemplateDocumento $template, Matricula $matricula, SolicitacaoDocumento $solicitacao): string
    {
        $aluno = $matricula->pessoa;
        $turma = $matricula->turma;
        $serie = $turma?->serie;
        $curso = $serie?->curso;
        $unidade = $curso?->unidade;
        $periodoLetivo = $matricula->periodoLetivo ?? $turma?->periodoLetivo;

        // Vínculos familiares (Pai e Mãe)
        $vinculoPai = TipoVinculo::whereIn('nome', ['Pai', 'pai'])->first();
        $vinculoMae = TipoVinculo::whereIn('nome', ['Mãe', 'mãe', 'Mae', 'mae'])->first();

        $nomePai = 'Não declarado';
        $nomeMae = 'Não declarada';

        if ($aluno) {
            if ($vinculoPai && $pai = $aluno->responsaveis()->wherePivot('tipo_vinculo_id', $vinculoPai->id)->first()) {
                $nomePai = $pai->nome;
            }
            if ($vinculoMae && $mae = $aluno->responsaveis()->wherePivot('tipo_vinculo_id', $vinculoMae->id)->first()) {
                $nomeMae = $mae->nome;
            }
        }

        // Endereço do aluno
        $endAluno = $aluno?->enderecos()->first();
        $enderecoAlunoFormatado = $endAluno ? trim("{$endAluno->logradouro}, {$endAluno->numero} ".($endAluno->complemento ? "- {$endAluno->complemento} " : '')."- {$endAluno->bairro}, {$endAluno->cidade?->nome}/{$endAluno->cidade?->estado?->sigla} - CEP: {$endAluno->cep}") : 'Não informado';

        $cidadeUnidade = $unidade?->endereco?->cidade?->nome ?? 'Esta Cidade';
        $urlValidacao = url('/validar-documento/'.$solicitacao->codigo_verificacao);

        $dataNascimento = $aluno?->data_nascimento ? Carbon::parse($aluno->data_nascimento)->format('d/m/Y') : '-';
        $cpfFormatado = $aluno?->cpf ? (strlen($aluno->cpf) === 11 ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $aluno->cpf) : $aluno->cpf) : 'Não informado';

        $situacaoFormatada = is_object($matricula->situacao) && method_exists($matricula->situacao, 'getLabel')
            ? $matricula->situacao->getLabel()
            : (string) ($matricula->situacao ?? 'Ativa');

        // Histórico de notas simplificado caso a macro seja utilizada
        $tabelaHistorico = $this->gerarTabelaHistoricoHtml($matricula);

        $variaveis = [
            // Aluno
            '{{ALUNO_NOME}}' => $aluno?->nome ?? '-',
            '{{ALUNO_CPF}}' => $cpfFormatado,
            '{{ALUNO_RG}}' => $aluno?->identidade ?? 'Não informado',
            '{{ALUNO_NASCIMENTO}}' => $dataNascimento,
            '{{ALUNO_PAI}}' => $nomePai,
            '{{ALUNO_MAE}}' => $nomeMae,
            '{{ALUNO_ENDERECO}}' => $enderecoAlunoFormatado,

            // Acadêmico
            '{{MATRICULA_ID}}' => (string) $matricula->id,
            '{{SITUACAO_MATRICULA}}' => $situacaoFormatada,
            '{{DATA_MATRICULA}}' => $matricula->data_ativacao ? Carbon::parse($matricula->data_ativacao)->format('d/m/Y') : $matricula->created_at->format('d/m/Y'),
            '{{TURMA_NOME}}' => $turma?->nome ?? '-',
            '{{SERIE_NOME}}' => $serie?->nome ?? '-',
            '{{CURSO_NOME}}' => $curso?->nome_externo ?? $curso?->nome_interno ?? '-',
            '{{PERIODO_LETIVO}}' => $periodoLetivo?->nome ?? (string) now()->year,
            '{{TURNO_NOME}}' => $turma?->turno?->nome ?? 'Regular',
            '{{HORARIO_AULAS}}' => $matricula->getHorarioAulasFormatado(),
            '{{ANO_LETIVO}}' => $periodoLetivo?->nome ?? (string) now()->year,
            '{{ANO_ANTERIOR}}' => (string) (now()->year - 1),
            '{{STATUS_FINANCEIRO}}' => $matricula->hasDebitosVencidos() ? 'Com pendências' : 'Adimplente / Em dia',

            // Unidade / Escola
            '{{UNIDADE_NOME}}' => $unidade?->nome ?? 'Torre360',
            '{{UNIDADE_CNPJ}}' => $unidade?->cnpj ?? '',
            '{{UNIDADE_CIDADE}}' => $cidadeUnidade,
            '{{UNIDADE_ESTADO}}' => $unidade?->endereco?->cidade?->estado?->sigla ?? '',

            // Autenticidade
            '{{PROTOCOLO}}' => $solicitacao->protocolo,
            '{{CODIGO_VERIFICACAO}}' => $solicitacao->codigo_verificacao,
            '{{URL_VERIFICACAO}}' => $urlValidacao,
            '{{DATA_EMISSAO}}' => $solicitacao->data_emissao ? $solicitacao->data_emissao->format('d/m/Y') : now()->format('d/m/Y'),
            '{{DATA_VALIDADE}}' => $solicitacao->data_validade ? $solicitacao->data_validade->format('d/m/Y') : now()->addDays($template->validade_dias)->format('d/m/Y'),
            '{{DATA_EXTENSO}}' => "{$cidadeUnidade}, ".now()->translatedFormat('d \d\e F \d\e Y'),
            '{{TABELA_HISTORICO}}' => $tabelaHistorico,
        ];

        return str_replace(array_keys($variaveis), array_values($variaveis), $template->conteudo);
    }

    /**
     * Valida regras de negócio para emissão do documento (ex: adimplência para quitação).
     *
     * @throws \DomainException
     */
    public function validarEmissao(TemplateDocumento $template, Matricula $matricula): void
    {
        if ($template->tipo === TipoTemplateDocumento::DeclaracaoQuitacao) {
            if ($matricula->hasDebitosVencidos()) {
                $totalPendencias = $matricula->getDebitosVencidosCount();
                throw new \DomainException("Não foi possível emitir a Declaração de Quitação de Débitos: constam {$totalPendencias} fatura(s) com pendências financeiras ou vencidas em aberto. Por favor, acesse o menu Financeiro para regularizar.");
            }
        }
    }

    /**
     * Cria e emite o documento oficial instantaneamente, gerando o PDF com carimbo e QR Code.
     *
     * @throws \DomainException
     */
    public function emitirDocumento(Matricula $matricula, TemplateDocumento $template, ?string $observacao = null, ?User $solicitadoPor = null): SolicitacaoDocumento
    {
        $this->validarEmissao($template, $matricula);

        $solicitacao = SolicitacaoDocumento::create([
            'protocolo' => SolicitacaoDocumento::gerarProtocolo(),
            'codigo_verificacao' => SolicitacaoDocumento::gerarCodigoVerificacao(),
            'matricula_id' => $matricula->id,
            'template_documento_id' => $template->id,
            'solicitado_por_user_id' => $solicitadoPor?->id ?? auth()->id(),
            'status' => StatusSolicitacaoDocumento::Disponivel,
            'observacao_solicitante' => $observacao,
            'data_solicitacao' => now(),
            'data_emissao' => now(),
            'data_validade' => now()->addDays($template->validade_dias ?? 30),
        ]);

        $this->gerarPdf($solicitacao);

        return $solicitacao;
    }

    /**
     * Emite e grava o PDF oficial com autenticação por QR Code.
     */
    public function gerarPdf(SolicitacaoDocumento $solicitacao): string
    {
        $matricula = $solicitacao->matricula;
        $template = $solicitacao->templateDocumento;
        $unidade = $matricula->turma?->serie?->curso?->unidade;

        if (! $solicitacao->data_emissao) {
            $solicitacao->data_emissao = now();
        }

        if (! $solicitacao->data_validade) {
            $solicitacao->data_validade = now()->addDays($template->validade_dias ?? 30);
        }

        $conteudoProcessado = $this->preencherMacros($template, $matricula, $solicitacao);
        $conteudoProcessado = HtmlSanitizer::clean($conteudoProcessado);

        $urlValidacao = url('/validar-documento/'.$solicitacao->codigo_verificacao);
        $qrCodeDataUri = $this->qrCodeService->renderDataUri($urlValidacao);
        $logoBase64 = $this->obterLogoBase64($unidade);
        $cidade = $unidade?->endereco?->cidade?->nome ?? 'Localidade';

        $dadosView = [
            'solicitacao' => $solicitacao,
            'template' => $template,
            'matricula' => $matricula,
            'unidade' => $unidade,
            'conteudo' => $conteudoProcessado,
            'qrCodeDataUri' => $qrCodeDataUri,
            'logoBase64' => $logoBase64,
            'cidade' => $cidade,
        ];

        $pdf = Pdf::loadView('pdfs.documento_oficial', $dadosView)
            ->setPaper('a4', 'portrait');

        $caminhoRelativo = 'documentos_emitidos/'.$solicitacao->protocolo.'.pdf';
        Storage::disk('local')->put($caminhoRelativo, $pdf->output());

        $solicitacao->arquivo_path = $caminhoRelativo;
        $solicitacao->status = StatusSolicitacaoDocumento::Disponivel;
        $solicitacao->save();

        return $caminhoRelativo;
    }

    /**
     * Retorna a representação base64 da logo da instituição/unidade.
     */
    protected function obterLogoBase64(?Unidade $unidade): ?string
    {
        $instituicao = $unidade?->instituicaoEnsino;
        $logoPath = $instituicao?->logo;

        if (! $logoPath) {
            return null;
        }

        try {
            $disk = config('filament.default_filesystem_disk', 'local');
            if (Storage::disk($disk)->exists($logoPath)) {
                $content = Storage::disk($disk)->get($logoPath);
                $mime = Storage::disk($disk)->mimeType($logoPath);

                return 'data:'.$mime.';base64,'.base64_encode($content);
            } elseif (Storage::disk('public')->exists($logoPath)) {
                $content = Storage::disk('public')->get($logoPath);
                $mime = Storage::disk('public')->mimeType($logoPath);

                return 'data:'.$mime.';base64,'.base64_encode($content);
            }
        } catch (\Throwable) {
            // Ignora falha de leitura e retorna null
        }

        return null;
    }

    /**
     * Gera HTML do histórico escolar real do aluno: uma tabela por matrícula (ano/período letivo),
     * com a situação final já consolidada pelo Fechamento do Ciclo Letivo (`SituacaoFinalDisciplina`) —
     * já refletindo o resultado do exame final quando aplicável. Períodos ainda não fechados, ou sem
     * nenhuma disciplina calculada, aparecem com um aviso em vez de uma tabela vazia.
     */
    protected function gerarTabelaHistoricoHtml(Matricula $matricula): string
    {
        if (! $matricula->pessoa_id) {
            return '<p><em>Nenhum aluno vinculado a esta matrícula.</em></p>';
        }

        $matriculas = Matricula::query()
            ->where('pessoa_id', $matricula->pessoa_id)
            ->with(['periodoLetivo', 'turma.serie', 'turma.periodoLetivo'])
            ->get()
            ->sortBy(fn (Matricula $m) => $m->periodoLetivo?->data_inicio
                ?? $m->turma?->periodoLetivo?->data_inicio
                ?? $m->created_at)
            ->values();

        if ($matriculas->isEmpty()) {
            return '<p><em>Nenhuma matrícula encontrada para o histórico.</em></p>';
        }

        $situacoesPorMatricula = SituacaoFinalDisciplina::query()
            ->whereIn('matricula_id', $matriculas->pluck('id'))
            ->with('disciplina')
            ->get()
            ->groupBy('matricula_id');

        $html = '';

        foreach ($matriculas as $matriculaDoAno) {
            $periodoLetivo = $matriculaDoAno->periodoLetivo ?? $matriculaDoAno->turma?->periodoLetivo;
            $periodoNome = $periodoLetivo?->nome ?? '-';
            $serieNome = $matriculaDoAno->turma?->serie?->nome ?? '-';

            $html .= '<p style="margin-top: 15px; margin-bottom: 4px; font-size: 12px;"><strong>'.htmlspecialchars($periodoNome).' — '.htmlspecialchars($serieNome).'</strong></p>';

            $registros = $situacoesPorMatricula->get($matriculaDoAno->id, collect())
                ->sortBy(fn ($r) => $r->disciplina?->ordem_boletim ?? 0)
                ->values();

            if ($registros->isEmpty()) {
                $html .= '<p style="font-size: 11px;"><em>Situação final ainda não calculada para este período.</em></p>';

                continue;
            }

            $html .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">';
            $html .= '<thead><tr style="background-color: #f3f4f6;">';
            $html .= '<th style="border: 1px solid #d1d5db; padding: 6px; text-align: left; font-size: 11px;">Componente Curricular</th>';
            $html .= '<th style="border: 1px solid #d1d5db; padding: 6px; text-align: center; font-size: 11px; width: 90px;">Média Final</th>';
            $html .= '<th style="border: 1px solid #d1d5db; padding: 6px; text-align: center; font-size: 11px; width: 120px;">Situação</th>';
            $html .= '</tr></thead><tbody>';

            foreach ($registros as $registro) {
                // O resultado do exame final, quando existir, é o que vale — não a situação de Recuperação anterior a ele.
                $media = $registro->media_final_pos_exame ?? $registro->media_final;
                $situacao = $registro->situacao_final_pos_exame ?? $registro->situacao;

                $html .= '<tr>';
                $html .= '<td style="border: 1px solid #e5e7eb; padding: 5px; font-size: 11px;">'.htmlspecialchars($registro->disciplina?->nome ?? '-').'</td>';
                $html .= '<td style="border: 1px solid #e5e7eb; padding: 5px; text-align: center; font-size: 11px;">'.($media !== null ? number_format((float) $media, 1, ',', '.') : '-').'</td>';
                $html .= '<td style="border: 1px solid #e5e7eb; padding: 5px; text-align: center; font-size: 11px;">'.($situacao?->getLabel() ?? '-').'</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
        }

        return $html;
    }
}
