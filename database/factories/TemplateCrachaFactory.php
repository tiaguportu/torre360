<?php

namespace Database\Factories;

use App\Models\TemplateCracha;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateCracha>
 */
class TemplateCrachaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => 'Crachá '.$this->faker->word(),
            'largura' => 300,
            'altura' => 480,
            'imagem_fundo' => null,
            'dados_layout' => [
                'version' => '5.3.0',
                'objects' => [
                    [
                        'type' => 'text',
                        'left' => 20,
                        'top' => 50,
                        'width' => 260,
                        'height' => 30,
                        'fill' => '#000000',
                        'text' => '{nome}',
                        'fontSize' => 18,
                        'fontWeight' => 'bold',
                        'fontStyle' => 'normal',
                        'textAlign' => 'center',
                    ],
                    [
                        'type' => 'text',
                        'left' => 20,
                        'top' => 100,
                        'width' => 260,
                        'height' => 25,
                        'fill' => '#555555',
                        'text' => '{profissao}',
                        'fontSize' => 14,
                        'fontWeight' => 'normal',
                        'fontStyle' => 'normal',
                        'textAlign' => 'center',
                    ],
                ],
            ],
        ];
    }
}
