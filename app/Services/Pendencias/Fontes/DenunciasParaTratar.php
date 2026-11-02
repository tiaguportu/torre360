<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Enums\StatusDenuncia;
use App\Filament\Resources\Denuncias\DenunciaResource;
use App\Models\Denuncia;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Illuminate\Database\Eloquent\Builder;

/**
 * Manifestações da ouvidoria (Ondas 35 e 63) ainda não encerradas, só as que o usuário pode ler (comitê da categoria). Atrasada é
 * a que passou do prazo da primeira providência. O item mostra o protocolo e a categoria, nunca o relato.
 */
final class DenunciasParaTratar implements FontePendencia
{
    public function chave(): string
    {
        return 'denuncias_para_tratar';
    }

    public function titulo(): string
    {
        return 'Ouvidoria: manifestações para tratar';
    }

    public function area(): string
    {
        return 'Secretaria';
    }

    public function icone(): string
    {
        return 'heroicon-o-shield-exclamation';
    }

    public function orientacao(): string
    {
        return 'Abra a manifestação, assuma a apuração e registre o andamento. Suspeita de violência contra criança ou adolescente pede a comunicação ao Conselho Tutelar.';
    }

    public function permissao(): string
    {
        return 'ViewAny:Denuncia';
    }

    public function url(): string
    {
        return DenunciaResource::getUrl('index');
    }

    public function contar(User $usuario): int
    {
        return $this->consulta($usuario)->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->consulta($usuario)->where('prazo_em', '<', now())->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        return $this->consulta($usuario)
            ->orderByDesc('suspeita_violencia')
            ->orderBy('prazo_em')
            ->limit($limite)
            ->get()
            ->map(fn (Denuncia $denuncia): ItemPendencia => new ItemPendencia(
                titulo: $denuncia->protocolo.' · '.$denuncia->categoria->getLabel(),
                detalhe: ($denuncia->prazo_em ? 'Prazo até '.$denuncia->prazo_em->format('d/m/Y') : 'Sem prazo').($denuncia->responsavel_user_id ? '' : ' · ninguém assumiu'),
                desde: $denuncia->created_at,
                url: DenunciaResource::getUrl('view', ['record' => $denuncia]),
                atrasado: $denuncia->atrasada(),
            ))
            ->all();
    }

    /**
     * @return Builder<Denuncia>
     */
    private function consulta(User $usuario): Builder
    {
        return Denuncia::query()
            ->whereIn('status', [StatusDenuncia::Recebida->value, StatusDenuncia::EmApuracao->value])
            ->visiveisPara($usuario);
    }
}
