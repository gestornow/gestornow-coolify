<?php

namespace App\Http\Controllers\Usuario;

use App\Http\Controllers\Controller;
use App\Domain\User\Services\UserService;
use App\Services\PermissaoService as PermissaoGrupoService;
use App\Facades\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Domain\Auth\Models\Empresa;
use App\Http\Traits\VerificaLimite;

class UserController extends Controller
{
    use VerificaLimite;

    protected $userService;
    protected $imageService;

    public function __construct(
        UserService $userService,
        \App\Domain\User\Services\UserImageService $imageService
    ) {
        $this->userService = $userService;
        $this->imageService = $imageService;
    }

    public function index(Request $request)
    {
        abort_unless($this->podeAdmin('usuarios.visualizar'), 403);

        // Collect filters from query string
        $filters = $request->all();
        $idEmpresaSessao = (int) (session('id_empresa') ?? Auth::user()->id_empresa ?? 0);
        $filters['id_empresa'] = $idEmpresaSessao; // Segurança: força escopo da empresa da sessão para bloquear IDOR.

        // Busca os usuários através do service (paginado) - 50 itens por página
        $users = $this->userService->getUserList($filters, 50);

        // Lista de empresas para o filtro
        $empresas = Empresa::orderBy('nome_empresa')->get();

        // Estatísticas (respeitando filtros básicos)
        $stats = [
            'total' => $this->userService->countUsers($filters),
            'ativos' => $this->userService->countUsers(array_merge($filters, ['status' => 'ativo'])),
            'inativos' => $this->userService->countUsers(array_merge($filters, ['status' => 'inativo'])),
            'bloqueados' => $this->userService->countUsers(array_merge($filters, ['status' => 'bloqueado'])),
        ];

        // O Controller é responsável por retornar a view
        return view('usuario.index', compact('users', 'empresas', 'filters', 'stats'));
    }

    /**
     * Mostrar detalhes do usuário
     */
    public function show($id)
    {
        abort_unless($this->podeAdmin('usuarios.visualizar'), 403);

        $user = $this->userService->getUserById((int) $id);
        if (!$user) {
            abort(404);
        }

        if ((int) $user->id_empresa !== (int) (session('id_empresa') ?? Auth::user()->id_empresa ?? null)) {
            abort(403); // Segurança: impede acesso a usuário de outra empresa (IDOR).
        }

        return view('usuario.show', compact('user'));
    }

    /**
     * Editar usuário (exibe formulário de edição)
     */
    public function edit($id)
    {
        abort_unless($this->podeAdmin('usuarios.editar'), 403);

        $idEmpresa = (int) (session('id_empresa') ?? Auth::user()->id_empresa ?? 0);
        $user = $this->userService->getUserById((int) $id);
        if (!$user) {
            abort(404);
        }

        if ((int) $user->id_empresa !== $idEmpresa) {
            abort(403); // Segurança: impede edição de usuário de outra empresa (IDOR).
        }

        $servicoPermissoes = app(PermissaoGrupoService::class);
        $grupos = $servicoPermissoes->grupos($idEmpresa);
        $perfisGlobais = $servicoPermissoes->perfisGlobais();
        $grupoVinculado = DB::table('usuario_grupo')
            ->where('id_usuario', (int) $user->id_usuario)
            ->where('id_empresa', $idEmpresa)
            ->value('id_grupo');
        $perfilGlobalVinculado = $servicoPermissoes->perfilGlobalDoUsuario((int) $user->id_usuario, $idEmpresa);
        $user->id_grupo = $grupoVinculado ? (int) $grupoVinculado : null;
        $user->id_perfil_global = $perfilGlobalVinculado;

        // Retorna view de edição dedicada
        return view('usuario.edit', compact('user', 'grupos', 'perfisGlobais'));
    }

