<?php

namespace App\Models;

use App\Enums\ContextoDocumento;
use App\Enums\TipoTemplateDocumento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

class TemplateDocumento extends Model
{
    use HasFactory;

    protected $table = 'template_documentos';

    protected $fillable = ['nome', 'tipo', 'descricao', 'cabecalho', 'conteudo', 'rodape', 'validade_dias', 'is_ativo'];

    protected static function booted(): void
    {
        // Quem mexeu no texto de um modelo que vai para assinatura fica registrado. O texto em si não vai para o log: cada
        // documento enviado guarda a cópia do que foi mandado, e é ela que prova o que foi assinado.
        static::updated(function (TemplateDocumento $template): void {
            $campos = array_keys(Arr::except($template->getChanges(), ['updated_at']));

            if ($campos === []) {
                return;
            }

            $registro = activity('modelos')->performedOn($template)->withProperties(['campos' => $campos]);

            if ($usuario = auth()->user()) {
                $registro->causedBy($usuario);
            }

            $registro->log('Modelo de documento alterado.');
        });
    }

    public function solicitacoes(): HasMany
    {
        return $this->hasMany(SolicitacaoDocumento::class);
    }

    /**
     * Modelos que a secretaria emite e a família pede como declaração (tipos de matrícula).
     *
     * @param  Builder<TemplateDocumento>  $query
     * @return Builder<TemplateDocumento>
     */
    public function scopeParaEmissao(Builder $query): Builder
    {
        return $query->whereIn('tipo', TipoTemplateDocumento::valoresParaEmissao());
    }

    /**
     * Modelos de um contexto (matrícula, cancelamento ou funcionário).
     *
     * @param  Builder<TemplateDocumento>  $query
     * @return Builder<TemplateDocumento>
     */
    public function scopeDoContexto(Builder $query, ContextoDocumento $contexto): Builder
    {
        $tipos = array_values(array_map(
            fn (TipoTemplateDocumento $tipo): string => $tipo->value,
            array_filter(TipoTemplateDocumento::cases(), fn (TipoTemplateDocumento $tipo): bool => $tipo->contexto() === $contexto),
        ));

        return $query->whereIn('tipo', $tipos);
    }

    public function contexto(): ContextoDocumento
    {
        return $this->tipo->contexto();
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoTemplateDocumento::class,
            'is_ativo' => 'boolean',
            'validade_dias' => 'integer',
        ];
    }
}
