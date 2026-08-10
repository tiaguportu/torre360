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

    protected $guarded = [];

    protected function casts(): array
    {
        return [
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
