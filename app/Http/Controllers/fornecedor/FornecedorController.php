<?php

namespace App\Http\Controllers\fornecedor;

use App\Http\Controllers\Controller;
use App\Models\Fornecedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class FornecedorController extends Controller
{
    public function index(Request $request)
    {
        $idEmpresa = $this->resolverIdEmpresaSessao();

        $query = Fornecedor::query()
            ->where('id_empresa', $idEmpresa); // Seguranca: restringe a consulta a empresa da sessao para bloquear IDOR.

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('id_tipo_pessoa')) {
            $query->where('id_tipo_pessoa', (int) $request->input('id_tipo_pessoa'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('nome', 'like', "%{$search}%")
                    ->orWhere('razao_social', 'like', "%{$search}%")
                    ->orWhere('nome_empresa', 'like', "%{$search}%")
                    ->orWhere('cpf_cnpj', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('telefone', 'like', "%{$search}%");
            });
        }

        $fornecedores = $query
            ->orderBy('nome')
            ->paginate(50)
            ->appends($request->query());

        $statsBase = Fornecedor::query()
            ->where('id_empresa', $idEmpresa);

        $stats = [
            'total' => (clone $statsBase)->count(),
            'ativos' => (clone $statsBase)->where('status', 'ativo')->count(),
            'pessoa_fisica' => (clone $statsBase)->where('id_tipo_pessoa', 1)->count(),
            'pessoa_juridica' => (clone $statsBase)->where('id_tipo_pessoa', 2)->count(),
        ];

        $filters = $request->all();

        return view('fornecedor.index', compact('fornecedores', 'stats', 'filters'));
    }

    public function create()
    {
        return view('fornecedor.create');
    }

    public function store(Request $request)
    {
        $idEmpresa = $this->resolverIdEmpresaSessao();

        $validated = $request->validate($this->rules());

        $data = $this->normalizarDados($validated);
        $data['id_empresa'] = $idEmpresa;

        $fornecedor = Fornecedor::create($data);

        Log::info('Fornecedor criado com sucesso', [
            'id_fornecedores' => $fornecedor->id_fornecedores,
            'id_empresa' => $idEmpresa,
        ]);

        return redirect()
            ->route('fornecedores.editar', $fornecedor->id_fornecedores)
            ->with('success', 'Fornecedor criado com sucesso.');
    }

    public function edit($id)
    {
        $idEmpresa = $this->resolverIdEmpresaSessao();

        $fornecedor = Fornecedor::query()
            ->where('id_empresa', $idEmpresa) // Seguranca: restringe a consulta a empresa da sessao para bloquear IDOR.
            ->findOrFail((int) $id);

        return view('fornecedor.edit', compact('fornecedor'));
    }

    public function update($id, Request $request)
    {
        $idEmpresa = $this->resolverIdEmpresaSessao();

        $fornecedor = Fornecedor::query()
            ->where('id_empresa', $idEmpresa) // Seguranca: restringe a consulta a empresa da sessao para bloquear IDOR.
            ->findOrFail((int) $id);

        $validated = $request->validate($this->rules());

        $data = $this->normalizarDados($validated);
        $data['id_empresa'] = $idEmpresa;

        $fornecedor->update($data);

        Log::info('Fornecedor atualizado com sucesso', [
            'id_fornecedores' => $fornecedor->id_fornecedores,
            'id_empresa' => $idEmpresa,
        ]);

        return redirect()
            ->back()
            ->with('success', 'Fornecedor atualizado com sucesso.');
    }

    public function destroy($id, Request $request)
    {
        $idEmpresa = $this->resolverIdEmpresaSessao();

        $fornecedor = Fornecedor::query()
            ->where('id_empresa', $idEmpresa) // Seguranca: restringe a consulta a empresa da sessao para bloquear IDOR.
            ->findOrFail((int) $id);

        $fornecedor->delete();

        Log::info('Fornecedor excluido com sucesso', [
            'id_fornecedores' => $fornecedor->id_fornecedores,
            'id_empresa' => $idEmpresa,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Fornecedor excluido com sucesso.',
            ]);
        }

        return redirect()
            ->route('fornecedores.index')
            ->with('success', 'Fornecedor excluido com sucesso.');
    }

    public function excluirMultiplos(Request $request)
    {
        $idEmpresa = $this->resolverIdEmpresaSessao();

        $ids = collect($request->input('ids', []))
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->values();

        if ($ids->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhum fornecedor selecionado.',
            ], 422);
        }

        $deletedCount = Fornecedor::query()
            ->where('id_empresa', $idEmpresa) // Seguranca: restringe exclusao em lote a empresa da sessao para bloquear IDOR.
            ->whereIn('id_fornecedores', $ids)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "{$deletedCount} fornecedor(es) excluido(s) com sucesso.",
        ]);
    }

    private function resolverIdEmpresaSessao(): int
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        abort_unless(!empty($idEmpresa), 403, 'Empresa da sessao nao encontrada.');

        return (int) $idEmpresa;
    }

    private function normalizarDados(array $data): array
    {
        if (isset($data['cpf_cnpj'])) {
            $data['cpf_cnpj'] = preg_replace('/[^0-9]/', '', (string) $data['cpf_cnpj']);
        }

        if (isset($data['telefone'])) {
            $data['telefone'] = preg_replace('/[^0-9]/', '', (string) $data['telefone']);
        }

        if (isset($data['cep'])) {
            $data['cep'] = preg_replace('/[^0-9]/', '', (string) $data['cep']);
        }

        if (isset($data['uf']) && !empty($data['uf'])) {
            $data['uf'] = strtoupper(trim((string) $data['uf']));
        }

        return $data;
    }

    private function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'razao_social' => ['nullable', 'string', 'max:255'],
            'nome_empresa' => ['nullable', 'string', 'max:255'],
            'id_tipo_pessoa' => ['required', 'integer', Rule::in([1, 2])],
            'cpf_cnpj' => ['nullable', 'string', 'max:18'],
            'rg_ie' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'contato_nome' => ['nullable', 'string', 'max:255'],
            'contato_cargo' => ['nullable', 'string', 'max:255'],
            'data_abertura' => ['nullable', 'date'],
            'data_nascimento' => ['nullable', 'date'],
            'prazo_medio_entrega_dias' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'banco_agencia' => ['nullable', 'string', 'max:100'],
            'banco_conta' => ['nullable', 'string', 'max:100'],
            'observacoes' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(['ativo', 'inativo', 'bloqueado'])],
            'cep' => ['nullable', 'string', 'max:9'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:50'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', 'string', 'max:2'],
        ];
    }
}