    public function create(Request $request)
    {
        abort_unless($this->podeAdmin('usuarios.criar'), 403);

        $idEmpresaSessao = (int) (session('id_empresa') ?? 0);

        // If this is a GET request, show the create form
        if ($request->isMethod('get')) {
            $idEmpresa = $idEmpresaSessao > 0
                ? $idEmpresaSessao
                : (int) (Auth::user()->id_empresa ?? 0);
            $servicoPermissoes = app(PermissaoGrupoService::class);
            $grupos = $servicoPermissoes->grupos($idEmpresa);
            $perfisGlobais = $servicoPermissoes->perfisGlobais();
            $filters = ['id_empresa' => $idEmpresa];
            return view('usuario.create', compact('filters', 'grupos', 'perfisGlobais'));
        }

        if ($idEmpresaSessao <= 0) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa da sessão não encontrada. Selecione a empresa ativa antes de cadastrar o usuário.'
                ], 422);
            }

            return redirect()->back()
                ->withErrors(['id_empresa' => 'Empresa da sessão não encontrada. Selecione a empresa ativa antes de cadastrar o usuário.'])
                ->withInput();
        }

        // Verificar limite de usuários
        $limiteCheck = $this->verificarLimiteUsuario();
        if ($limiteCheck) {
            return $limiteCheck;
        }

        try {
            // Usar validação customizada
            $validated = $request->validate([
                'login' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('usuarios', 'login')->whereNull('deleted_at'),
                ],
                'nome' => ['required', 'string', 'max:255'],
                'telefone' => ['nullable', 'string', 'max:50'],
                'cpf' => ['nullable', 'string', 'max:20'],
                'is_suporte' => ['nullable', 'boolean'],
                'endereco' => ['nullable', 'string', 'max:255'],
                'cep' => ['nullable', 'string', 'max:10'],
                'bairro' => ['nullable', 'string', 'max:100'],
                'comissao' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'observacoes' => ['nullable', 'string'],
                'metodo_senha' => ['required', 'in:email,direto'],
                'senha' => $request->input('metodo_senha') === 'direto' 
                    ? ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->letters()->numbers()]
                    : ['nullable'],
                'senha_confirmation' => $request->input('metodo_senha') === 'direto' 
                    ? ['required']
                    : ['nullable'],
                'id_grupo' => ['nullable', 'integer'],
                'id_perfil_global' => ['nullable', 'integer'],
            ], [
                'login.required' => 'O login é obrigatório.',
                'login.unique' => 'Este login já está sendo utilizado.',
                'nome.required' => 'O nome é obrigatório.',
                'metodo_senha.required' => 'Escolha como definir a senha.',
                'senha.required' => 'A senha é obrigatória.',
                'senha.confirmed' => 'A confirmação da senha não confere.',
                'senha.min' => 'A senha deve ter pelo menos 8 caracteres.',
                'senha.letters' => 'A senha deve conter pelo menos uma letra.',
                'senha.numbers' => 'A senha deve conter pelo menos um número.',
                'senha_confirmation.required' => 'A confirmação da senha é obrigatória.',
            ]);

            $metodo_senha = $request->input('metodo_senha', 'email');
            $request->merge(['id_empresa' => $idEmpresaSessao]);
            
            \Log::info('=== CRIANDO NOVO USUÁRIO ===', [
                'login' => $request->input('login'),
                'metodo_senha' => $metodo_senha,
                'dados_request' => $request->except(['_token', 'senha', 'senha_confirmation'])
            ]);

            $data = $request->only([
                'login',
                'nome',
                'telefone',
                'cpf',
                'is_suporte',
                'endereco',
                'cep',
                'bairro',
                'comissao',
                'observacoes',
                'metodo_senha',
                'senha',
                'senha_confirmation',
            ]); // Segurança: limita persistência a campos esperados para evitar mass assignment.
            $data['id_empresa'] = $idEmpresaSessao;
            $idGrupoSelecionado = $request->filled('id_grupo') ? (int) $request->input('id_grupo') : null;
            $idPerfilGlobalSelecionado = $request->filled('id_perfil_global') ? (int) $request->input('id_perfil_global') : null;

            unset($data['id_grupo']);
            unset($data['id_perfil_global']);

            // Se o método é direto, adicionar senha agora
            if ($metodo_senha === 'direto' && $request->filled('senha')) {
                $data['senha'] = \Hash::make($request->get('senha'));
                \Log::info('=== SENHA FORNECIDA DIRETAMENTE ===', ['login' => $request->input('login')]);
            } else if ($metodo_senha === 'email') {
                // Se for email, gerar token para criar senha
                $token = \Str::random(60);
                $data['codigo_reset'] = $token;
                \Log::info('=== TOKEN GERADO PARA CRIAR SENHA ===', [
                    'login' => $request->input('login'),
                    'token' => substr($token, 0, 10) . '...'
                ]);
            }

            // Criar usuário via service
            $usuario = $this->userService->create($data);

            if ($idGrupoSelecionado) {
                app(PermissaoGrupoService::class)->atribuirGrupo(
                    (int) $usuario->id_usuario,
                    $idEmpresaSessao,
                    $idGrupoSelecionado
                );
            }

            if ($idPerfilGlobalSelecionado) {
                app(PermissaoGrupoService::class)->atribuirPerfilGlobal(
                    (int) $usuario->id_usuario,
                    $idEmpresaSessao,
                    $idPerfilGlobalSelecionado
                );
            }

            \Log::info('=== USUÁRIO CRIADO COM SUCESSO ===', [
                'id_usuario' => $usuario->id_usuario,
                'login' => $usuario->login,
                'codigo_reset' => $usuario->codigo_reset ? substr($usuario->codigo_reset, 0, 10) . '...' : 'NÃO DEFINIDO',
                'metodo_senha' => $metodo_senha
            ]);

            // Se foi definido para enviar email, enviar agora
            if ($metodo_senha === 'email' && isset($token)) {
                try {
                    $this->enviarEmailCriarSenhaUsuario($usuario, $token);
                    \Log::info('=== EMAIL ENVIADO PARA CRIAR SENHA ===', ['id_usuario' => $usuario->id_usuario]);
                } catch (\Exception $e) {
                    \Log::error('=== ERRO AO ENVIAR EMAIL ===', [
                        'id_usuario' => $usuario->id_usuario,
                        'erro' => $e->getMessage()
                    ]);
                    // Continuar mesmo se o email falhar
                }
            }

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true, 
                    'message' => $metodo_senha === 'email' 
                        ? 'Usuário criado. Email enviado para criar senha.' 
                        : 'Usuário criado com sucesso.',
                    'usuario_id' => $usuario->id_usuario,
                    'id_usuario' => $usuario->id_usuario,
                    'redirect' => route('usuarios.editar', $usuario->id_usuario)
                ]);
            }

            // Redirecionar para a página de edição para permitir upload de foto e anexos
            return redirect()->route('usuarios.editar', $usuario->id_usuario)->with('success', 
                $metodo_senha === 'email' 
                    ? 'Usuário criado com sucesso! Email enviado para criar senha. Agora você pode adicionar foto e anexos.' 
                    : 'Usuário criado com sucesso! Agora você pode adicionar foto e anexos.'
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('=== ERRO DE VALIDAÇÃO AO CRIAR USUÁRIO ===', $e->errors());
            
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro de validação.',
                    'errors' => $e->errors()
                ], 422);
            }

            return redirect()->back()->withErrors($e->errors())->withInput();

        } catch (\Exception $e) {
            \Log::error('=== ERRO AO CRIAR USUÁRIO ===', [
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
     * Enviar email para criar senha do usuário
     */
    private function enviarEmailCriarSenhaUsuario($usuario, $token): void
    {
        try {
            // Usar login como email (pois é ali que está armazenado no banco)
            if (!$usuario->login) {
                \Log::error('=== ERRO: USUÁRIO SEM LOGIN/EMAIL ===', [
                    'id_usuario' => $usuario->id_usuario,
                    'nome' => $usuario->nome
                ]);
                throw new \Exception('Usuário não possui email configurado');
            }

            $linkCriarSenha = route('usuario.validar-codigo-reset', ['token' => $token]);
            
            \Log::info('=== ENVIANDO EMAIL CRIAR SENHA PARA USUÁRIO ===', [
                'id_usuario' => $usuario->id_usuario,
                'email' => $usuario->login
            ]);
            
            \Mail::send('emails.criar-senha-usuario', [
                'nome' => $usuario->nome,
                'link' => $linkCriarSenha
            ], function ($message) use ($usuario) {
                $message->to($usuario->login)
                        ->from('contato@gestornow.com', 'GestorNow')
                        ->subject('Defina sua Senha - GestorNow');
            });
            
            \Log::info('=== EMAIL CRIAR SENHA ENVIADO COM SUCESSO ===', [
                'id_usuario' => $usuario->id_usuario,
                'email' => $usuario->login
            ]);
            
        } catch (\Exception $e) {
            \Log::error('=== ERRO AO ENVIAR EMAIL CRIAR SENHA ===', [
                'id_usuario' => $usuario->id_usuario,
                'email' => $usuario->login ?? 'NÃO DEFINIDO',
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function update($id, Request $request)
    {
        abort_unless($this->podeAdmin('usuarios.editar'), 403);

        try {
            $idEmpresaSessao = (int) (session('id_empresa') ?? Auth::user()->id_empresa ?? 0);
            $usuarioAtual = $this->userService->getUserById((int) $id);

            if (!$usuarioAtual) {
                abort(404);
            }

            if ((int) $usuarioAtual->id_empresa !== $idEmpresaSessao) {
                abort(403); // Segurança: impede atualização de usuário de outra empresa (IDOR).
            }

            $data = $request->only([
                'login',
                'nome',
                'telefone',
                'cpf',
                'is_suporte',
                'endereco',
                'cep',
                'bairro',
                'comissao',
                'observacoes',
                'status',
            ]); // Segurança: limita persistência a campos permitidos para evitar mass assignment.

            // Em empresa da sessao = 1, o switch de suporte precisa persistir 0 quando desmarcado.
            if ($idEmpresaSessao === 1) {
                $data['is_suporte'] = $request->boolean('is_suporte') ? 1 : 0;
            }

            $idGrupoSelecionado = $request->filled('id_grupo') ? (int) $request->input('id_grupo') : null;
            $idPerfilGlobalSelecionado = $request->filled('id_perfil_global') ? (int) $request->input('id_perfil_global') : null;

            unset($data['id_grupo']);
            unset($data['id_perfil_global']);

            // Normalize numeric and formatted fields coming from the UI masks
            // Comissão: convert thousand separators and comma decimal to dot (e.g. "1.234,56" -> "1234.56")
            if (isset($data['comissao'])) {
                $raw = trim($data['comissao']);
                if ($raw === '') {
                    $data['comissao'] = null;
                } else {
                    // Remove dots used as thousand separators, replace comma with dot
                    $normalized = str_replace('.', '', $raw);
                    $normalized = str_replace(',', '.', $normalized);
                    $data['comissao'] = $normalized;
                }
            }

            if (isset($data['cpf'])) {
                $data['cpf'] = preg_replace('/\D+/', '', $data['cpf']);
            }
            if (isset($data['cep'])) {
                $data['cep'] = preg_replace('/\D+/', '', $data['cep']);
            }
            if (isset($data['telefone'])) {
                $data['telefone'] = preg_replace('/\D+/', '', $data['telefone']);
            }

            $this->userService->update($id, $data);

            if ($idGrupoSelecionado) {
                app(PermissaoGrupoService::class)->atribuirGrupo(
                    (int) $id,
                    $idEmpresaSessao,
                    $idGrupoSelecionado
                );
            } else {
                app(PermissaoGrupoService::class)->removerGrupo(
                    (int) $id,
                    $idEmpresaSessao
                );
            }

            if ($idPerfilGlobalSelecionado) {
                app(PermissaoGrupoService::class)->atribuirPerfilGlobal(
                    (int) $id,
                    $idEmpresaSessao,
                    $idPerfilGlobalSelecionado
                );
            } else {
                app(PermissaoGrupoService::class)->removerPerfilGlobal(
                    (int) $id,
                    $idEmpresaSessao
                );
            }
            
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Usuário atualizado com sucesso.'
                ]);
            }

            return redirect()->route('usuarios.index')->with('success', 'Usuário atualizado com sucesso.');
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro de validação.',
                    'errors' => $e->errors()
                ], 422);
            }
            
            return redirect()->back()->withErrors($e->errors())->withInput();
            
        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        abort_unless($this->podeAdmin('usuarios.excluir'), 403);

        try {
            $usuarioAtual = $this->userService->getUserById((int) $id);

            if (!$usuarioAtual) {
                abort(404);
            }

            if ((int) $usuarioAtual->id_empresa !== (int) (session('id_empresa') ?? Auth::user()->id_empresa ?? null)) {
                abort(403); // Segurança: impede exclusão de usuário de outra empresa (IDOR).
            }

            $this->userService->destroy($id);
            
            // If AJAX call, return JSON, otherwise redirect back
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Usuário deletado com sucesso.'
                ]);
            }

            return redirect()->route('usuarios.index')->with('success', 'Usuário deletado com sucesso.');
            
        } catch (\Exception $e) {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Excluir múltiplos usuários
     */
    public function excluirMultiplos(Request $request)
    {
        abort_unless($this->podeAdmin('usuarios.excluir'), 403);

        \Log::info('=== EXCLUIR MÚLTIPLOS USUÁRIOS CHAMADO ===', [
            'request_data' => $request->all(),
            'content_type' => $request->header('Content-Type'),
            'is_json' => $request->isJson(),
            'wants_json' => $request->wantsJson(),
            'is_ajax' => $request->ajax()
        ]);

        try {
            $idEmpresaSessao = (int) (session('id_empresa') ?? Auth::user()->id_empresa ?? 0);

            // Validar que ids foi enviado e é um array
            $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => [
                    'required',
                    'integer',
                    Rule::exists('usuarios', 'id_usuario')->where(function ($query) use ($idEmpresaSessao) {
                        $query->where('id_empresa', $idEmpresaSessao);
                    }),
                ]
            ], [
                'ids.required' => 'Nenhum usuário selecionado.',
                'ids.array' => 'Formato inválido.',
                'ids.min' => 'Selecione pelo menos um usuário.',
                'ids.*.integer' => 'ID inválido.',
                'ids.*.exists' => 'Um ou mais usuários não existem.',
            ]);

            $ids = $request->input('ids');
            \Log::info('IDs validados:', ['ids' => $ids]);

            $deletados = 0;
            $erros = [];

            // Deletar cada usuário individualmente
            foreach ($ids as $id) {
                try {
                    $usuarioAtual = $this->userService->getUserById((int) $id);
                    if (!$usuarioAtual || (int) $usuarioAtual->id_empresa !== $idEmpresaSessao) {
                        throw new \Exception('Usuário fora do escopo da empresa da sessão.'); // Segurança: bloqueia exclusão cruzada entre empresas.
                    }

                    \Log::info("Deletando usuário ID: {$id}");
                    $this->userService->destroy($id);
                    $deletados++;
                    \Log::info("Usuário ID {$id} deletado com sucesso");
                } catch (\Exception $e) {
                    \Log::error("Erro ao deletar usuário ID {$id}", [
                        'erro' => $e->getMessage()
                    ]);
                    $erros[] = "ID {$id}: " . $e->getMessage();
                }
            }

            // Preparar mensagem de resposta
            $mensagem = "{$deletados} usuário(s) deletado(s) com sucesso.";
            if (count($erros) > 0) {
                $mensagem .= " Alguns erros ocorreram: " . implode('; ', $erros);
            }

            \Log::info('Resultado da exclusão múltipla', [
                'deletados' => $deletados,
                'erros_count' => count($erros),
                'mensagem' => $mensagem
            ]);

            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => $deletados > 0,
                    'message' => $mensagem,
                    'deletados' => $deletados,
                    'erros' => $erros
                ]);
            }

            return redirect()->route('usuarios.index')->with(
                $deletados > 0 ? 'success' : 'error', 
                $mensagem
            );
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('Erro de validação ao excluir múltiplos', [
                'errors' => $e->errors()
            ]);

            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro de validação.',
                    'errors' => $e->errors()
                ], 422);
            }
            
            return redirect()->back()->withErrors($e->errors());
            
        } catch (\Exception $e) {
            \Log::error('Erro ao excluir múltiplos usuários', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function list(Request $request)
    {
        $filters = $request->all();
        $filters['id_empresa'] = (int) (session('id_empresa') ?? Auth::user()->id_empresa ?? 0); // Segurança: força escopo da empresa da sessão para bloquear IDOR.
        $users = $this->userService->getUserList($filters);
        return response()->json($users);
    }

    public function count(Request $request)
    {
        $filters = $request->all();
        $filters['id_empresa'] = (int) (session('id_empresa') ?? Auth::user()->id_empresa ?? 0); // Segurança: força escopo da empresa da sessão para bloquear IDOR.
        $count = $this->userService->countUsers($filters);
        return response()->json(['count' => $count]);
    }

    public function userStats(Request $request)
    {
        $filters = [
            'id_empresa' => (int) (session('id_empresa') ?? Auth::user()->id_empresa ?? 0), // Segurança: força escopo da empresa da sessão para bloquear IDOR.
        ];

        $stats = [
            'total' => $this->userService->countUsers($filters),
            'ativos' => $this->userService->countUsers(array_merge($filters, ['status' => 'ativo'])),
            'inativos' => $this->userService->countUsers(array_merge($filters, ['status' => 'inativo'])),
            'bloqueados' => $this->userService->countUsers(array_merge($filters, ['status' => 'bloqueado'])),
        ];

        return response()->json($stats);
    }

    public function search(Request $request)
    {
        $term = $request->input('term', '');
        $idEmpresaSessao = (int) (session('id_empresa') ?? Auth::user()->id_empresa ?? 0);
        $users = $this->userService->searchUsers($term)
            ->where('id_empresa', $idEmpresaSessao)
            ->values(); // Segurança: filtra resultados pela empresa da sessão para bloquear IDOR.
        return response()->json($users);
    }

    /**
     * Alterar senha do usuário
     */
    public function alterarSenha($id, Request $request)
    {
        abort_unless($this->podeAdmin('usuarios.editar'), 403);

        try {
            $usuarioAtual = $this->userService->getUserById((int) $id);
            if (!$usuarioAtual) {
                abort(404);
            }

            if ((int) $usuarioAtual->id_empresa !== (int) (session('id_empresa') ?? Auth::user()->id_empresa ?? null)) {
                abort(403); // Segurança: impede alteração de senha de usuário de outra empresa (IDOR).
            }

            // Validar dados
            $request->validate([
                'senha_atual' => 'required|string',
                'senha' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->letters()->numbers()],
                'senha_confirmation' => 'required',
            ], [
                'senha_atual.required' => 'A senha atual é obrigatória.',
                'senha.required' => 'A nova senha é obrigatória.',
                'senha.confirmed' => 'A confirmação da senha não confere.',
                'senha.min' => 'A senha deve ter pelo menos 8 caracteres.',
                'senha.letters' => 'A senha deve conter pelo menos uma letra.',
                'senha.numbers' => 'A senha deve conter pelo menos um número.',
                'senha_confirmation.required' => 'A confirmação da senha é obrigatória.',
            ]);

            // Chamar o service para alterar a senha
            $this->userService->alterarSenha($id, $request->get('senha_atual'), $request->get('senha'));

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Senha alterada com sucesso.'
                ]);
            }

            return redirect()->back()->with('success', 'Senha alterada com sucesso.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro de validação.',
                    'errors' => $e->errors()
                ], 422);
            }

            return redirect()->back()->withErrors($e->errors());

        } catch (\Exception $e) {
            \Log::error('Erro ao alterar senha do usuário', [
                'id_usuario' => $id,
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
     * Validar codigo_reset e exibir formulário para criar senha
     */
    public function validarCodigoReset($token)
    {
        try {
            \Log::info('=== VALIDANDO CÓDIGO RESET ===', [
                'token' => substr($token, 0, 10) . '...',
                'token_length' => strlen($token)
            ]);

            // Buscar usuário pelo codigo_reset
            $usuario = \App\Domain\Auth\Models\Usuario::where('codigo_reset', $token)->first();
            
            if (!$usuario) {
                \Log::warning('=== CÓDIGO RESET NÃO ENCONTRADO NO BANCO ===', [
                    'token_procurado' => substr($token, 0, 10) . '...',
                    'token_length' => strlen($token)
                ]);
                
                // Debug: listar todos os usuarios com codigo_reset preenchido
                $usuariosComCodigo = \App\Domain\Auth\Models\Usuario::whereNotNull('codigo_reset')->select('id_usuario', 'login', 'codigo_reset')->get();
                \Log::info('=== USUÁRIOS COM CÓDIGO RESET NO BANCO ===', $usuariosComCodigo->toArray());
                
                return redirect()->route('login')
                    ->with('error', 'Link de validação inválido ou expirado.');
            }

            \Log::info('=== USUÁRIO ENCONTRADO COM SUCESSO ===', [
                'id_usuario' => $usuario->id_usuario,
                'login' => $usuario->login,
                'codigo_reset' => substr($usuario->codigo_reset, 0, 10) . '...'
            ]);

            return view('auth.criar-senha-usuario', compact('usuario', 'token'));

        } catch (\Exception $e) {
            \Log::error('=== ERRO AO VALIDAR CÓDIGO RESET ===', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('login')
                ->with('error', 'Erro ao validar link: ' . $e->getMessage());
        }
    }

    /**
     * Criar senha do usuário usando codigo_reset
     */
    public function criarSenhaUsuario(\Illuminate\Http\Request $request)
    {
        try {
            \Log::info('=== CRIANDO SENHA PARA USUÁRIO ===', [
                'token' => substr($request->input('token', ''), 0, 10) . '...'
            ]);

            // Validar dados
            $request->validate([
                'token' => 'required|string',
                'senha' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->letters()->numbers()],
                'senha_confirmation' => 'required',
                'aceita_termos' => 'required|accepted',
            ], [
                'token.required' => 'Token inválido.',
                'senha.required' => 'A senha é obrigatória.',
                'senha.confirmed' => 'A confirmação da senha não confere.',
                'senha.min' => 'A senha deve ter pelo menos 8 caracteres.',
                'senha.letters' => 'A senha deve conter pelo menos uma letra.',
                'senha.numbers' => 'A senha deve conter pelo menos um número.',
                'senha_confirmation.required' => 'A confirmação da senha é obrigatória.',
                'aceita_termos.required' => 'Você deve aceitar os termos de uso.',
                'aceita_termos.accepted' => 'Você deve aceitar os termos de uso.',
            ]);

            $token = $request->input('token');

            // Buscar usuário pelo codigo_reset
            $usuario = \App\Domain\Auth\Models\Usuario::where('codigo_reset', $token)->first();
            
            if (!$usuario) {
                \Log::warning('=== CÓDIGO RESET INVÁLIDO AO CRIAR SENHA ===', [
                    'token' => substr($token, 0, 10) . '...'
                ]);
                return redirect()->route('login')
                    ->with('error', 'Token inválido ou expirado.');
            }

            // Atualizar senha e limpar código_reset
            $usuario->update([
                'senha' => \Hash::make($request->input('senha')),
                'codigo_reset' => null
            ]);

            \Log::info('=== SENHA CRIADA COM SUCESSO ===', [
                'id_usuario' => $usuario->id_usuario,
                'login' => $usuario->login
            ]);

            return redirect()->route('login')
                ->with('success', 'Senha criada com sucesso! Faça login para acessar o sistema.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('=== ERRO DE VALIDAÇÃO AO CRIAR SENHA ===', $e->errors());
            
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();

        } catch (\Exception $e) {
            \Log::error('=== ERRO AO CRIAR SENHA DO USUÁRIO ===', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Erro ao criar senha: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Invalida o cache de fotos dos usuários
     * Útil após upload, atualização ou exclusão de imagens
     */
    public function invalidarCacheFotos(Request $request)
    {
        abort_unless($this->podeAdmin('usuarios.editar'), 403);

        try {
            $userIds = $request->input('user_ids', null);
            
            $this->imageService->invalidateCache($userIds);
            
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Cache de fotos invalidado com sucesso.'
                ]);
            }

            return redirect()->back()->with('success', 'Cache de fotos atualizado.');

        } catch (\Exception $e) {
            Log::error('Erro ao invalidar cache de fotos', [
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

    private function podeAdmin(string $chave): bool
    {
        $usuario = Auth::user();

        return Perm::pode($usuario, 'admin.visualizar') && Perm::pode($usuario, $chave);
    }
}