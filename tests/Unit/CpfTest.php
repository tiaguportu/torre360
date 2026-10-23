<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Rules\Cpf;
use PHPUnit\Framework\TestCase;

class CpfTest extends TestCase
{
    /**
     * Valida CPFs válidos conhecidos com e sem máscara.
     */
    public function test_aceita_cpfs_validos(): void
    {
        // Exemplos conhecidos de CPFs matematicamente válidos
        $this->assertTrue(Cpf::valido('52998224725'));
        $this->assertTrue(Cpf::valido('529.982.247-25'));
        $this->assertTrue(Cpf::valido('12345678909'));
        $this->assertTrue(Cpf::valido('123.456.789-09'));
        $this->assertFalse(Cpf::valido('12345678900')); // Dígito inválido
    }

    /**
     * Rejeita CPFs formados por 11 dígitos repetidos.
     */
    public function test_rejeita_cpfs_com_digitos_iguais(): void
    {
        for ($i = 0; $i <= 9; $i++) {
            $repetido = str_repeat((string) $i, 11);
            $this->assertFalse(Cpf::valido($repetido), "CPF {$repetido} não deve ser válido.");
        }
    }

    /**
     * Rejeita valores com quantidade incorreta de dígitos.
     */
    public function test_rejeita_quantidade_invalida_de_digitos(): void
    {
        $this->assertFalse(Cpf::valido('1234567890')); // 10 dígitos
        $this->assertFalse(Cpf::valido('123456789012')); // 12 dígitos
        $this->assertFalse(Cpf::valido(''));
        $this->assertFalse(Cpf::valido(null));
    }

    /**
     * Rejeita CPFs com primeiro dígito verificador incorreto.
     */
    public function test_rejeita_primeiro_digito_verificador_incorreto(): void
    {
        // 52998224725 é válido. Trocando o primeiro dígito '2' por '3': 52998224735
        $this->assertFalse(Cpf::valido('52998224735'));
    }

    /**
     * Rejeita CPFs com segundo dígito verificador incorreto.
     */
    public function test_rejeita_segundo_digito_verificador_incorreto(): void
    {
        // 52998224725 é válido. Trocando o segundo dígito '5' por '4': 52998224724
        $this->assertFalse(Cpf::valido('52998224724'));
    }

    /**
     * Testa o método validate executando o callback $fail em caso de erro.
     */
    public function test_validate_executa_callback_fail_quando_invalido(): void
    {
        $rule = new Cpf;
        $falhou = false;
        $mensagemFalha = '';

        $rule->validate('cpf', '111.111.111-11', function (string $mensagem) use (&$falhou, &$mensagemFalha) {
            $falhou = true;
            $mensagemFalha = $mensagem;
        });

        $this->assertTrue($falhou);
        $this->assertSame('O :attribute informado não é um CPF válido.', $mensagemFalha);
    }

    /**
     * Testa o método validate não disparando $fail quando o CPF é válido.
     */
    public function test_validate_nao_dispara_fail_quando_valido(): void
    {
        $rule = new Cpf;
        $falhou = false;

        $rule->validate('cpf', '529.982.247-25', function () use (&$falhou) {
            $falhou = true;
        });

        $this->assertFalse($falhou);
    }
}
