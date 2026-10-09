<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices para as consultas mais repetidas do CRM (listagem, abas, Kanban, alertas e régua).
     * Antes só existiam os índices das chaves estrangeiras: ordenar a listagem por "próximo contato",
     * filtrar por score/conversão ou achar "sem consultor" varria a tabela inteira.
     *
     * Idempotente: cada índice só é criado se ainda não existir (nome fixo).
     *
     * @var array<string, array<string, list<string>>>
     */
    private const INDICES = [
        'interessado' => [
            // Ordenação padrão da listagem e aba "Precisa de contato".
            'interessado_proximo_contato_idx' => ['data_proximo_contato'],
            // Kanban (cards de uma etapa por urgência) e alertas (ativos atrasados).
            'interessado_status_proximo_idx' => ['status_interessado_id', 'data_proximo_contato'],
            // Kanban das colunas finais (movidos recentemente) e consultor + etapa.
            'interessado_status_atualizado_idx' => ['status_interessado_id', 'updated_at'],
            'interessado_usuario_status_idx' => ['usuario_id', 'status_interessado_id'],
            // Aba "Quentes", ordenação por score e relatórios de conversão.
            'interessado_lead_score_idx' => ['lead_score'],
            'interessado_data_conversao_idx' => ['data_conversao'],
            // "Sem consultor" há X horas e estagnação de quem nunca teve contato.
            'interessado_created_at_idx' => ['created_at'],
        ],
        'pessoa' => [
            // `where email = ?` (matrícula online, importação, convites).
            'pessoa_email_idx' => ['email'],
        ],
        'visita_interessado' => [
            // Visitas de um lead por status (card do Kanban, régua, calendário).
            'visita_interessado_lead_status_idx' => ['interessado_id', 'status'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDICES as $tabela => $indices) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }

            foreach ($indices as $nome => $colunas) {
                if (! Schema::hasColumns($tabela, $colunas) || Schema::hasIndex($tabela, $nome)) {
                    continue;
                }

                Schema::table($tabela, fn (Blueprint $table) => $table->index($colunas, $nome));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDICES as $tabela => $indices) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }

            foreach (array_keys($indices) as $nome) {
                if (Schema::hasIndex($tabela, $nome)) {
                    Schema::table($tabela, fn (Blueprint $table) => $table->dropIndex($nome));
                }
            }
        }
    }
};
