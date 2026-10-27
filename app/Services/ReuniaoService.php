<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConviteReuniao;
use App\Enums\StatusEncaminhamento;
use App\Enums\StatusReuniao;
use App\Enums\TipoReuniao;
use App\Exceptions\ReservaEspacoIndisponivelException;
use App\Exceptions\ReuniaoInvalidaException;
use App\Filament\Resources\Reunioes\ReuniaoResource;
use App\Models\DocumentoAssinatura;
use App\Models\EtapaAvaliativa;
use App\Models\Reuniao;
use App\Models\ReuniaoAdendo;
use App\Models\ReuniaoEncaminhamento;
use App\Models\ReuniaoParticipante;
use App\Models\User;
use App\Services\Documentos\AssinaturaDocumentoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * Reuniões internas e suas atas (Ondas 27 e 32). Uma reunião é agendada (com sala, participantes e pauta), acontece, tem a ata
 * escrita e fechada, e deixa encaminhamentos com responsável e prazo. A ata fechada não muda: a correção entra como adendo.
 *
 * A reunião sigilosa (a devolutiva individual sempre) só é vista por quem organiza e por quem participa, e não aparece na reserva
 * do espaço nem nos avisos com o assunto.
 */
class ReuniaoService
{
    public function __construct(
        private readonly ReservaEspacoService $reservas,
        private readonly AssinaturaDocumentoService $assinaturas,
    ) {}

    /**
     * @param  array{tipo: TipoReuniao|string, titulo: string, inicio_em: mixed, fim_em?: mixed, local?: ?string, sala_id?: int|string|null, turma_id?: int|string|null, etapa_avaliativa_id?: int|string|null, pauta?: ?string, confidencial?: bool, organizador_user_id?: int|string|null, participantes?: array<int, int|string|array{user_id: int|string, papel?: ?string}>}  $dados
     *
     * @throws ReuniaoInvalidaException
     */
    public function agendar(array $dados, User $por): Reuniao
    {
        $tipo = $dados['tipo'] instanceof TipoReuniao ? $dados['tipo'] : TipoReuniao::from((string) $dados['tipo']);
        [$inicio, $fim] = $this->janela($tipo, $dados['inicio_em'] ?? null, $dados['fim_em'] ?? null);

        $titulo = trim((string) ($dados['titulo'] ?? ''));

        if ($titulo === '') {
            throw ReuniaoInvalidaException::com('Dê um título à reunião.');
        }

        $organizadorId = (int) ($dados['organizador_user_id'] ?? $por->id);
        $participantes = $this->participantesNormalizados($dados['participantes'] ?? [], $organizadorId);
        $this->conferirDoTipo($tipo, $dados, $participantes, $organizadorId, null);

        $reuniao = DB::transaction(function () use ($dados, $tipo, $titulo, $inicio, $fim, $organizadorId, $participantes, $por): Reuniao {
            $reuniao = Reuniao::create([
                'tipo' => $tipo,
                'titulo' => $titulo,
                'status' => StatusReuniao::Agendada,
                'confidencial' => $tipo->sempreConfidencial() || (bool) ($dados['confidencial'] ?? false),
                'inicio_em' => $inicio,
                'fim_em' => $fim,
                'local' => filled($dados['local'] ?? null) ? trim((string) $dados['local']) : null,
                'sala_id' => $dados['sala_id'] ?? null,
                'turma_id' => $tipo->temAlunos() ? ($dados['turma_id'] ?? null) : null,
                'etapa_avaliativa_id' => $tipo->temAlunos() ? ($dados['etapa_avaliativa_id'] ?? null) : null,
                'organizador_user_id' => $organizadorId,
                'pauta' => filled($dados['pauta'] ?? null) ? trim((string) $dados['pauta']) : null,
            ]);

            foreach ($participantes as $userId => $papel) {
                ReuniaoParticipante::create([
                    'reuniao_id' => $reuniao->id,
                    'user_id' => $userId,
                    'papel' => $papel,
                    // Quem organiza não precisa aceitar o próprio convite.
                    'convite' => $userId === $organizadorId ? ConviteReuniao::Aceito : ConviteReuniao::Pendente,
                ]);
            }

            $this->sincronizarReserva($reuniao, $por);

            if ($reuniao->ehConselho()) {
                app(ConselhoClasseService::class)->montarPauta($reuniao);
            }

            return $reuniao->refresh();
        });

        $this->avisar(
            $this->usuarios(array_keys($participantes))->reject(fn (User $u) => $u->id === $organizadorId),
            'Convite para reunião',
            $this->descricaoDoConvite($reuniao),
            $reuniao,
        );

        return $reuniao;
    }

