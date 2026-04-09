<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PermissaoService
{
    public function pode(mixed $usuario, string $chave): bool
    {
        if (trim($chave) === '') {
            return false;
        }

        [$idUsuario, $idEmpresa, $isSuporte] = $this->resolverUsuario($usuario);

        if ($isSuporte) {
            return true;
        }

        if ($idUsuario <= 0 || $idEmpresa <= 0) {
            return false;
        }

        $chaves = $this->carregar($idUsuario, $idEmpresa);

        return in_array($chave, $chaves, true);
    }

    public function carregar(int $idUsuario, int $idEmpresa): array
    {
        $cacheKey = $this->cacheKey($idUsuario, $idEmpresa);

        return Cache::remember($cacheKey, 300, function () use ($idUsuario, $idEmpresa) {
            // Regra de precedencia:
            // 1) grupo local da empresa (sobrescreve global)
            // 2) perfil global da empresa
            $temGrupoLocal = DB::table('usuario_grupo')
                ->where('id_usuario', $idUsuario)
                ->where('id_empresa', $idEmpresa)
                ->exists();

            if ($temGrupoLocal) {
                return DB::table('usuario_grupo as ug')
                    ->join('grupos_permissoes as gp', function ($join) {
                        $join->on('gp.id_grupo', '=', 'ug.id_grupo')
                            ->on('gp.id_empresa', '=', 'ug.id_empresa');
                    })
                    ->leftJoin('grupos_permissoes_itens as gpi', 'gpi.id_grupo', '=', 'gp.id_grupo')
                    ->where('ug.id_usuario', $idUsuario)
                    ->where('ug.id_empresa', $idEmpresa)
                    ->pluck('gpi.chave')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }

            return DB::table('usuario_perfil_global as upg')
                ->join('perfil_global as pg', 'pg.id_perfil_global', '=', 'upg.id_perfil_global')
                ->leftJoin('perfil_global_itens as pgi', 'pgi.id_perfil_global', '=', 'pg.id_perfil_global')
                ->where('upg.id_usuario', $idUsuario)
                ->where('upg.id_empresa', $idEmpresa)
                ->where('pg.ativo', 1)
                ->pluck('pgi.chave')
                ->filter()
                ->unique()
                ->values()
                ->all();
        });
    }

    public function limparCache(int $idUsuario, int $idEmpresa): void
    {
        Cache::forget($this->cacheKey($idUsuario, $idEmpresa));
    }

    public function grupos(int $idEmpresa): array
    {
        return DB::table('grupos_permissoes')
            ->where('id_empresa', $idEmpresa)
            ->orderBy('nome')
            ->get()
            ->toArray();
    }

    public function perfisGlobais(): array
    {
        return DB::table('perfil_global')
            ->where('ativo', 1)
            ->orderBy('nome')
            ->get(['id_perfil_global', 'codigo', 'nome', 'descricao'])
            ->toArray();
    }

    public function perfilGlobalDoUsuario(int $idUsuario, int $idEmpresa): ?int
    {
        $idPerfil = DB::table('usuario_perfil_global')
            ->where('id_usuario', $idUsuario)
            ->where('id_empresa', $idEmpresa)
            ->value('id_perfil_global');

        return $idPerfil ? (int) $idPerfil : null;
    }

    public function chavesDoGrupo(int $idGrupo): array
    {
        return DB::table('grupos_permissoes_itens')
            ->where('id_grupo', $idGrupo)
            ->orderBy('chave')
            ->pluck('chave')
            ->all();
    }

    public function todasAsChaves(): array
    {
        return DB::table('permissoes_chaves')
            ->orderBy('modulo')
            ->orderBy('label')
            ->get(['chave', 'modulo', 'label'])
            ->groupBy('modulo')
            ->map(static fn ($itens) => $itens->values()->all())
            ->toArray();
    }

    public function criarGrupo(int $idEmpresa, string $nome, ?string $descricao, array $chaves): int
    {
        $chavesValidas = $this->validarChaves($chaves);

        return DB::transaction(function () use ($idEmpresa, $nome, $descricao, $chavesValidas) {
            $idGrupo = DB::table('grupos_permissoes')->insertGetId([
                'id_empresa' => $idEmpresa,
                'nome' => trim($nome),
                'descricao' => $descricao,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'id_grupo');

            $this->sincronizarItensGrupo($idGrupo, $chavesValidas);

            return (int) $idGrupo;
        });
    }

    public function atualizarGrupo(int $idGrupo, string $nome, ?string $descricao, array $chaves): void
    {
        $grupo = DB::table('grupos_permissoes')
            ->where('id_grupo', $idGrupo)
            ->first(['id_grupo', 'id_empresa']);

        if (!$grupo) {
            throw new InvalidArgumentException('Grupo de permissao nao encontrado.');
        }

        $chavesValidas = $this->validarChaves($chaves);

        $usuariosAfetados = DB::table('usuario_grupo')
            ->where('id_grupo', $idGrupo)
            ->get(['id_usuario', 'id_empresa']);

        DB::transaction(function () use ($idGrupo, $nome, $descricao, $chavesValidas) {
            DB::table('grupos_permissoes')
                ->where('id_grupo', $idGrupo)
                ->update([
                    'nome' => trim($nome),
                    'descricao' => $descricao,
                    'updated_at' => now(),
                ]);

            DB::table('grupos_permissoes_itens')
                ->where('id_grupo', $idGrupo)
                ->delete();

            $this->sincronizarItensGrupo($idGrupo, $chavesValidas);
        });

        foreach ($usuariosAfetados as $usuario) {
            $this->limparCache((int) $usuario->id_usuario, (int) $usuario->id_empresa);
        }
    }

    public function atribuirGrupo(int $idUsuario, int $idEmpresa, int $idGrupo): void
    {
        $grupo = DB::table('grupos_permissoes')
            ->where('id_grupo', $idGrupo)
            ->where('id_empresa', $idEmpresa)
            ->exists();

        if (!$grupo) {
            throw new InvalidArgumentException('Grupo invalido para a empresa informada.');
        }

        DB::table('usuario_grupo')->updateOrInsert(
            [
                'id_usuario' => $idUsuario,
                'id_empresa' => $idEmpresa,
            ],
            [
                'id_grupo' => $idGrupo,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->limparCache($idUsuario, $idEmpresa);
    }

    public function atribuirPerfilGlobal(int $idUsuario, int $idEmpresa, int $idPerfilGlobal): void
    {
        $perfilAtivo = DB::table('perfil_global')
            ->where('id_perfil_global', $idPerfilGlobal)
            ->where('ativo', 1)
            ->first(['codigo']);

        if (!$perfilAtivo) {
            throw new InvalidArgumentException('Perfil global invalido.');
        }

        DB::table('usuario_perfil_global')->updateOrInsert(
            [
                'id_usuario' => $idUsuario,
                'id_empresa' => $idEmpresa,
            ],
            [
                'id_perfil_global' => $idPerfilGlobal,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $finalidade = $this->resolverFinalidadePorCodigoPerfil((string) ($perfilAtivo->codigo ?? ''));
        if ($finalidade !== null) {
            DB::table('usuarios')
                ->where('id_usuario', $idUsuario)
                ->where('id_empresa', $idEmpresa)
                ->update([
                    'finalidade' => $finalidade,
                    'updated_at' => now(),
                ]);
        }

        $this->limparCache($idUsuario, $idEmpresa);
    }

    public function removerPerfilGlobal(int $idUsuario, int $idEmpresa): void
    {
        DB::table('usuario_perfil_global')
            ->where('id_usuario', $idUsuario)
            ->where('id_empresa', $idEmpresa)
            ->delete();

        DB::table('usuarios')
            ->where('id_usuario', $idUsuario)
            ->where('id_empresa', $idEmpresa)
            ->update([
                'finalidade' => null,
                'updated_at' => now(),
            ]);

        $this->limparCache($idUsuario, $idEmpresa);
    }

    public function removerGrupo(int $idUsuario, int $idEmpresa): void
    {
        DB::table('usuario_grupo')
            ->where('id_usuario', $idUsuario)
            ->where('id_empresa', $idEmpresa)
            ->delete();

        $this->limparCache($idUsuario, $idEmpresa);
    }

    private function resolverUsuario(mixed $usuario): array
    {
        $idUsuario = is_numeric($usuario)
            ? (int) $usuario
            : (int) data_get($usuario, 'id_usuario', 0);
        $idEmpresa = (int) data_get($usuario, 'id_empresa', session('id_empresa'));
        $isSuporte = (int) data_get($usuario, 'is_suporte', 0) === 1;

        return [$idUsuario, $idEmpresa, $isSuporte];
    }

    private function validarChaves(array $chaves): array
    {
        $chaves = array_values(array_unique(array_filter(array_map('trim', $chaves))));

        if (empty($chaves)) {
            return [];
        }

        $chavesValidas = DB::table('permissoes_chaves')
            ->whereIn('chave', $chaves)
            ->pluck('chave')
            ->all();

        $invalidas = array_values(array_diff($chaves, $chavesValidas));
        if (!empty($invalidas)) {
            throw new InvalidArgumentException('Chaves invalidas: ' . implode(', ', $invalidas));
        }

        return $chaves;
    }

    private function sincronizarItensGrupo(int $idGrupo, array $chaves): void
    {
        if (empty($chaves)) {
            return;
        }

        $linhas = array_map(static fn (string $chave) => [
            'id_grupo' => $idGrupo,
            'chave' => $chave,
        ], $chaves);

        DB::table('grupos_permissoes_itens')->insert($linhas);
    }

    private function resolverFinalidadePorCodigoPerfil(?string $codigo): ?string
    {
        $codigoNormalizado = strtolower(trim((string) $codigo));

        return match ($codigoNormalizado) {
            'admin', 'administrador' => 'administrador',
            'atendimento' => 'atendimento',
            'comercial' => 'comercial',
            'expedicao' => 'expedicao',
            'financeiro' => 'financeiro',
            default => null,
        };
    }

    private function cacheKey(int $idUsuario, int $idEmpresa): string
    {
        return "perm.{$idUsuario}.{$idEmpresa}";
    }
}
