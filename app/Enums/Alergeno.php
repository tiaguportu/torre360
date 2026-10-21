<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Alérgenos que o cardápio pode declarar. Só lactose, glúten e amendoim têm campo estruturado na `FichaMedica`;
 * para os demais (e como segunda checagem desses) o cruzamento procura as palavras-chave no texto livre das
 * restrições alimentares, e o resultado é sempre apresentado como "possível" para conferência humana.
 */
enum Alergeno: string implements HasLabel
{
    case Lactose = 'lactose';
    case Gluten = 'gluten';
    case Amendoim = 'amendoim';
    case Ovo = 'ovo';
    case Soja = 'soja';
    case Castanhas = 'castanhas';
    case Peixe = 'peixe';
    case FrutosDoMar = 'frutos_do_mar';

    public function getLabel(): string
    {
        return match ($this) {
            self::Lactose => 'Lactose / leite',
            self::Gluten => 'Glúten / trigo',
            self::Amendoim => 'Amendoim',
            self::Ovo => 'Ovo',
            self::Soja => 'Soja',
            self::Castanhas => 'Castanhas e nozes',
            self::Peixe => 'Peixe',
            self::FrutosDoMar => 'Frutos do mar',
        };
    }

    /**
     * Coluna booleana equivalente na `FichaMedica`, quando existe.
     */
    public function campoFichaMedica(): ?string
    {
        return match ($this) {
            self::Lactose => 'has_alergia_lactose',
            self::Gluten => 'has_alergia_gluten',
            self::Amendoim => 'has_alergia_amendoim',
            default => null,
        };
    }

    /**
     * Termos (sem acento, minúsculos) que, num texto livre de restrição alimentar, indicam este alérgeno.
     *
     * @return list<string>
     */
    public function palavrasChave(): array
    {
        return match ($this) {
            self::Lactose => ['lactose', 'leite', 'laticinio', 'laticinios', 'queijo', 'iogurte', 'manteiga', 'caseina'],
            self::Gluten => ['gluten', 'trigo', 'celiaco', 'celiaca', 'doenca celiaca', 'cevada', 'centeio'],
            self::Amendoim => ['amendoim', 'amendoins'],
            self::Ovo => ['ovo', 'ovos', 'clara de ovo'],
            self::Soja => ['soja'],
            self::Castanhas => ['castanha', 'castanhas', 'noz', 'nozes', 'amendoa', 'amendoas', 'avela', 'pistache', 'macadamia'],
            self::Peixe => ['peixe', 'peixes', 'atum', 'sardinha', 'salmao', 'bacalhau'],
            self::FrutosDoMar => ['frutos do mar', 'marisco', 'mariscos', 'camarao', 'camaroes', 'lagosta', 'caranguejo', 'crustaceo', 'crustaceos'],
        };
    }
}
