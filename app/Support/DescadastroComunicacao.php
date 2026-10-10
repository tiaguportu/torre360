<?php

namespace App\Support;

use App\Models\Pessoa;
use Illuminate\Support\Facades\URL;

/**
 * Link de descadastro dos e-mails comerciais (régua de follow-up). É uma URL assinada sem validade: o e-mail
 * pode ser aberto meses depois e o pedido de parar de receber não pode expirar. A assinatura (APP_KEY) impede
 * descadastrar terceiros trocando o número do link.
 */
class DescadastroComunicacao
{
    public static function url(Pessoa $pessoa): string
    {
        return URL::signedRoute('comunicacao.descadastrar', ['pessoa' => $pessoa->getKey()]);
    }
}
