<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assinatura eletrônica de documentos avulsos (Onda 69): distrato, contrato de trabalho, termos. O documento guarda a cópia
 * do texto que foi mandado (o modelo pode mudar depois), o PDF enviado com o seu hash e, ao fim, o PDF certificado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_assinatura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_documento_id')->nullable()->constrained('template_documentos')->nullOnDelete();
            // O registro a que o documento pertence e que reage à assinatura (cancelamento, item de RH, matrícula...).
            $table->nullableMorphs('documentavel');
            $table->string('titulo', 200);
            $table->longText('conteudo');
            $table->string('status', 15)->default('rascunho');
            $table->string('arquivo_original_path')->nullable();
            $table->string('arquivo_original_hash', 64)->nullable();
            $table->string('arquivo_assinado_path')->nullable();
            $table->string('assinafy_id', 100)->nullable();
            $table->string('assinafy_status', 40)->nullable();
            $table->json('assinafy_log')->nullable();
            $table->date('expira_em')->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->timestamp('concluido_em')->nullable();
            $table->foreignId('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('assinafy_id', 'idx_doc_assinatura_assinafy');
            $table->index('status', 'idx_doc_assinatura_status');
        });

        Schema::create('assinantes_documento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_assinatura_id')->constrained('documentos_assinatura')->cascadeOnDelete();
            $table->string('nome', 160);
            $table->string('email', 160);
            $table->string('papel', 40)->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->string('status', 15)->default('pendente');
            $table->timestamp('assinado_em')->nullable();
            $table->string('assinafy_signer_id', 100)->nullable();
            // Criptografado: o endereço carrega o token de quem assina.
            $table->text('link_assinatura')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assinantes_documento');
        Schema::dropIfExists('documentos_assinatura');
    }
};
