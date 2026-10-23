<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FichaMedica extends Model
{
    use HasFactory;

    protected $table = 'ficha_medicas';

    protected $fillable = ['pessoa_id', 'tipo_sanguineo', 'has_alergia_lactose', 'has_alergia_gluten', 'has_alergia_amendoim', 'outras_alergias_alimentares', 'observacoes_alimentares', 'plano_saude', 'numero_carteira_sus', 'hospital_preferencia', 'observacoes_gerais'];

    protected function casts(): array
    {
        return [
            'tipo_sanguineo' => 'encrypted',
            'outras_alergias_alimentares' => 'encrypted',
            'observacoes_alimentares' => 'encrypted',
            'plano_saude' => 'encrypted',
            'numero_carteira_sus' => 'encrypted',
            'hospital_preferencia' => 'encrypted',
            'observacoes_gerais' => 'encrypted',
            'has_alergia_lactose' => 'boolean',
            'has_alergia_gluten' => 'boolean',
            'has_alergia_amendoim' => 'boolean',
        ];
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function medicamentos(): HasMany
    {
        return $this->hasMany(MedicamentoAluno::class, 'ficha_medica_id');
    }

    public function contatosEmergencia(): HasMany
    {
        return $this->hasMany(ContatoEmergencia::class, 'ficha_medica_id');
    }
}
