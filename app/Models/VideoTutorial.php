<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class VideoTutorial extends Model
{
    protected $table = 'video_tutorial';

    protected $guarded = [];

    /**
     * Chaves de página usadas para linkar um vídeo ao modal de "Ajuda" de uma
     * tela específica. Uma mesma chave pode ser reaproveitada por mais de uma
     * página quando elas fazem parte do mesmo fluxo (ex: criar avaliação e
     * lançar notas são passos do mesmo vídeo).
     */
    public const CHAVES_PAGINA = [
        'interessados-kanban' => 'CRM: Kanban de Interessados',
        'rematriculas-lista' => 'Secretaria: Acompanhamento de Rematrículas',
        'cronograma-aula-lancar-frequencia' => 'Acadêmico: Lançar Frequência (Chamada)',
        'avaliacoes-notas' => 'Avaliações: Criar Avaliação / Lançar Notas em Grade',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'ordem' => 'integer',
            'duracao_segundos' => 'integer',
        ];
    }

    public function scopeAtivo(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /**
     * URL pública do arquivo de vídeo enviado (disco 'public'), se houver.
     */
    public function getArquivoUrlAttribute(): ?string
    {
        return $this->arquivo ? Storage::disk('public')->url($this->arquivo) : null;
    }

    /**
     * URL a usar no botão "Assistir" / "Baixar": arquivo local tem prioridade
     * sobre o link externo.
     */
    public function getUrlAssistirAttribute(): ?string
    {
        return $this->arquivo_url ?? $this->url_externo;
    }

    /**
     * Converte um link do YouTube/Vimeo para a URL de embed em iframe.
     * Retorna null se houver arquivo local (não precisa de embed) ou se o
     * link não for reconhecido.
     */
    public function getUrlEmbedAttribute(): ?string
    {
        if ($this->arquivo || ! $this->url_externo) {
            return null;
        }

        $url = $this->url_externo;

        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/', $url, $m)) {
            return 'https://www.youtube.com/embed/'.$m[1];
        }

        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1];
        }

        return null;
    }

    /**
     * Duração formatada (ex: "1min 27s"), ou null se não informada.
     */
    public function getDuracaoFormatadaAttribute(): ?string
    {
        if (! $this->duracao_segundos) {
            return null;
        }

        $min = intdiv($this->duracao_segundos, 60);
        $seg = $this->duracao_segundos % 60;

        return $min > 0 ? "{$min}min {$seg}s" : "{$seg}s";
    }
}
