<?php

declare(strict_types=1);

namespace App\Services\Documentos;

use App\Contracts\ReageAoDocumentoAssinado;
use App\Enums\StatusAssinante;
use App\Enums\StatusDocumentoAssinatura;
use App\Models\CancelamentoMatricula;
use App\Models\DocumentoAssinatura;
use App\Models\Funcionario;
use App\Models\Matricula;
use App\Models\TemplateDocumento;
use App\Models\Unidade;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Assinatura eletrônica de documentos avulsos pelo Assinafy (Onda 69).
 *
 * Fluxo: o documento nasce de um modelo ({@see TemplateDocumento}) preenchido com os dados de um registro; vira PDF; vai
 * para o Assinafy com os assinantes; a situação volta por webhook (e por consulta periódica, que é quem manda: o Assinafy
 * não assina os envelopes dos webhooks); ao fim, o PDF certificado é guardado e o registro a que o documento está ligado
 * reage ({@see ReageAoDocumentoAssinado}).
 *
 * O envio de contratos de matrícula continua no `AssinafyService`, sem mudança.
 */
class AssinaturaDocumentoService
{
    /**
     * Situação do documento no Assinafy → situação local (ausente = não muda).
     *
     * @var array<string, StatusDocumentoAssinatura>
     */
    private const STATUS_DA_API = [
        'pending_signature' => StatusDocumentoAssinatura::Enviado,
        'ready' => StatusDocumentoAssinatura::Assinado,
        'certificating' => StatusDocumentoAssinatura::Assinado,
        'certificated' => StatusDocumentoAssinatura::Assinado,
        'rejected_by_signer' => StatusDocumentoAssinatura::Recusado,
        'rejected_by_user' => StatusDocumentoAssinatura::Cancelado,
        'expired' => StatusDocumentoAssinatura::Expirado,
        'failed' => StatusDocumentoAssinatura::Erro,
    ];

    /**
     * Eventos do webhook que mudam a situação e por isso pedem uma consulta ao Assinafy; os demais (visualização, e-mail
     * verificado...) só entram no histórico.
     *
     * @var list<string>
     */
    private const EVENTOS_QUE_CONSULTAM = [
        'signer_signed_document', 'signer_signed', 'document_ready', 'signature_completed', 'document_signed', 'document_completed',
        'signer_rejected_document', 'document_refused', 'user_rejected_document', 'document_processing_failed',
    ];

    public function __construct(
        private readonly MacrosDocumentoService $macros,
        private readonly AssinafyDocumentosClient $client,
    ) {}

    // --- Criar -------------------------------------------------------------------------------------------------------

    /**
     * Monta o documento (texto, PDF e assinantes) sem enviar nada ao Assinafy.
     *
     * @param  list<array{nome: string, email: string, papel?: ?string}>  $assinantes
     *
     * @throws \DomainException
     */
    public function criar(TemplateDocumento $template, Model $documentavel, array $assinantes, ?User $por = null, ?string $titulo = null, ?CarbonInterface $expiraEm = null): DocumentoAssinatura
    {
        if (! $template->is_ativo) {
            throw new \DomainException("O modelo \"{$template->nome}\" está inativo. Revise o texto e ative-o em Modelos de Documentos.");
        }

        $contexto = $template->contexto();
        $registro = $this->macros->registroDoContexto($documentavel, $contexto);

        if (! $registro) {
            throw new \DomainException("O modelo \"{$template->nome}\" usa dados de: {$contexto->getLabel()}. O registro escolhido não é desse tipo.");
        }

        $assinantes = $this->normalizarAssinantes($assinantes);

        $jaAberto = DocumentoAssinatura::query()
            ->abertos()
            ->where('template_documento_id', $template->id)
            ->where('documentavel_type', $documentavel::class)
            ->where('documentavel_id', $documentavel->getKey())
            ->exists();

        if ($jaAberto) {
            throw new \DomainException('Já existe um documento deste modelo em andamento para este registro. Cancele-o ou espere o desfecho antes de criar outro.');
        }

        $conteudo = $this->macros->preencher($template, $registro, $assinantes);
        $cabecalho = filled($template->cabecalho) ? $this->macros->substituir($template->cabecalho, $template, $registro, $assinantes) : null;
        $rodape = filled($template->rodape) ? $this->macros->substituir($template->rodape, $template, $registro, $assinantes) : null;
        $titulo = trim((string) $titulo) !== '' ? trim((string) $titulo) : $template->nome.' — '.$this->macros->rotulo($registro);

        return DB::transaction(function () use ($template, $documentavel, $registro, $assinantes, $por, $titulo, $expiraEm, $conteudo, $cabecalho, $rodape): DocumentoAssinatura {
            $documento = DocumentoAssinatura::create([
                'template_documento_id' => $template->id,
                'documentavel_type' => $documentavel::class,
                'documentavel_id' => $documentavel->getKey(),
                'titulo' => mb_substr($titulo, 0, 200),
                'conteudo' => $conteudo,
                'status' => StatusDocumentoAssinatura::Rascunho,
                'expira_em' => $expiraEm?->toDateString(),
                'criado_por_user_id' => $por?->id,
            ]);

            foreach ($assinantes as $posicao => $assinante) {
                $documento->assinantes()->create($assinante + ['ordem' => $posicao + 1]);
            }

            $this->gerarPdf($documento, $registro, $cabecalho, $rodape);
            $this->registrarLog($documento, $por, 'Documento criado para assinatura eletrônica.', ['modelo_id' => $template->id]);

            return $documento->refresh();
        });
    }

