<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prova do consentimento dado no formulário público de captação (LGPD, art. 8º: o controlador deve poder
     * demonstrar o consentimento) e do descadastro pedido pelo link dos e-mails da régua.
     *
     * - `consentimento_em` / `_versao` / `_origem` / `_ip`: quando, qual texto (`crm.lgpd.versao_consentimento`), por
     *   qual caminho (formulário de captação, convite de matrícula) e de qual IP o aceite veio.
     * - `descadastrado_em`: quando a pessoa pediu para não receber e-mails; `aceita_comunicacao` passa a false.
     *
     * Pessoas já cadastradas ficam sem registro de consentimento: ele não existia e não é inventado aqui.
     */
    public function up(): void
    {
        Schema::table('pessoa', function (Blueprint $table) {
            if (! Schema::hasColumn('pessoa', 'consentimento_em')) {
                $table->timestamp('consentimento_em')->nullable()->after('aceita_comunicacao');
                $table->string('consentimento_versao', 20)->nullable()->after('consentimento_em');
                $table->string('consentimento_origem', 40)->nullable()->after('consentimento_versao');
                $table->string('consentimento_ip', 45)->nullable()->after('consentimento_origem');
            }

            if (! Schema::hasColumn('pessoa', 'descadastrado_em')) {
                $table->timestamp('descadastrado_em')->nullable()->after('consentimento_ip');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pessoa', function (Blueprint $table) {
            $colunas = array_filter(
                ['consentimento_em', 'consentimento_versao', 'consentimento_origem', 'consentimento_ip', 'descadastrado_em'],
                fn (string $coluna): bool => Schema::hasColumn('pessoa', $coluna)
            );

            if ($colunas !== []) {
                $table->dropColumn($colunas);
            }
        });
    }
};
