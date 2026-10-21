<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\TiposArquivo;
use Database\Factories\PortfolioItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/**
 * Evidência de aprendizagem de um aluno (produção, foto, vídeo curto, áudio), ligada às habilidades que ela
 * demonstra. Os arquivos ficam no disco privado e só saem pela rota `portfolio.anexo`, que autoriza cada acesso.
 */
class PortfolioItem extends Model
{
    /** @use HasFactory<PortfolioItemFactory> */
    use HasFactory;

    protected $table = 'portfolio_itens';

    protected $fillable = [
        'matricula_id',
        'titulo',
        'descricao',
        'data_registro',
        'anexos',
        'mostra_outros_alunos',
        'visivel_familia',
        'registrado_por_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_registro' => 'date',
            'anexos' => 'array',
            'mostra_outros_alunos' => 'boolean',
            'visivel_familia' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Rede de segurança além da validação do formulário: material com outros alunos nunca vai para uma família só.
        static::saving(function (PortfolioItem $item): void {
            if ($item->mostra_outros_alunos) {
                $item->visivel_familia = false;
            }
        });

        // Dado pessoal de criança não fica órfão no disco: apagar o item apaga os arquivos dele.
        static::deleting(function (PortfolioItem $item): void {
            foreach ($item->caminhosDosAnexos() as $caminho) {
                Storage::disk('local')->delete($caminho);
            }
        });
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function habilidades(): BelongsToMany
    {
        return $this->belongsToMany(Habilidade::class, 'portfolio_item_habilidade', 'portfolio_item_id', 'habilidade_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    /**
     * Itens que a família pode ver.
     */
    public function scopeVisiveisParaFamilia(Builder $query): Builder
    {
        return $query->where('visivel_familia', true)->where('mostra_outros_alunos', false);
    }

    /**
     * Caminhos dos anexos, sem entradas vazias.
     *
     * @return list<string>
     */
    public function caminhosDosAnexos(): array
    {
        return array_values(array_filter((array) $this->anexos, fn ($c): bool => is_string($c) && $c !== ''));
    }

    public function caminhoDoAnexo(int $indice): ?string
    {
        return $this->caminhosDosAnexos()[$indice] ?? null;
    }

    public function urlDoAnexo(int $indice): string
    {
        return route('portfolio.anexo', ['item' => $this->id, 'indice' => $indice]);
    }

    public function nomeDoAnexo(int $indice): string
    {
        return basename((string) $this->caminhoDoAnexo($indice));
    }

    /**
     * Tipo MIME detectado no próprio arquivo (nunca pela extensão enviada).
     */
    public function mimeDoAnexo(int $indice): ?string
    {
        $caminho = $this->caminhoDoAnexo($indice);

        if ($caminho === null || ! Storage::disk('local')->exists($caminho)) {
            return null;
        }

        return Storage::disk('local')->mimeType($caminho) ?: null;
    }

    public function anexoEhImagem(int $indice): bool
    {
        return in_array($this->mimeDoAnexo($indice), array_intersect(TiposArquivo::exibiveisNoNavegador(), TiposArquivo::imagens()), true);
    }
}
