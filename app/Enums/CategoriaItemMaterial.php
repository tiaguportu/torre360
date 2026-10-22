<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Agrupa os itens da lista de material para a família achar o que procura. Não define o que é de uso individual: isso é
 * a descrição do item e a confirmação de quem monta a lista.
 */
enum CategoriaItemMaterial: string implements HasLabel
{
    case Papelaria = 'papelaria';
    case LivrosEApostilas = 'livros';
    case ArteEArtesanato = 'arte';
    case HigienePessoal = 'higiene_pessoal';
    case EducacaoFisica = 'educacao_fisica';
    case Outros = 'outros';

    public function getLabel(): string
    {
        return match ($this) {
            self::Papelaria => 'Papelaria',
            self::LivrosEApostilas => 'Livros e apostilas',
            self::ArteEArtesanato => 'Arte e artesanato',
            self::HigienePessoal => 'Higiene pessoal do aluno',
            self::EducacaoFisica => 'Educação física',
            self::Outros => 'Outros',
        };
    }
}
