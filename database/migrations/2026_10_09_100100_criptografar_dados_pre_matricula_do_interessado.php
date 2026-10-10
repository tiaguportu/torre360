<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O rascunho de pré-matrícula (CPF, endereço, responsáveis e alunos preenchidos pela família) ficava em JSON
     * puro. Passa a ser guardado criptografado (cast `ArrayCriptografado`), o que exige uma coluna de texto: o
     * tipo `json` recusa o texto cifrado.
     *
     * Também ganha `dados_pre_matricula_em`, a data da última atualização do rascunho, que a rotina de retenção
     * (`crm:expurgar-rascunhos-pre-matricula`) usa; nos rascunhos existentes vale a data de atualização do lead.
     *
     * Os rascunhos existentes são cifrados aqui. O cast aceita JSON puro na leitura, então um deploy em que o
     * código chega antes da migration não quebra a ficha dos leads.
     */
    public function up(): void
    {
        Schema::table('interessado', function (Blueprint $table) {
            if (! Schema::hasColumn('interessado', 'dados_pre_matricula_em')) {
                $table->timestamp('dados_pre_matricula_em')->nullable()->after('dados_pre_matricula');
            }

            $table->longText('dados_pre_matricula')->nullable()->change();
        });

        DB::table('interessado')
            ->whereNotNull('dados_pre_matricula')
            ->orderBy('id')
            ->each(function (object $lead): void {
                $conteudo = (string) $lead->dados_pre_matricula;

                // Já cifrado (migration repetida) ou vazio: nada a fazer.
                if ($conteudo === '' || json_decode($conteudo) === null) {
                    return;
                }

                DB::table('interessado')->where('id', $lead->id)->update([
                    'dados_pre_matricula' => Crypt::encryptString($conteudo),
                    'dados_pre_matricula_em' => $lead->dados_pre_matricula_em ?? $lead->updated_at ?? now(),
                ]);
            });
    }

    public function down(): void
    {
        DB::table('interessado')
            ->whereNotNull('dados_pre_matricula')
            ->orderBy('id')
            ->each(function (object $lead): void {
                try {
                    $claro = Crypt::decryptString((string) $lead->dados_pre_matricula);
                } catch (Throwable) {
                    return; // já em JSON puro
                }

                DB::table('interessado')->where('id', $lead->id)->update(['dados_pre_matricula' => $claro]);
            });

        Schema::table('interessado', function (Blueprint $table) {
            $table->json('dados_pre_matricula')->nullable()->change();

            if (Schema::hasColumn('interessado', 'dados_pre_matricula_em')) {
                $table->dropColumn('dados_pre_matricula_em');
            }
        });
    }
};
