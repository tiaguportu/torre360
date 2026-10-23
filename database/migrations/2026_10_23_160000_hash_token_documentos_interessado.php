<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resolução da pendência M8 de segurança (LGPD):
     * Os tokens de acesso público ao portal de pré-admissão/documentos do candidato não devem
     * ficar expostos em texto claro no banco de dados nem em dumps de backup.
     *
     * A coluna `token_documentos` passa a armazenar o hash SHA-256 (64 caracteres) do token para
     * busca indexada ultra rápida e comparação segura, enquanto `token_documentos_cifrado` guarda
     * o token criptografado (AES-256) apenas para recuperação na tela administrativa pelo atendente.
     */
    public function up(): void
    {
        Schema::table('interessado', function (Blueprint $table) {
            if (! Schema::hasColumn('interessado', 'token_documentos_cifrado')) {
                $table->text('token_documentos_cifrado')->nullable()->after('token_documentos');
            }
        });

        // Converte tokens em texto claro existentes para hash SHA-256 e armazena o valor cifrado
        DB::table('interessado')
            ->whereNotNull('token_documentos')
            ->orderBy('id')
            ->each(function (object $lead): void {
                $tokenAtual = (string) $lead->token_documentos;
                if ($tokenAtual === '') {
                    return;
                }

                // Se já tem tamanho 64 e parece um hash SHA-256 hexadecimal e já tem o cifrado, ignora
                if (strlen($tokenAtual) === 64 && ctype_xdigit($tokenAtual) && filled($lead->token_documentos_cifrado ?? null)) {
                    return;
                }

                DB::table('interessado')->where('id', $lead->id)->update([
                    'token_documentos' => hash('sha256', $tokenAtual),
                    'token_documentos_cifrado' => Crypt::encryptString($tokenAtual),
                ]);
            });
    }

    public function down(): void
    {
        // Na reversão, se houver token cifrado, restaura o texto claro
        DB::table('interessado')
            ->whereNotNull('token_documentos_cifrado')
            ->orderBy('id')
            ->each(function (object $lead): void {
                try {
                    $tokenClaro = Crypt::decryptString((string) $lead->token_documentos_cifrado);
                    DB::table('interessado')->where('id', $lead->id)->update([
                        'token_documentos' => $tokenClaro,
                    ]);
                } catch (Throwable) {
                    // Mantém como está se não conseguir descriptografar
                }
            });

        Schema::table('interessado', function (Blueprint $table) {
            if (Schema::hasColumn('interessado', 'token_documentos_cifrado')) {
                $table->dropColumn('token_documentos_cifrado');
            }
        });
    }
};
