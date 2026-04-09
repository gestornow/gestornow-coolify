<?php

namespace App\Policies;

use App\Domain\Auth\Models\Usuario;
use App\Models\ContasAReceber;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContasAReceberPolicy
{
    use HandlesAuthorization;

    /**
     * Determinar se o usuário pode visualizar a listagem
     */
    public function viewAny(Usuario $user): bool
    {
        // Verificar se o usuário tem permissão de visualizar contas
        // Ajustar conforme sistema de permissões
        return $user->id_empresa !== null;
    }

    /**
     * Determinar se o usuário pode visualizar uma conta específica
     */
    public function view(Usuario $user, ContasAReceber $conta): bool
    {
        // Usuário só pode ver contas da sua empresa
        return $user->id_empresa === $conta->id_empresa;
    }

    /**
     * Determinar se o usuário pode criar contas
     */
    public function create(Usuario $user): bool
    {
        // Verificar se o usuário tem permissão de criar contas
        return $user->id_empresa !== null;
    }

    /**
     * Determinar se o usuário pode atualizar uma conta
     */
    public function update(Usuario $user, ContasAReceber $conta): bool
    {
        // Usuário só pode editar contas da sua empresa
        // e contas que não estejam recebidas (opcional)
        return $user->id_empresa === $conta->id_empresa;
    }

    /**
     * Determinar se o usuário pode deletar uma conta
     */
    public function delete(Usuario $user, ContasAReceber $conta): bool
    {
        // Usuário só pode deletar contas da sua empresa
        return $user->id_empresa === $conta->id_empresa;
    }

    /**
     * Determinar se o usuário pode deletar múltiplas contas
     */
    public function deleteMultiple(Usuario $user): bool
    {
        return $user->id_empresa !== null;
    }

    /**
     * Determinar se o usuário pode visualizar parcelas de uma conta
     */
    public function viewParcelas(Usuario $user, ContasAReceber $conta): bool
    {
        return $user->id_empresa === $conta->id_empresa;
    }

    /**
     * Determinar se o usuário pode visualizar recorrências de uma conta
     */
    public function viewRecorrencias(Usuario $user, ContasAReceber $conta): bool
    {
        return $user->id_empresa === $conta->id_empresa;
    }

    /**
     * Determinar se o usuário pode marcar conta como recebida
     */
    public function markAsPaid(Usuario $user, ContasAReceber $conta): bool
    {
        return $user->id_empresa === $conta->id_empresa 
            && $conta->status !== 'pago';
    }

    /**
     * Determinar se o usuário pode dar baixa em uma conta
     */
    public function darBaixa(Usuario $user, ContasAReceber $conta): bool
    {
        return $user->id_empresa === $conta->id_empresa 
            && $conta->status !== 'pago';
    }

    /**
     * Determinar se o usuário pode excluir um recebimento
     */
    public function excluirRecebimento(Usuario $user, ContasAReceber $conta): bool
    {
        // Permite excluir recebimentos se for da mesma empresa
        // Pode adicionar verificação de permissões adicionais se necessário
        return $user->id_empresa === $conta->id_empresa;
    }
}
