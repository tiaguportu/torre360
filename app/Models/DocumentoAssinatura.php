<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NivelAssinatura;
use App\Enums\StatusAssinante;
use App\Enums\StatusDocumentoAssinatura;
use App\Services\Documentos\AssinaturaDocumentoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Documento mandado para assinatura eletrônica no Assinafy (Onda 69), fora do contrato de matrícula. As regras vivem em
 * {@see AssinaturaDocumentoService}.
 */
class DocumentoAssinatura extends Model
{
    protected $table = 'documentos_assinatura';

    protected $fillable = [
        'template_documento_id',
        'documentavel_type',
        'documentavel_id',
        'titulo',
        'conteudo',
        'status',
        'arquivo_original_path',
        'arquivo_original_hash',
        'arquivo_assinado_path',
        'assinafy_id',
        'assinafy_status',
        'assinafy_log',
        'expira_em',
        'enviado_em',
        'concluido_em',
        'criado_por_user_id',
        'nivel_assinatura',
        'assinatura_detalhes',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusDocumentoAssinatura::class,
            'nivel_assinatura' => NivelAssinatura::class,
            'assinatura_detalhes' => 'array',
            'assinafy_log' => 'array',
            'expira_em' => 'date',
            'enviado_em' => 'datetime',
            'concluido_em' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TemplateDocumento::class, 'template_documento_id');
    }

    public function documentavel(): MorphTo
    {
        return $this->morphTo();
    }

    public function assinantes(): HasMany
    {
        return $this->hasMany(AssinanteDocumento::class, 'documento_assinatura_id')->orderBy('ordem')->orderBy('id');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }

    /**
     * Documentos ainda sem desfecho (rascunho ou aguardando assinaturas).
     *
     * @param  Builder<DocumentoAssinatura>  $query
     * @return Builder<DocumentoAssinatura>
     */
    public function scopeAbertos(Builder $query): Builder
    {
        return $query->whereIn('status', [StatusDocumentoAssinatura::Rascunho->value, StatusDocumentoAssinatura::Enviado->value]);
    }

    /**
     * Documentos de RH (contrato de trabalho, termos do funcionário), que só quem vê Admissão e Desligamento enxerga.
     *
     * @param  Builder<DocumentoAssinatura>  $query
     * @return Builder<DocumentoAssinatura>
     */
    public function scopeDeRh(Builder $query): Builder
    {
        return $query->whereIn('documentavel_type', self::tiposDeRh());
    }

    /**
     * @param  Builder<DocumentoAssinatura>  $query
     * @return Builder<DocumentoAssinatura>
     */
    public function scopeSemRh(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('documentavel_type')->orWhereNotIn('documentavel_type', self::tiposDeRh()));
    }

    /**
     * @return list<class-string<Model>>
     */
    public static function tiposDeRh(): array
    {
        return [Funcionario::class, ItemProcessoFuncionario::class, ProcessoFuncionario::class];
    }

    public function ehDeRh(): bool
    {
        return in_array($this->documentavel_type, self::tiposDeRh(), true);
    }

    /**
     * A que o documento se refere, em palavras (o nome da pessoa já está no título).
     */
    public function tipoDoRegistro(): string
    {
        return match ($this->documentavel_type) {
            Matricula::class => 'Matrícula do aluno',
            CancelamentoMatricula::class => 'Cancelamento de matrícula',
            Funcionario::class => 'Funcionário',
            ItemProcessoFuncionario::class, ProcessoFuncionario::class => 'Admissão ou desligamento',
            SolicitacaoDocumento::class => 'Declaração emitida',
            HistoricoEscolar::class => 'Histórico escolar',
            default => 'Outro registro',
        };
    }

    public function estaAssinado(): bool
    {
        return $this->status === StatusDocumentoAssinatura::Assinado;
    }

    public function emAndamento(): bool
    {
        return $this->status->emAndamento();
    }

    /**
     * Ainda não está no Assinafy, ou a tentativa de envio falhou: dá para enviar (e continuar de onde parou).
     */
    public function podeEnviar(): bool
    {
        return in_array($this->status, [StatusDocumentoAssinatura::Rascunho, StatusDocumentoAssinatura::Erro], true);
    }

    public function aguardaAssinaturas(): bool
    {
        return $this->status === StatusDocumentoAssinatura::Enviado;
    }

    /**
     * Só há o que consultar no Assinafy depois do envio, e um documento cancelado aqui não volta atrás.
     */
    public function podeSerConsultado(): bool
    {
        return filled($this->assinafy_id) && $this->status !== StatusDocumentoAssinatura::Cancelado;
    }

    /**
     * @return array{total: int, assinaram: int}
     */
    public function andamentoDasAssinaturas(): array
    {
        $assinantes = $this->assinantes()->get();

        return [
            'total' => $assinantes->count(),
            'assinaram' => $assinantes->where('status', StatusAssinante::Assinou)->count(),
        ];
    }

    /**
     * Quem vê o documento (e o PDF original e o assinado): a equipe com `View:DocumentoAssinatura` (e, no documento de RH,
     * também `View:ProcessoFuncionario`), quem foi chamado para assinar (pelo e-mail) e a família do aluno nos documentos
     * de matrícula e de cancelamento. O professor não entra só por ser da equipe.
     */
    public function isAccessibleBy(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->can('View:DocumentoAssinatura') && (! $this->ehDeRh() || $user->can('View:ProcessoFuncionario'))) {
            return true;
        }

        $email = mb_strtolower(trim((string) $user->email));

        if ($email !== '' && $this->assinantes()->whereRaw('lower(email) = ?', [$email])->exists()) {
            return true;
        }

        // Histórico escolar é do aluno, não de uma matrícula: a família é a das pessoas ligadas ao usuário.
        if ($this->documentavel_type === HistoricoEscolar::class) {
            $pessoaId = HistoricoEscolar::query()->whereKey($this->documentavel_id)->value('pessoa_id');

            return $pessoaId !== null && $user->pessoasAcessiveis()->pluck('id')->contains($pessoaId);
        }

        $matricula = $this->matriculaDoDocumento();

        return $matricula !== null && $matricula->isAccessibleByFamilia($user);
    }

    /**
     * A matrícula a que o documento se refere, quando ele é de matrícula, de cancelamento ou de uma declaração emitida.
     */
    public function matriculaDoDocumento(): ?Matricula
    {
        return match ($this->documentavel_type) {
            Matricula::class => Matricula::query()->with('contrato.responsaveisFinanceiros')->find($this->documentavel_id),
            CancelamentoMatricula::class => ($matriculaId = CancelamentoMatricula::query()->whereKey($this->documentavel_id)->value('matricula_id'))
                ? Matricula::query()->with('contrato.responsaveisFinanceiros')->find($matriculaId)
                : null,
            SolicitacaoDocumento::class => ($matriculaId = SolicitacaoDocumento::query()->whereKey($this->documentavel_id)->value('matricula_id'))
                ? Matricula::query()->with('contrato.responsaveisFinanceiros')->find($matriculaId)
                : null,
            default => null,
        };
    }

    /**
     * Veio de um PDF que a escola já tinha (declaração ou histórico) e não de um modelo de texto.
     */
    public function ehDocumentoOficial(): bool
    {
        return in_array($this->documentavel_type, [SolicitacaoDocumento::class, HistoricoEscolar::class], true) && blank($this->template_documento_id);
    }

    /**
     * Foi assinado fora do sistema (certificado do próprio signatário, gov.br) e anexado: não passou pelo Assinafy.
     */
    public function foiAssinadoFora(): bool
    {
        return $this->estaAssinado() && blank($this->assinafy_id);
    }
}
