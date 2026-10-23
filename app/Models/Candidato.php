<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusCandidato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Candidato extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'candidatos';

    protected $fillable = [
        'nome_completo',
        'email',
        'telefone',
        'data_nascimento',
        'idade',
        'cargo_pretendido',
        'escola_atual',
        'local_moradia',
        'pretensao_salarial',
        'formacao_academica',
        'experiencias_profissionais',
        'habilidades',
        'resumo_perfil',
        'curriculo_path',
        'curriculo_nome_original',
        'foto_path',
        'status',
        'impressoes_entrevistador',
        'audio_entrevista_path',
        'audio_transcricao_resumo',
        'dados_ia',
        'cadastrado_por_user_id',
        'entrevistador_user_id',
    ];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            'idade' => 'integer',
            'pretensao_salarial' => 'decimal:2',
            'status' => StatusCandidato::class,
            'dados_ia' => 'array',
        ];
    }

    public function cadastradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cadastrado_por_user_id');
    }

    public function entrevistador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entrevistador_user_id');
    }

    public function getIdadeCalculadaAttribute(): ?int
    {
        if ($this->idade !== null && $this->idade > 0) {
            return $this->idade;
        }

        if ($this->data_nascimento) {
            return $this->data_nascimento->age;
        }

        return null;
    }

    public function getFotoUrlAttribute(): ?string
    {
        if (empty($this->foto_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->foto_path);
    }

    public function getCurriculoUrlAttribute(): ?string
    {
        if (empty($this->curriculo_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->curriculo_path);
    }

    public function getAudioUrlAttribute(): ?string
    {
        if (empty($this->audio_entrevista_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->audio_entrevista_path);
    }

    public function scopePorStatus(Builder $query, StatusCandidato|string $status): Builder
    {
        $valor = $status instanceof StatusCandidato ? $status->value : $status;

        return $query->where('status', $valor);
    }

    public function scopeBancoTalentos(Builder $query): Builder
    {
        return $query->where('status', StatusCandidato::BancoTalentos->value);
    }
}
