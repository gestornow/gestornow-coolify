<?php

namespace App\Http\Controllers\Produto;

use App\Http\Controllers\Controller;
use App\Domain\Produto\Models\Produto;
use App\Domain\Produto\Models\ProdutoAcessorio;
use App\Domain\Produto\Models\Acessorio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ProdutoAcessorioController extends Controller
{
    /**
     * Listar acessórios de um produto
     */
    public function index(Request $request, $idProduto)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $produto = Produto::where('id_produto', $idProduto)
            ->where('id_empresa', $idEmpresa)
            ->first();

        if (!$produto) {
            return response()->json(['success' => false, 'message' => 'Produto não encontrado.'], 404);
        }

        $acessorios = $produto->acessorios()->get();

        return response()->json($acessorios);
    }

    /**
     * Vincular acessório a um produto
     */
    public function store(Request $request, $idProduto)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $validated = $request->validate([
                'id_acessorio' => [
                    'required',
                    'integer',
                    Rule::exists('acessorios', 'id_acessorio')->where(function ($query) use ($idEmpresa) {
                        $query->where('id_empresa', $idEmpresa);
                    }),
                ],
                'quantidade' => ['nullable', 'integer', 'min:1'],
                'obrigatorio' => ['nullable', 'boolean'],
            ]);

            $produto = Produto::where('id_produto', $idProduto)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$produto) {
                throw new \Exception('Produto não encontrado.');
            }

            $acessorio = Acessorio::where('id_empresa', $idEmpresa)
                ->find($validated['id_acessorio']);

            if (!$acessorio) {
                throw new \Exception('Acessório não encontrado para a empresa da sessão.'); // Segurança: bloqueia vínculo com acessório de outra empresa (IDOR).
            }

            // Verificar se já existe vínculo
            $existe = ProdutoAcessorio::where('id_produto', $idProduto)
                ->where('id_acessorio', $validated['id_acessorio'])
                ->exists();

            if ($existe) {
                throw new \Exception('Este acessório já está vinculado ao produto.');
            }

            $produtoAcessorio = ProdutoAcessorio::create([
                'id_produto' => $idProduto,
                'id_acessorio' => $validated['id_acessorio'],
                'quantidade' => $validated['quantidade'] ?? 1,
                'obrigatorio' => $validated['obrigatorio'] ?? false,
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Acessório vinculado com sucesso.',
                    'data' => [
                        'id_produto_acessorio' => $produtoAcessorio->id_produto_acessorio,
                        'acessorio' => $acessorio,
                        'quantidade' => $produtoAcessorio->quantidade,
                        'obrigatorio' => $produtoAcessorio->obrigatorio,
                    ]
                ]);
            }

            return redirect()->route('produtos.show', $idProduto)->with('success', 'Acessório vinculado com sucesso.');

        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }

            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Atualizar vínculo de acessório
     */
    public function update(Request $request, $idProduto, $idAcessorio)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $produto = Produto::where('id_produto', $idProduto)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$produto) {
                throw new \Exception('Produto não encontrado.');
            }

            $produtoAcessorio = ProdutoAcessorio::where('id_produto', $idProduto)
                ->where('id_acessorio', $idAcessorio)
                ->first();

            if (!$produtoAcessorio) {
                throw new \Exception('Vínculo não encontrado.');
            }

            $produtoAcessorio->update([
                'quantidade' => $request->quantidade ?? $produtoAcessorio->quantidade,
                'obrigatorio' => $request->has('obrigatorio') ? $request->obrigatorio : $produtoAcessorio->obrigatorio,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Vínculo atualizado com sucesso.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remover vínculo de acessório
     */
    public function destroy(Request $request, $idProduto, $idAcessorio)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $produto = Produto::where('id_produto', $idProduto)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$produto) {
                throw new \Exception('Produto não encontrado.');
            }

            $deleted = ProdutoAcessorio::where('id_produto', $idProduto)
                ->where('id_acessorio', $idAcessorio)
                ->delete();

            if (!$deleted) {
                throw new \Exception('Vínculo não encontrado.');
            }

            return response()->json([
                'success' => true,
                'message' => 'Acessório desvinculado com sucesso.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sincronizar acessórios de um produto (substituir todos)
     */
    public function sync(Request $request, $idProduto)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $produto = Produto::where('id_produto', $idProduto)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$produto) {
                throw new \Exception('Produto não encontrado.');
            }

            $acessorios = $request->input('acessorios', []);
            
            // Remover vínculos antigos
            ProdutoAcessorio::where('id_produto', $idProduto)->delete();

            // Criar novos vínculos
            foreach ($acessorios as $item) {
                ProdutoAcessorio::create([
                    'id_produto' => $idProduto,
                    'id_acessorio' => $item['id_acessorio'],
                    'quantidade' => $item['quantidade'] ?? 1,
                    'obrigatorio' => $item['obrigatorio'] ?? false,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Acessórios sincronizados com sucesso.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