    /**
     * Muda data, horário, sala, pauta ou participantes de uma reunião que ainda não aconteceu. Quem já foi convidado mantém a
     * resposta, a menos que o horário mude: aí todos precisam responder de novo.
     *
     * @param  array<string, mixed>  $dados  mesmas chaves de {@see self::agendar()}; tipo, turma e etapa não mudam
     *
     * @throws ReuniaoInvalidaException
     */
    public function reagendar(Reuniao $reuniao, array $dados, User $por): Reuniao
    {
        if (! $reuniao->estaAgendada()) {
            throw ReuniaoInvalidaException::com('Só uma reunião ainda agendada pode ser alterada.');
        }

        [$inicio, $fim] = $this->janela($reuniao->tipo, $dados['inicio_em'] ?? $reuniao->inicio_em, $dados['fim_em'] ?? null);
        $titulo = trim((string) ($dados['titulo'] ?? $reuniao->titulo));

        if ($titulo === '') {
            throw ReuniaoInvalidaException::com('Dê um título à reunião.');
        }

        $participantes = $this->participantesNormalizados($dados['participantes'] ?? [], (int) $reuniao->organizador_user_id);
        $this->conferirDoTipo($reuniao->tipo, $dados, $participantes, (int) $reuniao->organizador_user_id, $reuniao);

        $mudouOHorario = ! $reuniao->inicio_em->eq($inicio) || ! $reuniao->fim_em?->eq($fim);
        $convidados = [];

        DB::transaction(function () use ($reuniao, $dados, $titulo, $inicio, $fim, $participantes, $mudouOHorario, $por, &$convidados): void {
            $reuniao->update([
                'titulo' => $titulo,
                'confidencial' => $reuniao->tipo->sempreConfidencial() || (bool) ($dados['confidencial'] ?? $reuniao->confidencial),
                'inicio_em' => $inicio,
                'fim_em' => $fim,
                'local' => array_key_exists('local', $dados) ? (filled($dados['local']) ? trim((string) $dados['local']) : null) : $reuniao->local,
                'sala_id' => array_key_exists('sala_id', $dados) ? ($dados['sala_id'] ?: null) : $reuniao->sala_id,
                'pauta' => array_key_exists('pauta', $dados) ? (filled($dados['pauta']) ? trim((string) $dados['pauta']) : null) : $reuniao->pauta,
            ]);

            $atuais = $reuniao->participantes()->get()->keyBy('user_id');

            foreach ($atuais as $userId => $participante) {
                if (! array_key_exists($userId, $participantes)) {
                    $participante->delete();
                }
            }

            foreach ($participantes as $userId => $papel) {
                $existente = $atuais->get($userId);

                if ($existente === null) {
                    ReuniaoParticipante::create([
                        'reuniao_id' => $reuniao->id,
                        'user_id' => $userId,
                        'papel' => $papel,
                        'convite' => $userId === (int) $reuniao->organizador_user_id ? ConviteReuniao::Aceito : ConviteReuniao::Pendente,
                    ]);
                    $convidados[] = $userId;

                    continue;
                }

                $existente->update(['papel' => $papel]);
            }

            if ($mudouOHorario) {
                $reuniao->participantes()
                    ->where('user_id', '!=', $reuniao->organizador_user_id)
                    ->update(['convite' => ConviteReuniao::Pendente->value, 'respondido_em' => null]);
            }

            $this->sincronizarReserva($reuniao, $por);
        });

        $reuniao->refresh();
        $naoOrganizadores = fn (Collection $usuarios) => $usuarios->reject(fn (User $u) => $u->id === (int) $reuniao->organizador_user_id);

        if ($mudouOHorario) {
            $this->avisar(
                $naoOrganizadores($this->usuarios($reuniao->participantes()->pluck('user_id')->all())),
                'Reunião remarcada',
                $this->descricaoDoConvite($reuniao).' Confirme se você consegue participar.',
                $reuniao,
                'heroicon-o-calendar-days',
                'warning',
            );
        } elseif ($convidados !== []) {
            $this->avisar($naoOrganizadores($this->usuarios($convidados)), 'Convite para reunião', $this->descricaoDoConvite($reuniao), $reuniao);
        }

        return $reuniao;
    }

