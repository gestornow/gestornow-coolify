<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Http\Traits\VerificaLimite;
use App\Domain\Cliente\Services\ClienteService;
use App\Domain\Cliente\Services\ClienteImageService;
use App\Domain\Cliente\Models\Cliente;
use App\Models\RegistroAtividade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use App\Domain\Auth\Models\Empresa;

class ClienteController extends Controller
{
    use VerificaLimite;

    protected $clienteService;
    protected $clienteImageService;

    public function __construct(ClienteService $clienteService, ClienteImageService $clienteImageService)
    {
        $this->clienteService = $clienteService;
        $this->clienteImageService = $clienteImageService;
    }

    /**
     * Listar todos os clientes
     */
    public function index(Request $request)
    {
        // Coletar filtros da query string
        $filters = $request->all();
        $filters['id_empresa'] = session('id_empresa') ?? Auth::user()->id_empresa ?? null; // Segurança: força escopo da empresa da sessão para bloquear IDOR.

        // Buscar clientes através do service (paginado) - 50 itens por página
        // O service já adiciona as fotos
        $clientes = $this->clienteService->getClienteList($filters, 50);

        // Lista de empresas para o filtro
        $empresas = Empresa::orderBy('nome_empresa')->get();

        // Estatísticas (respeitando filtros básicos ou mostrando tudo se não houver filtro)
        $stats = $this->clienteService->getStatistics($filters['id_empresa'] ?? null);

        // Retornar view
        return view('cliente.index', compact('clientes', 'empresas', 'filters', 'stats'));
    }

