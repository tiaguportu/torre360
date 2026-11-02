<?php

namespace App\Models;

use App\Enums\CategoriaDenuncia;
use App\Enums\StatusDenuncia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Denúncia do canal de ouvidoria e convivência escolar (Ondas 35 e 63).
 *
 * Descrição, local, envolvidos e identificação ficam criptografados. O código de acompanhamento nunca é guardado: só o hash
 * (HMAC com a chave da aplicação). Denúncia anônima tem `user_id` nulo e nenhum IP ou agente de navegador.
 */
class Denuncia extends Model
{
    protected $table = 'denuncias';

    protected $fillable = [
        'protocolo',
        'codigo_hash',
        'categoria',
        'descricao',
        'local_data',
        'envolvidos',
        'anonima',
        'identificacao',
        'user_id',
        'status',
        'suspeita_violencia',
        'prazo_em',
        'responsavel_user_id',
        'conclusao',
        'encerrada_em',
    ];

    protected $hidden = ['codigo_hash'];

    /** Sempre com o responsável: o app proíbe carga preguiçosa e a tela de leitura mostra o nome dele. */
    protected $with = ['responsavel'];

    protected function casts(): array
    {
        return [
            'categoria' => CategoriaDenuncia::class,
            'status' => StatusDenuncia::class,
            'descricao' => 'encrypted',
            'local_data' => 'encrypted',
            'envolvidos' => 'encrypted:array',
            'identificacao' => 'encrypted:array',
            'anonima' => 'boolean',
            'suspeita_violencia' => 'boolean',
            'prazo_em' => 'datetime',
            'encerrada_em' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $denuncia): void {
            if (empty($denuncia->protocolo)) {
                $denuncia->protocolo = 'OUV-'.date('Y').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
            }
        });
    }

    public static function hashDoCodigo(string $codigo): string
    {
        return hash_hmac('sha256', strtoupper(trim($codigo)), (string) config('app.key'));
    }

    public function codigoConfere(string $codigo): bool
    {
        return hash_equals($this->codigo_hash, self::hashDoCodigo($codigo));
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(DenunciaMovimentacao::class, 'denuncia_id')->oldest();
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }

    public function atrasada(): bool
    {
        return ! $this->status->estaEncerrada() && (bool) $this->prazo_em?->isPast();
    }

    /**
     * Quem lê a denúncia: quem tem `VerTodas:Denuncia` (direção) ou é do comitê da categoria (ou de todas as categorias).
     *
     * @param  Builder<self>  $consulta
     * @return Builder<self>
     */
    public function scopeVisiveisPara(Builder $consulta, ?User $usuario): Builder
    {
        if (! $usuario) {
            return $consulta->whereRaw('1 = 0');
        }

        if ($usuario->can('VerTodas:Denuncia')) {
            return $consulta;
        }

        $categorias = DenunciaComite::query()->where('user_id', $usuario->id)->get()->map(fn (DenunciaComite $membro): ?string => $membro->categoria?->value);

        if ($categorias->isEmpty()) {
            return $consulta->whereRaw('1 = 0');
        }

        // Categoria em branco no comitê: a pessoa acompanha todas.
        if ($categorias->contains(fn (?string $categoria): bool => $categoria === null)) {
            return $consulta;
        }

        return $consulta->whereIn('categoria', $categorias->all());
    }

    public function podeSerVistaPor(?User $usuario): bool
    {
        return self::query()->visiveisPara($usuario)->whereKey($this->getKey())->exists();
    }
}