    /**
     * @throws ReuniaoInvalidaException
     */
    public function responderConvite(Reuniao $reuniao, User $user, ConviteReuniao $resposta): ReuniaoParticipante
    {
        if ($resposta === ConviteReuniao::Pendente) {
            throw ReuniaoInvalidaException::com('Responda se vai participar ou não.');
        }

        if (! $reuniao->estaAgendada()) {
            throw ReuniaoInvalidaException::com('Esta reunião não está mais aberta a respostas.');
        }

        $participante = $reuniao->participantes()->where('user_id', $user->id)->first();

        if ($participante === null) {
            throw ReuniaoInvalidaException::com('Você não está na lista desta reunião.');
        }

        $participante->update(['convite' => $resposta, 'respondido_em' => now()]);

        if ($resposta === ConviteReuniao::Recusado && $reuniao->organizador_user_id && (int) $reuniao->organizador_user_id !== $user->id) {
            $this->avisar(
                $this->usuarios([$reuniao->organizador_user_id]),
                'Convite recusado',
                "{$user->name} não poderá participar de \"{$this->tituloVisivel($reuniao)}\".",
                $reuniao,
                'heroicon-o-user-minus',
                'danger',
            );
        }

        return $participante->refresh();
    }

    /**
     * Quem estava na reunião. A chave é o id do usuário; quem não aparece fica como ausente.
     *
     * @param  array<int|string, bool>  $presentes
     *
     * @throws ReuniaoInvalidaException
     */
    public function registrarPresenca(Reuniao $reuniao, array $presentes): void
    {
        $this->exigirEmAndamento($reuniao);

        DB::transaction(function () use ($reuniao, $presentes): void {
            foreach ($reuniao->participantes()->get() as $participante) {
                $participante->update(['presente' => (bool) ($presentes[$participante->user_id] ?? false)]);
            }
        });
    }

    /**
     * @param  array<int|string, bool>|null  $presentes
     *
     * @throws ReuniaoInvalidaException
     */
    public function marcarRealizada(Reuniao $reuniao, User $por, ?array $presentes = null): Reuniao
    {
        if (! $reuniao->estaAgendada()) {
            throw ReuniaoInvalidaException::com('Só uma reunião agendada pode ser marcada como realizada.');
        }

        if ($reuniao->inicio_em->isFuture()) {
            throw ReuniaoInvalidaException::com('A reunião ainda não começou.');
        }

        DB::transaction(function () use ($reuniao, $presentes): void {
            if ($presentes !== null) {
                $this->registrarPresenca($reuniao, $presentes);
            }

            $reuniao->update(['status' => StatusReuniao::Realizada, 'realizada_em' => now()]);
        });

        return $reuniao->refresh();
    }

    /**
     * @throws ReuniaoInvalidaException
     */
    public function salvarAta(Reuniao $reuniao, ?string $texto): Reuniao
    {
        $this->exigirEmAndamento($reuniao);

        $reuniao->update(['ata' => filled($texto) ? trim((string) $texto) : null]);

        return $reuniao->refresh();
    }

    /**
     * Fecha a ata: depois disso ela não muda mais, só recebe adendos. Exige alguém presente, o texto da ata (no conselho de classe a
     * pauta por aluno faz as vezes dele) e, no conselho, todos os alunos deliberados.
     *
     * @throws ReuniaoInvalidaException
     */
    public function fecharAta(Reuniao $reuniao, User $por): Reuniao
    {
        if (! $reuniao->foiRealizada()) {
            throw ReuniaoInvalidaException::com($reuniao->estaAgendada()
                ? 'Marque a reunião como realizada antes de fechar a ata.'
                : 'Esta reunião não tem ata em aberto para fechar.');
        }

        if (! $reuniao->participantes()->where('presente', true)->exists()) {
            throw ReuniaoInvalidaException::com('Registre quem esteve presente antes de fechar a ata.');
        }

        if ($reuniao->ehConselho()) {
            $pendentes = app(ConselhoClasseService::class)->pendentesDeDeliberacao($reuniao);

            if ($pendentes > 0) {
                throw ReuniaoInvalidaException::com($pendentes === 1
                    ? 'Falta deliberar sobre 1 aluno antes de fechar a ata.'
                    : "Faltam deliberações sobre {$pendentes} alunos antes de fechar a ata.");
            }
        } elseif (blank($reuniao->ata)) {
            throw ReuniaoInvalidaException::com('Escreva a ata antes de fechá-la.');
        }

        $reuniao->update([
            'status' => StatusReuniao::AtaFechada,
            'ata_fechada_em' => now(),
            'ata_fechada_por_user_id' => $por->id,
        ]);

        $this->avisar(
            $this->usuarios($reuniao->participantes()->pluck('user_id')->all())->reject(fn (User $u) => $u->id === $por->id),
            'Ata de reunião fechada',
            "A ata de \"{$this->tituloVisivel($reuniao)}\" foi fechada e está disponível.",
            $reuniao,
            'heroicon-o-document-check',
            'success',
        );

        return $reuniao->refresh();
    }