    /**
     * Confere a lista: nome e e-mail válidos, sem repetir o e-mail, de 1 a 10 pessoas.
     *
     * @param  list<array{nome?: ?string, email?: ?string, papel?: ?string}>  $assinantes
     * @return list<array{nome: string, email: string, papel: ?string}>
     *
     * @throws \DomainException
     */
    private function normalizarAssinantes(array $assinantes): array
    {
        $limpos = [];

        foreach ($assinantes as $assinante) {
            $nome = trim((string) ($assinante['nome'] ?? ''));
            $email = mb_strtolower(trim((string) ($assinante['email'] ?? '')));

            if ($nome === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new \DomainException('Todo assinante precisa de nome e de um e-mail válido'.($nome !== '' ? " (confira {$nome})" : '').'.');
            }

            if (isset($limpos[$email])) {
                throw new \DomainException("O e-mail {$email} aparece mais de uma vez na lista de assinantes.");
            }

            $limpos[$email] = ['nome' => mb_substr($nome, 0, 160), 'email' => mb_substr($email, 0, 160), 'papel' => filled($assinante['papel'] ?? null) ? mb_substr(trim((string) $assinante['papel']), 0, 40) : null];
        }

        if ($limpos === []) {
            throw new \DomainException('Informe ao menos um assinante com e-mail.');
        }

        if (count($limpos) > 10) {
            throw new \DomainException('O limite é de 10 assinantes por documento.');
        }

        return array_values($limpos);
    }

    private function gerarPdf(DocumentoAssinatura $documento, Model $registro, ?string $cabecalho, ?string $rodape): void
    {
        $unidade = $this->unidadeDoRegistro($registro);

        $pdf = Pdf::loadView('pdfs.documento_assinatura', [
            'titulo' => $documento->titulo,
            'escola' => $unidade?->instituicaoEnsino?->nome ?? $unidade?->nome ?? config('app.name'),
            'cabecalho' => $cabecalho,
            'conteudo' => $documento->conteudo,
            'rodape' => $rodape,
            'geradoEm' => now()->format('d/m/Y H:i'),
            'codigo' => $documento->id,
        ])->setPaper('a4');

        $bytes = $pdf->output();
        $caminho = "documentos_assinatura/{$documento->id}/original.pdf";

        Storage::disk('local')->put($caminho, $bytes);
        $documento->update(['arquivo_original_path' => $caminho, 'arquivo_original_hash' => hash('sha256', $bytes)]);
    }

    private function unidadeDoRegistro(Model $registro): ?Unidade
    {
        return match (true) {
            $registro instanceof Matricula => Matricula::query()->with('turma.serie.curso.unidade.instituicaoEnsino')->find($registro->id)?->turma?->serie?->curso?->unidade,
            $registro instanceof CancelamentoMatricula => Matricula::query()->with('turma.serie.curso.unidade.instituicaoEnsino')->find($registro->matricula_id)?->turma?->serie?->curso?->unidade,
            $registro instanceof Funcionario => Funcionario::query()->with('unidade.instituicaoEnsino')->find($registro->id)?->unidade,
            default => null,
        };
    }

    // --- Enviar ------------------------------------------------------------------------------------------------------

