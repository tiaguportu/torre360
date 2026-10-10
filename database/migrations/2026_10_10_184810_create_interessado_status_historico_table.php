<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('interessado_status_historico')) {
            Schema::create('interessado_status_historico', function (Blueprint $table) {
                $table->id();
                $table->foreignId('interessado_id')->constrained('interessado')->cascadeOnDelete();
                $table->foreignId('status_anterior_id')->nullable()->constrained('status_interessado')->nullOnDelete();
                $table->foreignId('status_novo_id')->constrained('status_interessado')->cascadeOnDelete();
                $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('motivo_perda')->nullable();
                $table->dateTime('data_transicao');
                $table->boolean('estimada')->default(false);
                $table->timestamps();

                $table->index(['interessado_id', 'data_transicao'], 'idx_transicao_lead_data');
                $table->index(['status_novo_id', 'data_transicao'], 'idx_transicao_status_novo_data');
                $table->index(['status_anterior_id', 'status_novo_id'], 'idx_transicao_de_para');
                $table->index('data_transicao', 'idx_transicao_data');
            });
        }

        // Backfill idempotente: uma linha inicial por lead existente (etapa atual, created_at), marcada como estimada.
        if (Schema::hasTable('interessado') && Schema::hasTable('interessado_status_historico')) {
            $leads = DB::table('interessado')
                ->whereNotNull('status_interessado_id')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('interessado_status_historico')
                        ->whereColumn('interessado_status_historico.interessado_id', 'interessado.id');
                })
                ->select(['id', 'status_interessado_id', 'usuario_id', 'motivo_perda', 'created_at'])
                ->get();

            $agora = now();
            $registros = [];

            foreach ($leads as $lead) {
                $registros[] = [
                    'interessado_id' => $lead->id,
                    'status_anterior_id' => null,
                    'status_novo_id' => $lead->status_interessado_id,
                    'usuario_id' => $lead->usuario_id,
                    'motivo_perda' => $lead->motivo_perda,
                    'data_transicao' => $lead->created_at ?? $agora,
                    'estimada' => true,
                    'created_at' => $lead->created_at ?? $agora,
                    'updated_at' => $lead->created_at ?? $agora,
                ];

                if (count($registros) >= 200) {
                    DB::table('interessado_status_historico')->insert($registros);
                    $registros = [];
                }
            }

            if (! empty($registros)) {
                DB::table('interessado_status_historico')->insert($registros);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interessado_status_historico');
    }
};