    /**
     * @throws ReuniaoInvalidaException
     */
    public function registrarAdendo(Reuniao $reuniao, string $texto, User $por): ReuniaoAdendo
    {
        if (! $reuniao->ataEstaFechada()) {
            throw ReuniaoInvalidaException::com('O adendo é para ata já fechada. Enquanto ela está aberta, edite o texto da ata.');
        }

        $texto = trim($texto);

        if ($texto === '') {
            throw ReuniaoInvalidaException::com('Escreva o texto do adendo.');
        }

        return ReuniaoAdendo::create(['reuniao_id' => $reuniao->id, 'texto' => $texto, 'user_id' => $por->id]);
    }

    /**
     * Cancela uma reunião agendada, libera a sala e avisa quem foi convidado.
     *
     * @throws ReuniaoInvalidaException
     */
    public function cancelar(Reuniao $reuniao, User $por, string $motivo): Reuniao
    {
        if (! $reuniao->estaAgendada()) {
            throw ReuniaoInvalidaException::com('Só uma reunião ainda agendada pode ser cancelada.');
        }

        $motivo = trim($motivo);

        if ($motivo === '') {
            throw ReuniaoInvalidaException::com('Diga por que a reunião foi cancelada.');
        }

        DB::transaction(function () use ($reuniao, $por, $motivo): void {
            $reserva = $reuniao->reservaEspaco;

            if ($reserva !== null && ! $reserva->estaCancelada() && $reserva->aindaNaoOcorreu()) {
                $this->reservas->cancelar($reserva, $por, "Reunião cancelada: {$motivo}");
            }

            $reuniao->update(['status' => StatusReuniao::Cancelada, 'motivo_cancelamento' => $motivo]);
        });

        $this->avisar(
            $this->usuarios($reuniao->participantes()->pluck('user_id')->all())->reject(fn (User $u) => $u->id === $por->id),
            'Reunião cancelada',
            "\"{$this->tituloVisivel($reuniao)}\", marcada para {$reuniao->inicio_em->format('d/m/Y \à\s H:i')}, foi cancelada. Motivo: {$motivo}",
            $reuniao,
            'heroicon-o-x-circle',
            'danger',
        );

        return $reuniao->refresh();
    }

    // ─── Encaminhamentos ─────────────────────────────────────────────

    /**
     * @param  array{descricao: string, responsavel_user_id?: int|string|null, prazo?: mixed, matricula_id?: int|string|null, reuniao_aluno_id?: int|string|null}  $dados
     *
     * @throws ReuniaoInvalidaException
     */
    public function adicionarEncaminhamento(Reuniao $reuniao, array $dados, User $por): ReuniaoEncaminhamento
    {
        if ($reuniao->estaCancelada() || $reuniao->ataEstaFechada()) {
            throw ReuniaoInvalidaException::com('A ata já está fechada ou a reunião foi cancelada: registre o novo combinado num adendo.');
        }

        $descricao = trim((string) ($dados['descricao'] ?? ''));

        if ($descricao === '') {
            throw ReuniaoInvalidaException::com('Diga o que foi combinado.');
        }

        $prazo = filled($dados['prazo'] ?? null) ? Carbon::parse($dados['prazo'])->startOfDay() : null;

        if ($prazo !== null && $prazo->lt(today())) {
            throw ReuniaoInvalidaException::com('O prazo do encaminhamento não pode ser uma data que já passou.');
        }

        $encaminhamento = ReuniaoEncaminhamento::create([
            'reuniao_id' => $reuniao->id,
            'reuniao_aluno_id' => $dados['reuniao_aluno_id'] ?? null,
            'matricula_id' => $dados['matricula_id'] ?? null,
            'descricao' => Str::limit($descricao, 300, ''),
            'responsavel_user_id' => $dados['responsavel_user_id'] ?? null,
            'prazo' => $prazo?->toDateString(),
            'status' => StatusEncaminhamento::Aberto,
        ]);

        if ($encaminhamento->responsavel_user_id && (int) $encaminhamento->responsavel_user_id !== $por->id) {
            $this->avisar(
                $this->usuarios([$encaminhamento->responsavel_user_id]),
                'Novo encaminhamento para você',
                $encaminhamento->descricao.($prazo ? " Prazo: {$prazo->format('d/m/Y')}." : ''),
                $reuniao,
                'heroicon-o-clipboard-document-check',
                'warning',
            );
        }

        return $encaminhamento;
    }