    /**
     * Manda o PDF para o Assinafy e pede a assinatura de todos. Se uma tentativa anterior parou no meio, continua dali
     * (o documento já enviado e os assinantes já cadastrados não se repetem).
     *
     * @throws \DomainException
     */
    public function enviar(DocumentoAssinatura $documento, ?User $por = null): DocumentoAssinatura
    {
        if (! $this->client->configurado()) {
            throw new \DomainException('Configuração do Assinafy pendente no servidor: defina ASSINAFY_API_KEY e ASSINAFY_ACCOUNT_ID no .env.');
        }

        $trava = Cache::lock("assinatura-documento:{$documento->id}", 120);

        if (! $trava->get()) {
            throw new \DomainException('Este documento já está sendo enviado. Aguarde alguns segundos.');
        }

        try {
            $documento = DocumentoAssinatura::query()->findOrFail($documento->id);

            if (! in_array($documento->status, [StatusDocumentoAssinatura::Rascunho, StatusDocumentoAssinatura::Erro], true)) {
                throw new \DomainException('Só dá para enviar um documento em rascunho ou com erro no envio.');
            }

            if (blank($documento->arquivo_original_path) || ! Storage::disk('local')->exists($documento->arquivo_original_path)) {
                throw new \DomainException('O PDF deste documento não foi encontrado. Cancele e crie o documento de novo.');
            }

            $assinantes = $documento->assinantes()->get();

            try {
                $documentoId = $documento->assinafy_id ?: $this->client->enviarPdf(
                    Storage::disk('local')->get($documento->arquivo_original_path),
                    Str::slug($documento->titulo).'-'.$documento->id.'.pdf',
                );

                // Guarda já: se uma etapa abaixo falhar, a nova tentativa continua deste documento.
                $documento->update(['assinafy_id' => $documentoId]);

                $assinanteIds = [];

                foreach ($assinantes as $assinante) {
                    $assinante->update(['assinafy_signer_id' => $assinante->assinafy_signer_id ?: $this->client->garantirAssinante($assinante->nome, $assinante->email)]);
                    $assinanteIds[] = (string) $assinante->assinafy_signer_id;
                }

                $this->client->aguardarProcessamento($documentoId);
                $enderecos = collect($this->client->solicitarAssinaturas($documentoId, $assinanteIds));
            } catch (\RuntimeException $e) {
                $log = $documento->assinafy_log ?? [];
                $log['erro'] = $e->getMessage();
                $documento->update(['status' => StatusDocumentoAssinatura::Erro, 'assinafy_log' => $log]);
                $this->registrarLog($documento, $por, 'Falha ao enviar o documento para assinatura.');

                throw new \DomainException('Não foi possível enviar ao Assinafy: '.$e->getMessage());
            }

            foreach ($assinantes as $assinante) {
                $endereco = $enderecos->firstWhere('signer_id', $assinante->assinafy_signer_id)
                    ?? $enderecos->first(fn (array $e): bool => str_contains(mb_strtolower(urldecode((string) ($e['url'] ?? ''))), $assinante->email));

                $assinante->update(['link_assinatura' => $endereco['url'] ?? null]);
            }

            $log = $documento->assinafy_log ?? [];
            unset($log['erro']);
            $log['environment_url'] = $this->client->urlPadrao();

            $documento->update([
                'status' => StatusDocumentoAssinatura::Enviado,
                'assinafy_status' => 'pending_signature',
                'enviado_em' => now(),
                'assinafy_log' => $log,
            ]);

            $this->registrarLog($documento, $por, 'Documento enviado para assinatura eletrônica.', ['assinantes' => $assinantes->count()]);

            return $documento->refresh();
        } finally {
            $trava->release();
        }
    }

    // --- Acompanhar --------------------------------------------------------------------------------------------------

