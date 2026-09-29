<?php

namespace App\Contracts;

use App\Models\Pessoa;

/**
 * Um canal de envio de mensagens a uma Pessoa (e-mail, push, e futuramente
 * WhatsApp/SMS). Usado pela comunicação em massa do CRM e, nas ondas
 * seguintes, pela régua de cobrança e pelo convite de matrícula online.
 */
interface CanalMensagem
{
    /**
     * Identificador estável do canal (ex.: "email", "fcm"), usado para
     * persistir a escolha do canal em configurações e registros.
     */
    public function chave(): string;

    /**
     * Nome amigável exibido nas telas de configuração.
     */
    public function rotulo(): string;

    /**
     * Indica se a pessoa tem os dados necessários para receber por este canal
     * (ex.: e-mail preenchido, usuário com token de push registrado).
     */
    public function disponivelPara(Pessoa $pessoa): bool;

    /**
     * Envia a mensagem. Retorna true se o canal aceitou o envio (não garante
     * entrega — apenas que a chamada ao provedor não falhou).
     */
    public function enviar(Pessoa $pessoa, string $assunto, string $corpo): bool;
}