    /**
     * @throws ReuniaoInvalidaException
     */
    public function concluirEncaminhamento(ReuniaoEncaminhamento $encaminhamento, User $por, ?string $observacao = null): ReuniaoEncaminhamento
    {
        if (! $encaminhamento->estaAberto()) {
            throw ReuniaoInvalidaException::com('Este encaminhamento já foi encerrado.');
        }

        $encaminhamento->update([
            'status' => StatusEncaminhamento::Feito,
            'concluido_em' => now(),
            'concluido_por_user_id' => $por->id,
            'observacao_conclusao' => filled($observacao) ? trim((string) $observacao) : null,
        ]);

        return $encaminhamento->refresh();
    }

    /**
     * @throws ReuniaoInvalidaException
     */
    public function cancelarEncaminhamento(ReuniaoEncaminhamento $encaminhamento, User $por, string $motivo): ReuniaoEncaminhamento
    {
        if (! $encaminhamento->estaAberto()) {
            throw ReuniaoInvalidaException::com('Este encaminhamento já foi encerrado.');
        }

        $motivo = trim($motivo);

        if ($motivo === '') {
            throw ReuniaoInvalidaException::com('Diga por que o encaminhamento deixou de valer.');
        }

        $encaminhamento->update([
            'status' => StatusEncaminhamento::Cancelado,
            'concluido_em' => now(),
            'concluido_por_user_id' => $por->id,
            'observacao_conclusao' => $motivo,
        ]);

        return $encaminhamento->refresh();
    }

    /**
     * Avisa, uma vez só, o responsável e a coordenação de cada encaminhamento que passou do prazo. A reunião sigilosa não leva o
     * aviso à coordenação: quem sabe do assunto é só quem organiza e o responsável.
     *
     * @return int quantos encaminhamentos foram avisados
     */
    public function lembrarEncaminhamentosVencidos(): int
    {
        $vencidos = ReuniaoEncaminhamento::query()
            ->vencidos()
            ->whereNull('vencimento_avisado_em')
            ->whereHas('reuniao', fn (Builder $q) => $q->where('status', '!=', StatusReuniao::Cancelada->value))
            ->with('reuniao')
            ->get();

        $coordenacao = $this->coordenacao();

        foreach ($vencidos as $encaminhamento) {
            $reuniao = $encaminhamento->reuniao;
            $destinatarios = $this->usuarios(array_filter([$encaminhamento->responsavel_user_id, $reuniao->organizador_user_id]));

            if (! $reuniao->ehSigilosa()) {
                $destinatarios = $destinatarios->merge($coordenacao);
            }

            $this->avisar(
                $destinatarios,
                'Encaminhamento vencido',
                "{$encaminhamento->descricao} (prazo {$encaminhamento->prazo->format('d/m/Y')}, reunião \"{$this->tituloVisivel($reuniao)}\").",
                $reuniao,
                'heroicon-o-exclamation-triangle',
                'danger',
            );

            $encaminhamento->forceFill(['vencimento_avisado_em' => now()])->save();
        }

        return $vencidos->count();
    }

    // ─── Ata em PDF e assinatura ─────────────────────────────────────

