<?php

namespace App\Http\Controllers;

use App\Services\PermissaoService;
use InvalidArgumentException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GrupoPermissaoController extends Controller
{
    private PermissaoService $permissoes;

    public function __construct(PermissaoService $permissoes)
    {
        $this->permissoes = $permissoes;
    }

    public function index()
    {
        $idEmpresa = (int) session('id_empresa');
        $grupos = $this->permissoes->grupos($idEmpresa);

        return view('configuracoes.grupos-permissoes.index', compact('grupos'));
    }

    public function create()
    {
        $catalogo = $this->permissoes->todasAsChaves();

        return view('configuracoes.grupos-permissoes.create', [
            'catalogo' => $catalogo,
        ]);
    }

    public function store(Request $request)
    {
        $idEmpresa = (int) session('id_empresa');

        $dados = $request->validate([
            'nome' => [
                'required',
                'string',
                'max:100',
                Rule::unique('grupos_permissoes', 'nome')->where(fn ($q) => $q->where('id_empresa', $idEmpresa)),
            ],
            'descricao' => ['nullable', 'string', 'max:255'],
            'chaves' => ['nullable', 'array'],
            'chaves.*' => ['string', 'max:100'],
        ]);

        try {
            $this->permissoes->criarGrupo(
                $idEmpresa,
                $dados['nome'],
                $dados['descricao'] ?? null,
                $dados['chaves'] ?? []
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['chaves' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('configuracoes.grupos-permissoes.index')
            ->with('success', 'Grupo de permissoes criado com sucesso.');
    }

    public function edit(int $id)
    {
        $idEmpresa = (int) session('id_empresa');
        $grupo = DB::table('grupos_permissoes')
            ->where('id_grupo', $id)
            ->where('id_empresa', $idEmpresa)
            ->first();

        abort_unless($grupo, 404);

        return view('configuracoes.grupos-permissoes.edit', [
            'grupo' => $grupo,
            'catalogo' => $this->permissoes->todasAsChaves(),
            'chavesSelecionadas' => $this->permissoes->chavesDoGrupo($id),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $idEmpresa = (int) session('id_empresa');
        $grupo = DB::table('grupos_permissoes')
            ->where('id_grupo', $id)
            ->where('id_empresa', $idEmpresa)
            ->first();

        abort_unless($grupo, 404);

        $dados = $request->validate([
            'nome' => [
                'required',
                'string',
                'max:100',
                Rule::unique('grupos_permissoes', 'nome')
                    ->where(fn ($q) => $q->where('id_empresa', $idEmpresa))
                    ->ignore($id, 'id_grupo'),
            ],
            'descricao' => ['nullable', 'string', 'max:255'],
            'chaves' => ['nullable', 'array'],
            'chaves.*' => ['string', 'max:100'],
        ]);

        try {
            $this->permissoes->atualizarGrupo(
                $id,
                $dados['nome'],
                $dados['descricao'] ?? null,
                $dados['chaves'] ?? []
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['chaves' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('configuracoes.grupos-permissoes.index')
            ->with('success', 'Grupo de permissoes atualizado com sucesso.');
    }

    public function destroy(int $id)
    {
        $idEmpresa = (int) session('id_empresa');

        $grupo = DB::table('grupos_permissoes')
            ->where('id_grupo', $id)
            ->where('id_empresa', $idEmpresa)
            ->first();

        abort_unless($grupo, 404);

        $temUsuarios = DB::table('usuario_grupo')
            ->where('id_grupo', $id)
            ->where('id_empresa', $idEmpresa)
            ->exists();

        if ($temUsuarios) {
            return back()->with('error', 'Nao e possivel excluir: ha usuarios vinculados a este grupo.');
        }

        DB::table('grupos_permissoes')
            ->where('id_grupo', $id)
            ->delete();

        return redirect()
            ->route('configuracoes.grupos-permissoes.index')
            ->with('success', 'Grupo de permissoes excluido com sucesso.');
    }
}