    /**
     * Exibir formulário de criação
     */
    public function create(Request $request)
    {
        // Se for GET, mostrar formulário de criação
        if ($request->isMethod('get')) {
            $filters = [];
            $filters['id_empresa'] = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $ufs = $this->carregarUfs();
            return view('cliente.create', compact('filters', 'ufs'));
        }

        // Se for POST, criar o cliente
        try {
            // Verificar limite de clientes antes de criar
            $limiteResponse = $this->verificarLimiteCliente();
            if ($limiteResponse) {
                if ($request->wantsJson() || $request->ajax()) {
                    return $limiteResponse;
                }
                return redirect()->back()
                    ->with('error', 'Limite de clientes atingido. Faça upgrade do seu plano.')
                    ->withInput();
            }

            $idEmpresaSessao = $this->resolverIdEmpresaSessao();
            if (empty($idEmpresaSessao)) {
                return redirect()->back()
                    ->withErrors(['id_empresa' => 'Sessão da empresa não encontrada. Faça login novamente.'])
                    ->withInput();
            }

            $request->merge(['id_empresa' => (int) $idEmpresaSessao]);

            // Validação
            $validated = $request->validate([
                'id_empresa' => ['required', 'integer'],
                'nome' => ['required', 'string', 'max:255'],
                'cpf_cnpj' => [
                    'required',
                    'string',
                    'max:18',
                    Rule::unique('clientes', 'cpf_cnpj')->where(fn ($query) => $query
                        ->where('id_empresa', (int) $idEmpresaSessao)
                        ->whereNull('deleted_at')),
                ],
                'email' => [
                    'nullable',
                    'email',
                    'max:255',
                    Rule::unique('clientes', 'email')->where(fn ($query) => $query
                        ->where('id_empresa', (int) $idEmpresaSessao)
                        ->whereNull('deleted_at')),
                ],
                'telefone' => ['nullable', 'string', 'max:20'],
                'cep' => ['nullable', 'string', 'max:9'],
                'endereco' => ['nullable', 'string', 'max:255'],
                'numero' => ['nullable', 'string', 'max:50'],
                'complemento' => ['nullable', 'string', 'max:255'],
                'bairro' => ['nullable', 'string', 'max:100'],
                'cidade' => ['nullable', 'string', 'max:255'],
                'uf' => ['nullable', 'string', 'size:2'],
                'rg_ie' => ['nullable', 'string', 'max:20'],
                'razao_social' => ['nullable', 'string', 'max:255'],
                'data_nascimento' => ['nullable', 'date'],
                'status' => ['nullable', 'string', 'in:ativo,inativo,bloqueado'],
                'id_tipo_pessoa' => ['nullable', 'integer', 'in:1,2'],
                'id_filial' => ['nullable', 'integer', Rule::in([(int) $idEmpresaSessao])],
                // Campos de entrega
                'endereco_entrega' => ['nullable', 'string', 'max:255'],
                'numero_entrega' => ['nullable', 'string', 'max:50'],
                'complemento_entrega' => ['nullable', 'string', 'max:255'],
                'cep_entrega' => ['nullable', 'string', 'max:9'],
            ], [
                'id_empresa.required' => 'A empresa é obrigatória.',
                'nome.required' => 'O nome é obrigatório.',
                'email.email' => 'Email inválido.',
                'cpf_cnpj.unique' => 'Este CPF/CNPJ já está cadastrado.',
                'email.unique' => 'Este e-mail já está cadastrado para outro cliente.',
            ]);

            Log::info('=== CRIANDO NOVO CLIENTE ===', [
                'nome' => $request->input('nome'),
                'id_empresa' => $request->input('id_empresa')
            ]);

            $data = $validated;

            // Criar cliente via service
            $cliente = $this->clienteService->create($data);

            Log::info('=== CLIENTE CRIADO COM SUCESSO ===', [
                'id_clientes' => $cliente->id_clientes,
                'nome' => $cliente->nome
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Cliente criado com sucesso.',
                    'cliente_id' => $cliente->id_clientes,
                    'id_clientes' => $cliente->id_clientes,
                    'redirect' => route('clientes.editar', $cliente->id_clientes)
                ]);
            }

            // Redirecionar para a página de edição para permitir upload de imagens e anexos
            return redirect()->route('clientes.editar', $cliente->id_clientes)
                ->with('success', 'Cliente criado com sucesso! Agora você pode adicionar fotos e anexos.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('=== ERRO DE VALIDAÇÃO AO CRIAR CLIENTE ===', $e->errors());

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro de validação.',
                    'errors' => $e->errors()
                ], 422);
            }

            return redirect()->back()->withErrors($e->errors())->withInput();

        } catch (\Exception $e) {
            Log::error('=== ERRO AO CRIAR CLIENTE ===', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

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
     * Mostrar detalhes do cliente
     */
    public function show($id)
    {
        $cliente = $this->clienteService->getClienteById((int) $id);
        
        if (!$cliente) {
            abort(404);
        }

        if ((int) $cliente->id_empresa !== (int) (session('id_empresa') ?? Auth::user()->id_empresa ?? null)) {
            abort(403); // Segurança: impede visualização de cliente de outra empresa (IDOR).
        }

        return view('cliente.show', compact('cliente'));
    }

    /**
     * Retornar cliente em JSON para uso interno (Ajax)
     */
    public function getJson($id)
    {
        $cliente = $this->clienteService->getClienteById((int) $id);
        
        if (!$cliente) {
            return response()->json(['error' => 'Cliente não encontrado'], 404);
        }

        if ((int) $cliente->id_empresa !== (int) (session('id_empresa') ?? Auth::user()->id_empresa ?? null)) {
            return response()->json(['error' => 'Você não tem permissão para acessar este cliente.'], 403); // Segurança: impede acesso JSON a cliente de outra empresa (IDOR).
        }

        return response()->json($cliente);
    }

    /**
     * Retorna o log de atividades do cliente
     */
    public function logsAtividades($id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $cliente = $this->clienteService->getClienteById((int) $id);

            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente não encontrado.'
                ], 404);
            }

            if ((int) $cliente->id_empresa !== (int) $idEmpresa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar os logs deste cliente.'
                ], 403);
            }

            $logs = RegistroAtividade::query()
                ->where('id_empresa', $idEmpresa)
                ->where('entidade_tipo', 'cliente')
                ->where('entidade_id', $cliente->id_clientes)
                ->orderByDesc('ocorrido_em')
                ->limit(50)
                ->get([
                    'id_registro',
                    'acao',
                    'descricao',
                    'nome_responsavel',
                    'email_responsavel',
                    'contexto',
                    'antes',
                    'depois',
                    'icone',
                    'cor',
                    'tags',
                    'ocorrido_em',
                ]);

            return response()->json([
                'success' => true,
                'cliente' => [
                    'id_clientes' => $cliente->id_clientes,
                    'nome' => $cliente->nome,
                ],
                'logs' => $logs,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar log de atividades do cliente', [
                'id_cliente' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar log de atividades: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Editar cliente (exibe formulário de edição)
     */
    public function edit($id)
    {
        $cliente = $this->clienteService->getClienteById((int) $id);
        
        if (!$cliente) {
            abort(404);
        }

        if ((int) $cliente->id_empresa !== (int) (session('id_empresa') ?? Auth::user()->id_empresa ?? null)) {
            abort(403); // Segurança: impede edição de cliente de outra empresa (IDOR).
        }

        // Retornar view de edição dedicada
        $ufs = $this->carregarUfs();

        return view('cliente.edit', compact('cliente', 'ufs'));
    }

    /**
     * Retorna cidades por UF para preencher o cadastro de clientes.
     */
    public function cidadesPorUf(Request $request)
    {
        $uf = strtoupper(trim((string) $request->query('uf', '')));

        if (strlen($uf) !== 2) {
            return response()->json(['success' => false, 'message' => 'UF inválida.'], 422);
        }

        $tabelaCidades = $this->resolverTabelaCidades();
        $colunaUf = Schema::hasColumn($tabelaCidades, 'Uf') ? 'Uf' : 'uf';
        $colunaNome = Schema::hasColumn($tabelaCidades, 'Nome') ? 'Nome' : 'nome';

        $cidades = DB::table($tabelaCidades)
            ->select($colunaNome . ' as nome')
            ->where($colunaUf, $uf)
            ->orderBy($colunaNome)
            ->get();

        return response()->json([
            'success' => true,
            'items' => $cidades,
        ]);
    }

    /**
     * Atualizar cliente
     */
    public function update($id, Request $request)
    {
        try {
            $idEmpresaSessao = $this->resolverIdEmpresaSessao();

            if (empty($idEmpresaSessao)) {
                $clienteAtual = $this->clienteService->getClienteById((int) $id);
                if ($clienteAtual && !empty($clienteAtual->id_empresa)) {
                    $idEmpresaSessao = (int) $clienteAtual->id_empresa;
                    session(['id_empresa' => $idEmpresaSessao]);
                }
            }

            if (empty($idEmpresaSessao)) {
                return redirect()->back()
                    ->withErrors(['id_empresa' => 'Sessão da empresa não encontrada. Faça login novamente.'])
                    ->withInput();
            }

            $clienteAtual = $this->clienteService->getClienteById((int) $id);
            if (!$clienteAtual) {
                abort(404);
            }

            if ((int) $clienteAtual->id_empresa !== (int) $idEmpresaSessao) {
                abort(403); // Segurança: impede atualização de cliente de outra empresa (IDOR).
            }

            $request->merge(['id_empresa' => (int) $idEmpresaSessao]);

            // Validação
            $validated = $request->validate([
                'nome' => ['required', 'string', 'max:255'],
                'cpf_cnpj' => ['nullable', 'string', 'max:18'],
                'email' => ['nullable', 'email', 'max:255'],
                'telefone' => ['nullable', 'string', 'max:20'],
                'cep' => ['nullable', 'string', 'max:9'],
                'endereco' => ['nullable', 'string', 'max:255'],
                'numero' => ['nullable', 'string', 'max:50'],
                'complemento' => ['nullable', 'string', 'max:255'],
                'bairro' => ['nullable', 'string', 'max:100'],
                'cidade' => ['nullable', 'string', 'max:255'],
                'uf' => ['nullable', 'string', 'size:2'],
                'rg_ie' => ['nullable', 'string', 'max:20'],
                'razao_social' => ['nullable', 'string', 'max:255'],
                'data_nascimento' => ['nullable', 'date'],
                'status' => ['nullable', 'string', 'in:ativo,inativo,bloqueado'],
                'id_tipo_pessoa' => ['nullable', 'integer', 'in:1,2'],
                'id_filial' => ['nullable', 'integer', Rule::in([(int) $idEmpresaSessao])],
                // Campos de entrega
                'endereco_entrega' => ['nullable', 'string', 'max:255'],
                'numero_entrega' => ['nullable', 'string', 'max:50'],
                'complemento_entrega' => ['nullable', 'string', 'max:255'],
                'cep_entrega' => ['nullable', 'string', 'max:9'],
                // Campos de upload
                'foto' => ['nullable', 'string', 'max:255'],
                'nomeImagemCliente' => ['nullable', 'string', 'max:255'],
            ]);

            $data = $validated;
            $data['id_empresa'] = (int) $idEmpresaSessao;

            // Normalizar campos que vêm mascarados da UI
            // Remover máscaras de CPF/CNPJ, telefone, CEP
            if (isset($data['cpf_cnpj'])) {
                $data['cpf_cnpj'] = preg_replace('/[^0-9]/', '', (string) $data['cpf_cnpj']);
            }

            if (isset($data['telefone'])) {
                $data['telefone'] = preg_replace('/[^0-9]/', '', (string) $data['telefone']);
            }

            if (isset($data['cep'])) {
                $data['cep'] = preg_replace('/[^0-9]/', '', (string) $data['cep']);
            }

            if (isset($data['cep_entrega'])) {
                $data['cep_entrega'] = preg_replace('/[^0-9]/', '', (string) $data['cep_entrega']);
            }

            if (isset($data['uf'])) {
                $data['uf'] = strtoupper(trim((string) $data['uf']));
            }

            Log::info('=== ATUALIZANDO CLIENTE ===', [
                'id_clientes' => $id,
                'campos_atualizados' => array_keys($data)
            ]);

            $cliente = $this->clienteService->update((int) $id, $data);

            Log::info('=== CLIENTE ATUALIZADO COM SUCESSO ===', [
                'id_clientes' => $cliente->id_clientes
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Cliente atualizado com sucesso.',
                    'cliente' => $cliente
                ]);
            }

            return redirect()->back()->with('success', 'Cliente atualizado com sucesso.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('=== ERRO DE VALIDAÇÃO AO ATUALIZAR CLIENTE ===', [
                'id' => $id,
                'errors' => $e->errors()
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro de validação.',
                    'errors' => $e->errors()
                ], 422);
            }

            return redirect()->back()->withErrors($e->errors())->withInput();

        } catch (\Exception $e) {
            Log::error('=== ERRO AO ATUALIZAR CLIENTE ===', [
                'id' => $id,
                'erro' => $e->getMessage()
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }

            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    private function resolverIdEmpresaSessao(): ?int
    {
        $idEmpresaSessao = session('id_empresa');
        if (!empty($idEmpresaSessao)) {
            return (int) $idEmpresaSessao;
        }

        if (Auth::check() && !empty(Auth::user()->id_empresa)) {
            $idEmpresaSessao = (int) Auth::user()->id_empresa;
            session(['id_empresa' => $idEmpresaSessao]);
            return $idEmpresaSessao;
        }

        return null;
    }

    private function carregarUfs(): array
    {
        $tabelaEstados = $this->resolverTabelaEstados();
        $colunaUf = Schema::hasColumn($tabelaEstados, 'Uf') ? 'Uf' : 'uf';
        $colunaNome = Schema::hasColumn($tabelaEstados, 'Nome') ? 'Nome' : 'nome';

        return DB::table($tabelaEstados)
            ->select($colunaUf . ' as uf', $colunaNome . ' as nome')
            ->orderBy($colunaNome)
            ->get()
            ->map(fn ($item) => [
                'uf' => strtoupper((string) $item->uf),
                'nome' => (string) $item->nome,
            ])
            ->toArray();
    }

    private function resolverTabelaCidades(): string
    {
        if (Schema::hasTable('municipio')) {
            return 'municipio';
        }

        if (Schema::hasTable('cidades')) {
            return 'cidades';
        }

        throw new \RuntimeException('Tabela de cidades não encontrada. Esperado: municipio ou cidades.');
    }

    private function resolverTabelaEstados(): string
    {
        if (Schema::hasTable('estados')) {
            return 'estados';
        }

        if (Schema::hasTable('estado')) {
            return 'estado';
        }

        throw new \RuntimeException('Tabela de estados não encontrada. Esperado: estados ou estado.');
    }

    /**
     * Deletar cliente
     */
    public function destroy($id, Request $request)
    {
        try {
            $clienteAtual = $this->clienteService->getClienteById((int) $id);
            if (!$clienteAtual) {
                abort(404);
            }

            if ((int) $clienteAtual->id_empresa !== (int) (session('id_empresa') ?? Auth::user()->id_empresa ?? null)) {
                abort(403); // Segurança: impede exclusão de cliente de outra empresa (IDOR).
            }

            Log::info('=== DELETANDO CLIENTE ===', ['id_clientes' => $id]);

            $this->clienteService->destroy((int) $id);

            Log::info('=== CLIENTE DELETADO COM SUCESSO ===', ['id_clientes' => $id]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Cliente deletado com sucesso.'
                ]);
            }

            return redirect()->route('clientes.index')->with('success', 'Cliente deletado com sucesso.');

        } catch (\Exception $e) {
            Log::error('=== ERRO AO DELETAR CLIENTE ===', [
                'id' => $id,
                'erro' => $e->getMessage()
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Deletar múltiplos clientes
     */
    public function excluirMultiplos(Request $request)
    {
        try {
            $ids = $request->input('ids', []);

            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhum cliente selecionado.'
                ], 400);
            }

            Log::info('=== DELETANDO MÚLTIPLOS CLIENTES ===', [
                'qtd' => count($ids),
                'ids' => $ids
            ]);

            $idEmpresaSessao = (int) (session('id_empresa') ?? Auth::user()->id_empresa ?? 0);
            $deletedCount = Cliente::whereIn('id_clientes', $ids)
                ->where('id_empresa', $idEmpresaSessao)
                ->delete(); // Segurança: restringe exclusão em lote à empresa da sessão para bloquear IDOR.

            Log::info('=== CLIENTES DELETADOS COM SUCESSO ===', [
                'qtd_deletados' => $deletedCount
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$deletedCount} cliente(s) deletado(s) com sucesso."
            ]);

        } catch (\Exception $e) {
            Log::error('=== ERRO AO DELETAR MÚLTIPLOS CLIENTES ===', [
                'erro' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Invalidar cache de fotos dos clientes
     */
    public function invalidarCacheFotos(Request $request)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null; // Segurança: ignora id_empresa do payload para bloquear IDOR.

            if (!$idEmpresa) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID da empresa não fornecido.'
                ], 400);
            }

            // Invalidar cache através do service
            $this->clienteImageService->invalidateCache((int) $idEmpresa);

            Log::info('=== CACHE DE FOTOS DE CLIENTES INVALIDADO ===', [
                'id_empresa' => $idEmpresa
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cache de fotos invalidado com sucesso.'
            ]);

        } catch (\Exception $e) {
            Log::error('=== ERRO AO INVALIDAR CACHE DE FOTOS DE CLIENTES ===', [
                'erro' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