    public function pdfDaAta(Reuniao $reuniao): DomPdf
    {
        $reuniao->loadMissing([
            'organizador', 'turma', 'etapaAvaliativa', 'sala',
            'participantes.user', 'alunos.matricula.pessoa', 'alunos.encaminhamentos.responsavel',
            'encaminhamentos.responsavel', 'adendos.autor', 'ataFechadaPor',
        ]);

        return Pdf::loadView('pdfs.ata-reuniao', ['reuniao' => $reuniao, 'deliberacoes' => config('reunioes.deliberacoes', [])])
            ->setPaper('a4', 'portrait');
    }

    /**
     * Por que a ata ainda não pode ir para a assinatura eletrônica (null = pode).
     */
    public function impedimentoDeAssinatura(Reuniao $reuniao): ?string
    {
        if ($reuniao->temCopiaAssinada()) {
            return 'A ata já tem uma cópia assinada.';
        }

        if (! $reuniao->ataEstaFechada()) {
            return 'Feche a ata antes de enviá-la para assinatura.';
        }

        if ($reuniao->ehSigilosa()) {
            return 'A ata de uma reunião sigilosa não segue para a assinatura eletrônica: o documento ficaria visível a toda a equipe de assinaturas.';
        }

        if ($reuniao->documentosAssinatura()->abertos()->exists()) {
            return 'Esta ata já tem uma assinatura em andamento.';
        }

        return null;
    }

    /**
     * Quem esteve presente, com o e-mail do usuário: a sugestão de assinantes da ata.
     *
     * @return array{assinantes: list<array{nome: string, email: string, papel: string}>, sem_email: list<string>}
     */
    public function signatariosSugeridos(Reuniao $reuniao): array
    {
        $assinantes = [];
        $semEmail = [];

        $reuniao->loadMissing('participantes.user');

        foreach ($reuniao->participantes->where('presente', true) as $participante) {
            $usuario = $participante->user;

            if ($usuario === null) {
                continue;
            }

            if (filter_var($usuario->email, FILTER_VALIDATE_EMAIL) === false) {
                $semEmail[] = $usuario->name;

                continue;
            }

            $assinantes[] = [
                'nome' => $usuario->name,
                'email' => mb_strtolower(trim((string) $usuario->email)),
                'papel' => $participante->papel === ReuniaoParticipante::PAPEL_PARTICIPANTE ? 'Participante' : ucfirst($participante->papel),
            ];
        }

        return ['assinantes' => $assinantes, 'sem_email' => $semEmail];
    }

    /**
     * @param  list<array{nome: string, email: string, papel?: ?string}>  $assinantes
     *
     * @throws \DomainException
     */
    public function enviarAtaParaAssinatura(Reuniao $reuniao, array $assinantes, User $por, ?CarbonInterface $expiraEm = null): DocumentoAssinatura
    {
        if ($motivo = $this->impedimentoDeAssinatura($reuniao)) {
            throw new ReuniaoInvalidaException([$motivo]);
        }

        return $this->assinaturas->criarDePdf(
            $reuniao,
            $this->pdfDaAta($reuniao)->output(),
            'Ata — '.$reuniao->titulo.' — '.$reuniao->inicio_em->format('d/m/Y'),
            $assinantes,
            $por,
            $expiraEm,
        );
    }

    public function caminhoDaCopiaAssinada(Reuniao $reuniao): ?string
    {
        return $reuniao->temCopiaAssinada() && Storage::disk('local')->exists((string) $reuniao->arquivo_assinado_path)
            ? (string) $reuniao->arquivo_assinado_path
            : null;
    }

    // ─── Consultas ───────────────────────────────────────────────────

    /**
     * Reuniões realizadas cuja ata ainda não foi fechada.
     *
     * @return Builder<Reuniao>
     */
    public function atasPorFechar(): Builder
    {
        return Reuniao::query()->where('status', StatusReuniao::Realizada->value);
    }

    /**
     * Dia em que a ata em aberto passa a ser cobrada.
     */
    public function prazoDaAta(Reuniao $reuniao): ?CarbonInterface
    {
        return $reuniao->realizada_em?->copy()->addDays((int) config('reunioes.ata_prazo_dias', 5))->endOfDay();
    }

    /**
     * A ata está em aberto há mais dias do que o combinado.
     */
    public function ataAtrasada(Reuniao $reuniao): bool
    {
        $prazo = $this->prazoDaAta($reuniao);

        return $reuniao->foiRealizada() && $prazo !== null && $prazo->isPast();
    }

    // ─── Internos ────────────────────────────────────────────────────

