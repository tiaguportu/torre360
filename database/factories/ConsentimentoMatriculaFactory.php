<?php

namespace Database\Factories;

use App\Enums\StatusConsentimento;
use App\Models\ConsentimentoMatricula;
use App\Models\Matricula;
use App\Models\TipoConsentimento;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConsentimentoMatriculaFactory extends Factory
{
    protected $model = ConsentimentoMatricula::class;

    public function definition(): array
    {
        return [
            'matricula_id' => Matricula::factory(),
            'tipo_consentimento_id' => TipoConsentimento::factory(),
            'status' => StatusConsentimento::Pendente->value,
        ];
    }
}
