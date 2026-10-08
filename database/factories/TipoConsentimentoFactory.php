<?php

namespace Database\Factories;

use App\Models\TipoConsentimento;
use Illuminate\Database\Eloquent\Factories\Factory;

class TipoConsentimentoFactory extends Factory
{
    protected $model = TipoConsentimento::class;

    public function definition(): array
    {
        return [
            'nome' => 'Uso de Imagem — Redes Sociais',
            'texto_padrao' => 'Autorizo o uso da imagem do(a) aluno(a) em publicações nas redes sociais oficiais da escola.',
            'exige_renovacao_periodica' => true,
            'periodicidade_meses' => 12,
            'is_ativo' => true,
        ];
    }
}