    /**
     * @return array{0: Carbon, 1: Carbon}
     *
     * @throws ReuniaoInvalidaException
     */
    private function janela(TipoReuniao $tipo, mixed $inicio, mixed $fim): array
    {
        if (blank($inicio)) {
            throw ReuniaoInvalidaException::com('Informe a data e a hora de início.');
        }

        $inicio = Carbon::parse($inicio)->startOfMinute();
        $fim = filled($fim)
            ? Carbon::parse($fim)->startOfMinute()
            : $inicio->copy()->addMinutes((int) (config("reunioes.duracao_padrao_minutos.{$tipo->value}") ?? 60));

        if ($fim->lte($inicio)) {
            throw ReuniaoInvalidaException::com('O horário final precisa ser depois do início.');
        }

        if (! $fim->isSameDay($inicio)) {
            throw ReuniaoInvalidaException::com('A reunião precisa começar e terminar no mesmo dia.');
        }

        return [$inicio, $fim];
    }

    /**
     * Organizador sempre entra, uma única vez, e o conselho tem no máximo um presidente.
     *
     * @param  array<int, int|string|array{user_id: int|string, papel?: ?string}>  $participantes
     * @return array<int, string> id do usuário => papel
     *
     * @throws ReuniaoInvalidaException
     */
    private function participantesNormalizados(array $participantes, int $organizadorId): array
    {
        $resultado = [];

        foreach ($participantes as $item) {
            $userId = (int) (is_array($item) ? ($item['user_id'] ?? 0) : $item);
            $papel = is_array($item) ? (string) ($item['papel'] ?? ReuniaoParticipante::PAPEL_PARTICIPANTE) : ReuniaoParticipante::PAPEL_PARTICIPANTE;

            if ($userId <= 0) {
                continue;
            }

            $resultado[$userId] = $papel === ReuniaoParticipante::PAPEL_PRESIDENTE ? ReuniaoParticipante::PAPEL_PRESIDENTE : ReuniaoParticipante::PAPEL_PARTICIPANTE;
        }

        $resultado[$organizadorId] = ReuniaoParticipante::PAPEL_ORGANIZADOR;

        if (count(array_filter($resultado, fn (string $papel) => $papel === ReuniaoParticipante::PAPEL_PRESIDENTE)) > 1) {
            throw ReuniaoInvalidaException::com('Uma reunião tem um presidente só.');
        }

        $existentes = User::query()->whereIn('id', array_keys($resultado))->pluck('id')->all();

        return array_intersect_key($resultado, array_flip($existentes));
    }

    /**
     * @param  array<string, mixed>  $dados
     * @param  array<int, string>  $participantes
     *
     * @throws ReuniaoInvalidaException
     */
    private function conferirDoTipo(TipoReuniao $tipo, array $dados, array $participantes, int $organizadorId, ?Reuniao $atual): void
    {
        if ($tipo === TipoReuniao::Devolutiva && count($participantes) !== 2) {
            throw ReuniaoInvalidaException::com('A devolutiva individual é entre quem organiza e uma única pessoa.');
        }

        if (! $tipo->temAlunos()) {
            return;
        }

        $turmaId = $atual?->turma_id ?? ($dados['turma_id'] ?? null);
        $etapaId = $atual?->etapa_avaliativa_id ?? ($dados['etapa_avaliativa_id'] ?? null);

        if (blank($turmaId) || blank($etapaId)) {
            throw ReuniaoInvalidaException::com('O conselho de classe precisa de turma e de etapa avaliativa.');
        }

        if ($atual === null && ! EtapaAvaliativa::query()->whereKey($etapaId)->where('turma_id', $turmaId)->exists()) {
            throw ReuniaoInvalidaException::com('A etapa avaliativa escolhida não é desta turma.');
        }

        $jaExiste = Reuniao::query()
            ->doTipo(TipoReuniao::ConselhoClasse)
            ->where('turma_id', $turmaId)
            ->where('etapa_avaliativa_id', $etapaId)
            ->where('status', '!=', StatusReuniao::Cancelada->value)
            ->when($atual, fn (Builder $q) => $q->whereKeyNot($atual->id))
            ->exists();

        if ($jaExiste) {
            throw ReuniaoInvalidaException::com('Esta turma já tem conselho de classe marcado ou realizado para a etapa.');
        }
    }

