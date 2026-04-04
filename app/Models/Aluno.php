<?php

namespace App\Models;

/**
 * Proxy model para separar as permissões de Alunos das de Pessoas no Shield.
 */
class Aluno extends Pessoa
{
    protected $table = 'pessoa';
}
