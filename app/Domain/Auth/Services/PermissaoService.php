<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Models\Usuario;
use App\Domain\Auth\Models\UsuarioPermissao;
use App\Models\Modulo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PermissaoService
{
    /**
     * Obter permissões do usuário para todos os módulos da empresa
     */
    public function obterPermissoesUsuario(int $idUsuario, int $idEmpresa): Collection
    {
        $usuarioExisteNaEmpresa = Usuario::where('id_usuario', $idUsuario)
            ->where('id_empresa', $idEmpresa)
            ->exists();

        if (!$usuarioExisteNaEmpresa) {
            return collect(); // Segurança: evita expor permissões de usuário fora da empresa da sessão.
        }

        return UsuarioPermissao::where('id_usuario', $idUsuario)
            ->get()
            ->keyBy('id_modulo');
    }

    /**
     * Obter módulos do plano da empresa
     */
    public function obterModulosEmpresa(int $idEmpresa): Collection
    {
        return DB::table('planos_contratados_modulos')
            ->join('modulos', 'planos_contratados_modulos.id_modulo', '=', 'modulos.id_modulo')
            ->join('planos_contratados', 'planos_contratados_modulos.id_plano_contratado', '=', 'planos_contratados.id')
            ->where('planos_contratados.id_empresa', $idEmpresa)
            ->select('modulos.id_modulo', 'modulos.nome')
            ->distinct()
            ->get();
    }

    /**
     * Salvar permissões do usuário
     */
    public function salvarPermissoes(int $idUsuario, int $idEmpresa, array $permissoes): bool
    {
        try {
            $usuario = Usuario::where('id_usuario', $idUsuario)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$usuario) {
                \Log::warning('Tentativa de salvar permissões fora do escopo da empresa.', [
                    'id_usuario' => $idUsuario,
                    'id_empresa' => $idEmpresa,
                ]);
                return false;
            }

            // Limpar permissões antigas
            UsuarioPermissao::where('id_usuario', $idUsuario)->delete();

            // Salvar novas permissões
            foreach ($permissoes as $idModulo => $acoes) {
                UsuarioPermissao::create([
                    'id_usuario' => $idUsuario,
                    'id_modulo' => (int)$idModulo,
                    'pode_ler' => (int)($acoes['ler'] ?? false),
                    'pode_criar' => (int)($acoes['criar'] ?? false),
                    'pode_editar' => (int)($acoes['editar'] ?? false),
                    'pode_deletar' => (int)($acoes['deletar'] ?? false),
                ]);
            }

            \Log::info('Permissões salvas com sucesso', [
                'id_usuario' => $idUsuario,
                'id_empresa' => $idEmpresa,
                'total_modulos' => count($permissoes)
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Erro ao salvar permissões', [
                'id_usuario' => $idUsuario,
                'id_empresa' => $idEmpresa,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Definir permissões padrão para novo usuário
     */
    public function criarPermissoesParaUsuario(int $idUsuario, int $idEmpresa): void
    {
        $modulos = $this->obterModulosEmpresa($idEmpresa);

        foreach ($modulos as $modulo) {
            UsuarioPermissao::create([
                'id_usuario' => $idUsuario,
                'id_modulo' => $modulo->id_modulo,
                'pode_ler' => true,      // Por padrão, todos podem ler
                'pode_criar' => false,
                'pode_editar' => false,
                'pode_deletar' => false,
            ]);
        }

        \Log::info('Permissões padrão criadas para usuário', [
            'id_usuario' => $idUsuario,
            'id_empresa' => $idEmpresa,
            'total_modulos' => $modulos->count()
        ]);
    }

    /**
     * Copiar permissões de um usuário para outro
     */
    public function copiarPermissoes(int $idUsuarioOrigem, int $idUsuarioDestino): bool
    {
        try {
            $permissoesOrigem = UsuarioPermissao::where('id_usuario', $idUsuarioOrigem)->get();

            UsuarioPermissao::where('id_usuario', $idUsuarioDestino)->delete();

            foreach ($permissoesOrigem as $permissao) {
                UsuarioPermissao::create([
                    'id_usuario' => $idUsuarioDestino,
                    'id_modulo' => $permissao->id_modulo,
                    'pode_ler' => $permissao->pode_ler,
                    'pode_criar' => $permissao->pode_criar,
                    'pode_editar' => $permissao->pode_editar,
                    'pode_deletar' => $permissao->pode_deletar,
                ]);
            }

            \Log::info('Permissões copiadas', [
                'de_usuario' => $idUsuarioOrigem,
                'para_usuario' => $idUsuarioDestino
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Erro ao copiar permissões', [
                'de_usuario' => $idUsuarioOrigem,
                'para_usuario' => $idUsuarioDestino,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