    /**
     * Consulta o Assinafy e atualiza a situação do documento e de cada assinante.
     *
     * @throws \DomainException
     */
    public function atualizar(DocumentoAssinatura $documento): DocumentoAssinatura
    {
        if (blank($documento->assinafy_id)) {
            throw new \DomainException('Este documento ainda não foi enviado ao Assinafy.');
        }

        if (! $this->client->configurado()) {
            throw new \DomainException('Configuração do Assinafy pendente no servidor.');
        }

        $consulta = $this->client->consultar($documento->assinafy_id, $documento->assinafy_log['environment_url'] ?? null);

        if (! $consulta['ok']) {
            throw new \DomainException('Erro ao consultar o Assinafy: '.$consulta['erro']);
        }

        return $this->aplicarConsulta($documento, $consulta['dados'], $consulta['url']);
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function aplicarConsulta(DocumentoAssinatura $documento, array $dados, ?string $urlDoAmbiente): DocumentoAssinatura
    {
        $documento = DocumentoAssinatura::query()->findOrFail($documento->id);
        $statusApi = $dados['status'] ?? null;
        $daApi = $this->assinantesDaApi($dados);

        foreach ($documento->assinantes()->get() as $assinante) {
            $info = $daApi[mb_strtolower($assinante->email)] ?? null;

            if (! $info || $assinante->status === StatusAssinante::Assinou) {
                continue;
            }

            $assinante->update([
                'status' => $info['status'],
                'assinado_em' => $info['status'] === StatusAssinante::Assinou ? ($this->interpretarData($info['signed_at']) ?? now()) : $assinante->assinado_em,
            ]);
        }

        $log = $documento->assinafy_log ?? [];
        $log['api_status'] = $statusApi;
        $log['last_check'] = now()->toDateTimeString();

        if ($urlDoAmbiente) {
            $log['environment_url'] = $urlDoAmbiente;
        }

        $novo = self::STATUS_DA_API[$statusApi ?? ''] ?? null;

        // Cancelado aqui, assinado lá: a situação local não volta atrás, mas fica registrado para alguém olhar.
        if ($documento->status === StatusDocumentoAssinatura::Cancelado) {
            if ($novo === StatusDocumentoAssinatura::Assinado) {
                $log['assinado_apos_cancelamento'] = true;
            }

            $novo = null;
        }

        // Um documento assinado nunca volta para uma etapa anterior.
        if ($documento->status === StatusDocumentoAssinatura::Assinado) {
            $novo = null;
        }

        $mudancas = ['assinafy_status' => $statusApi ?? $documento->assinafy_status, 'assinafy_log' => $log];

        if ($novo !== null && $novo !== $documento->status) {
            $mudancas['status'] = $novo;

            if ($novo === StatusDocumentoAssinatura::Assinado) {
                $mudancas['concluido_em'] = $this->dataDaUltimaAssinatura($daApi) ?? now();

                // Documento completo: quem a consulta não detalhou (resposta sem a lista de assinantes) também assinou.
                $documento->assinantes()
                    ->where('status', StatusAssinante::Pendente->value)
                    ->update(['status' => StatusAssinante::Assinou->value, 'assinado_em' => $mudancas['concluido_em']]);
            }
        }

        $documento->update($mudancas);
        $documento = $documento->refresh();

        if ($documento->estaAssinado()) {
            $this->guardarCertificado($documento);
        }

        if (($mudancas['status'] ?? null) !== null) {
            $this->avisarCriador($documento);
            $this->registrarLog($documento, null, 'Situação da assinatura atualizada: '.$documento->status->value.'.');
        }

        return $documento->refresh();
    }

    /**
     * Baixa o PDF certificado assim que o Assinafy o libera e só então avisa o registro ligado ao documento.
     */
    private function guardarCertificado(DocumentoAssinatura $documento): void
    {
        if (blank($documento->arquivo_assinado_path)) {
            // Enquanto o certificado não fica pronto o Assinafy não entrega o arquivo: a rotina periódica tenta de novo.
            $bytes = $this->client->baixarCertificado($documento->assinafy_id, $documento->assinafy_log['environment_url'] ?? null);

            if ($bytes !== null) {
                $caminho = "documentos_assinatura/{$documento->id}/assinado.pdf";
                Storage::disk('local')->put($caminho, $bytes);
                $documento->update(['arquivo_assinado_path' => $caminho]);
            }
        }

        $documento = $documento->refresh();

        if (filled($documento->arquivo_assinado_path)) {
            $this->aplicarReacao($documento);
        }
    }

    /**
     * O registro ligado ao documento reage uma vez só (o log marca que já reagiu).
     */
    private function aplicarReacao(DocumentoAssinatura $documento): void
    {
        $log = $documento->assinafy_log ?? [];

        if (isset($log['reacao_aplicada_em'])) {
            return;
        }

        $documento->loadMissing('documentavel');

        if ($documento->documentavel instanceof ReageAoDocumentoAssinado) {
            $documento->documentavel->documentoAssinado($documento);
        }

        $log['reacao_aplicada_em'] = now()->toIso8601String();
        $documento->update(['assinafy_log' => $log]);
    }

    /**
     * Webhook do Assinafy. Devolve false quando o documento não é um dos nossos (o controlador então deixa o envio de
     * contratos tratar). A situação só muda depois de consultar a API: o envelope do webhook não é assinado.
     *
     * @param  array<string, mixed>  $payload
     */
    public function processarWebhook(array $payload): bool
    {
        $id = $payload['object']['id'] ?? $payload['document_id'] ?? null;

        if (blank($id)) {
            return false;
        }

        $documento = DocumentoAssinatura::query()->where('assinafy_id', (string) $id)->first();

        if (! $documento) {
            return false;
        }

        $evento = mb_strtolower((string) ($payload['event'] ?? ''));
        $log = $documento->assinafy_log ?? [];
        $log['webhook_last'] = ['event' => $evento, 'recebido_em' => now()->toDateTimeString()];
        $documento->update(['assinafy_log' => $log]);

        if (in_array($evento, self::EVENTOS_QUE_CONSULTAM, true) && $this->client->configurado()) {
            try {
                $this->atualizar($documento);
            } catch (\DomainException $e) {
                Log::warning('Webhook Assinafy: consulta do documento avulso falhou; a reconciliação resolve depois.', ['documento_id' => $documento->id, 'erro' => $e->getMessage()]);
            }
        }

        return true;
    }

    /**
     * Rotina periódica: consulta os documentos aguardando assinatura, repete o download do certificado que ainda não
     * veio e expira o que passou do prazo.
     *
     * @return array{consultados: int, expirados: int, certificados: int, falhas: int}
     */
    public function reconciliar(int $limite = 100): array
    {
        $resumo = ['consultados' => 0, 'expirados' => 0, 'certificados' => 0, 'falhas' => 0];

        $pendentes = DocumentoAssinatura::query()
            ->whereNotNull('assinafy_id')
            ->where(fn ($consulta) => $consulta
                ->where('status', StatusDocumentoAssinatura::Enviado->value)
                ->orWhere(fn ($q) => $q->where('status', StatusDocumentoAssinatura::Assinado->value)->whereNull('arquivo_assinado_path')->where('concluido_em', '>=', now()->subDays(7))))
            ->orderBy('updated_at')
            ->limit(max(1, $limite))
            ->get();

        foreach ($pendentes as $documento) {
            if ($documento->status === StatusDocumentoAssinatura::Enviado && $documento->expira_em && $documento->expira_em->isPast() && ! $documento->expira_em->isToday()) {
                $this->expirar($documento);
                $resumo['expirados']++;

                continue;
            }

            $semCertificado = blank($documento->arquivo_assinado_path);

            try {
                $atualizado = $this->atualizar($documento);
                $resumo['consultados']++;

                if ($semCertificado && filled($atualizado->arquivo_assinado_path)) {
                    $resumo['certificados']++;
                }
            } catch (\DomainException) {
                $resumo['falhas']++;
            }
        }

        return $resumo;
    }

    private function expirar(DocumentoAssinatura $documento): void
    {
        if ($documento->assinafy_id && $this->client->configurado()) {
            $this->client->excluir($documento->assinafy_id, $documento->assinafy_log['environment_url'] ?? null);
        }

        $documento->update(['status' => StatusDocumentoAssinatura::Expirado]);
        $this->avisarCriador($documento->refresh());
        $this->registrarLog($documento, null, 'Documento expirado sem todas as assinaturas.');
    }

    // --- Cancelar ----------------------------------------------------------------------------------------------------

    /**
     * Cancela o documento. Se já está no Assinafy, tira-o de lá (em melhor esforço) para o link de quem não assinou
     * deixar de valer.
     *
     * @throws \DomainException
     */
    public function cancelar(DocumentoAssinatura $documento, ?User $por = null): DocumentoAssinatura
    {
        $documento = DocumentoAssinatura::query()->findOrFail($documento->id);

        if (! $documento->emAndamento()) {
            throw new \DomainException('Só dá para cancelar um documento em andamento.');
        }

        $log = $documento->assinafy_log ?? [];

        if (filled($documento->assinafy_id) && $this->client->configurado()) {
            $log['removido_do_assinafy'] = $this->client->excluir($documento->assinafy_id, $log['environment_url'] ?? null);
        }

        $documento->update(['status' => StatusDocumentoAssinatura::Cancelado, 'assinafy_log' => $log]);
        $this->registrarLog($documento, $por, 'Documento cancelado.');

        return $documento->refresh();
    }

    // --- Apoio -------------------------------------------------------------------------------------------------------

    /**
     * Situação de cada assinante segundo o que o Assinafy devolve: e-mail (minúsculo) => status e data.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, array{status: StatusAssinante, signed_at: mixed}>
     */
    private function assinantesDaApi(array $dados): array
    {
        $lista = $dados['assignment']['signers']
            ?? $dados['assignment']['summary']['signers']
            ?? $dados['signers']
            ?? $dados['assignment']['signing_urls']
            ?? [];

        $resultado = [];

        foreach ($lista as $assinante) {
            $email = ! empty($assinante['email']) ? mb_strtolower(trim((string) $assinante['email'])) : null;

            if (! $email && ! empty($assinante['url'])) {
                parse_str((string) (parse_url((string) $assinante['url'], PHP_URL_QUERY) ?? ''), $consulta);
                $email = ! empty($consulta['email']) ? mb_strtolower(trim((string) $consulta['email'])) : null;
            }

            if (! $email) {
                continue;
            }

            $situacao = mb_strtolower((string) ($assinante['status'] ?? ''));
            $assinou = ($assinante['completed'] ?? false) === true
                || ($assinante['signed'] ?? false) === true
                || in_array($situacao, ['signed', 'completed', 'signer_signed_document', 'signer_signed'], true);
            $recusou = in_array($situacao, ['refused', 'rejected', 'declined'], true);

            $resultado[$email] = [
                'status' => $assinou ? StatusAssinante::Assinou : ($recusou ? StatusAssinante::Recusou : StatusAssinante::Pendente),
                'signed_at' => $assinante['signed_at'] ?? $assinante['completed_at'] ?? null,
            ];
        }

        return $resultado;
    }

    /**
     * @param  array<string, array{status: StatusAssinante, signed_at: mixed}>  $daApi
     */
    private function dataDaUltimaAssinatura(array $daApi): ?Carbon
    {
        $datas = collect($daApi)
            ->filter(fn (array $info): bool => $info['status'] === StatusAssinante::Assinou)
            ->map(fn (array $info): ?Carbon => $this->interpretarData($info['signed_at']))
            ->filter();

        return $datas->isEmpty() ? null : $datas->max();
    }

    private function interpretarData(mixed $valor): ?Carbon
    {
        if (blank($valor)) {
            return null;
        }

        try {
            return (is_numeric($valor) ? Carbon::createFromTimestamp((int) $valor) : Carbon::parse($valor))->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function avisarCriador(DocumentoAssinatura $documento): void
    {
        $usuario = $documento->criado_por_user_id ? User::query()->find($documento->criado_por_user_id) : null;

        if (! $usuario) {
            return;
        }

        $titulo = match ($documento->status) {
            StatusDocumentoAssinatura::Assinado => 'Documento assinado por todos',
            StatusDocumentoAssinatura::Recusado => 'Assinatura recusada',
            StatusDocumentoAssinatura::Expirado => 'Documento expirou sem todas as assinaturas',
            default => null,
        };

        if ($titulo === null) {
            return;
        }

        try {
            Notification::make()
                ->title($titulo)
                ->body($documento->titulo)
                ->icon($documento->status->getIcon())
                ->iconColor($documento->estaAssinado() ? 'success' : 'danger')
                ->sendToDatabase($usuario);
        } catch (\Throwable $e) {
            Log::warning('Falha ao avisar o criador do documento assinado.', ['documento_id' => $documento->id, 'erro' => $e->getMessage()]);
        }
    }

    /**
     * Log sem o texto do documento e sem e-mails de assinantes.
     *
     * @param  array<string, mixed>  $propriedades
     */
    private function registrarLog(DocumentoAssinatura $documento, ?User $por, string $mensagem, array $propriedades = []): void
    {
        $registro = activity('assinaturas')
            ->performedOn($documento)
            ->withProperties(['status' => $documento->status->value] + $propriedades);

        if ($por) {
            $registro->causedBy($por);
        }

        $registro->log($mensagem);
    }
}