    /**
     * A sala da reunião vira uma reserva do espaço, para ninguém ocupá-la no mesmo horário. A reserva de reunião sigilosa não
     * mostra o assunto: toda a escola enxerga as reservas.
     *
     * @throws ReuniaoInvalidaException
     */
    private function sincronizarReserva(Reuniao $reuniao, User $por): void
    {
        $reserva = $reuniao->reserva_espaco_id ? $reuniao->reservaEspaco()->first() : null;
        $reserva = $reserva && ! $reserva->estaCancelada() ? $reserva : null;

        try {
            if (! $reuniao->sala_id) {
                if ($reserva && $reserva->aindaNaoOcorreu()) {
                    $this->reservas->cancelar($reserva, $por, 'A reunião deixou de usar o espaço.');
                }

                $reuniao->forceFill(['reserva_espaco_id' => null])->save();

                return;
            }

            $dados = [
                'sala_id' => $reuniao->sala_id,
                'data' => $reuniao->inicio_em->toDateString(),
                'hora_inicio' => $reuniao->inicio_em->format('H:i'),
                'hora_fim' => $reuniao->fim_em->format('H:i'),
                'motivo' => $reuniao->ehSigilosa() ? 'Reunião' : Str::limit('Reunião: '.$reuniao->titulo, 120, ''),
                'descricao' => $reuniao->ehSigilosa() ? null : $reuniao->tipo->getLabel(),
                'turma_id' => $reuniao->ehSigilosa() ? null : $reuniao->turma_id,
            ];

            $reserva = $reserva ? $this->reservas->atualizar($reserva, $dados, $por) : $this->reservas->solicitar($dados, $por);
        } catch (ReservaEspacoIndisponivelException $e) {
            throw new ReuniaoInvalidaException($e->mensagens());
        }

        $reuniao->forceFill([
            'reserva_espaco_id' => $reserva->id,
            'local' => filled($reuniao->local) ? $reuniao->local : $reuniao->sala()->value('nome'),
        ])->save();
    }

    /**
     * @throws ReuniaoInvalidaException
     */
    private function exigirEmAndamento(Reuniao $reuniao): void
    {
        if ($reuniao->ataEstaFechada()) {
            throw ReuniaoInvalidaException::com('A ata já está fechada e não muda mais. Use um adendo.');
        }

        if ($reuniao->estaCancelada()) {
            throw ReuniaoInvalidaException::com('Esta reunião foi cancelada.');
        }
    }

    /**
     * O título que pode aparecer num aviso: a reunião sigilosa não diz o assunto.
     */
    private function tituloVisivel(Reuniao $reuniao): string
    {
        return $reuniao->ehSigilosa() ? $reuniao->tipo->getLabel() : $reuniao->titulo;
    }

    private function descricaoDoConvite(Reuniao $reuniao): string
    {
        $onde = filled($reuniao->local) ? " em {$reuniao->local}" : '';

        return "{$this->tituloVisivel($reuniao)}: {$reuniao->inicio_em->format('d/m/Y \à\s H:i')}{$onde}.";
    }

    /**
     * @param  array<int, int|string|null>  $ids
     * @return Collection<int, User>
     */
    private function usuarios(array $ids): Collection
    {
        $ids = array_values(array_unique(array_filter($ids)));

        return $ids === [] ? collect() : User::query()->ativos()->whereIn('id', $ids)->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function coordenacao(): Collection
    {
        if (! Permission::where('name', 'VerTodas:Reuniao')->where('guard_name', 'web')->exists()) {
            return collect();
        }

        return User::query()->ativos()->permission('VerTodas:Reuniao')->get();
    }

    /**
     * @param  iterable<User>  $destinatarios
     */
    private function avisar(iterable $destinatarios, string $titulo, string $corpo, Reuniao $reuniao, string $icone = 'heroicon-o-calendar-days', string $cor = 'info'): void
    {
        $destinatarios = collect($destinatarios)->filter()->unique('id')->values();

        if ($destinatarios->isEmpty()) {
            return;
        }

        Notification::make()
            ->title($titulo)
            ->body($corpo)
            ->icon($icone)
            ->iconColor($cor)
            ->actions([
                Action::make('abrir')
                    ->label('Abrir reunião')
                    ->button()
                    ->url(ReuniaoResource::getUrl('view', ['record' => $reuniao], panel: 'admin')),
            ])
            ->sendToDatabase($destinatarios);
    }
}
