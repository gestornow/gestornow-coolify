<?php

namespace App\Http\Controllers\auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegistroRequest;
use App\Domain\Auth\Services\RegistroService;
use App\Models\Plano;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RegistroController extends Controller
{
    protected $registroService;

    public function __construct(RegistroService $registroService)
    {
        $this->registroService = $registroService;
        $this->middleware('guest');
    }

    public function showRegistroForm(): View
    {
        return view('auth.registro');
    }

    public function registro(RegistroRequest $request): JsonResponse|RedirectResponse
    {
        try {
            \Log::info('=== INICIANDO PRE-REGISTRO ===', [
                'email' => $request->email,
                'dados' => $request->except('senha', 'senha_confirmation')
            ]);
            
            // Verificar se já existe usuário com esse email
            if ($this->registroService->emailUsuarioExiste($request->email)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Já existe um usuário cadastrado com este email.',
                        'errors' => ['email' => ['Email já está sendo utilizado por outro usuário.']]
                    ], 422);
                }
                return back()->withErrors(['email' => 'Email já está sendo utilizado por outro usuário.'])->withInput();
            }

            // Resolver plano de teste para gravação posterior do id_plano_teste.
            $idPlanoTeste = (int) $request->input('id_plano', 0);

            if ($idPlanoTeste <= 0 && $request->filled('plano')) {
                $planoPorNome = Plano::ativos()
                    ->whereNotIn('nome', ['Plano Gestor', 'Gestor'])
                    ->where('nome', 'LIKE', '%' . $request->input('plano') . '%')
                    ->first();

                $idPlanoTeste = (int) ($planoPorNome?->id_plano ?? 0);
            }

            if ($idPlanoTeste <= 0) {
                $planoMaisCaro = Plano::ativos()
                    ->whereNotIn('nome', ['Plano Gestor', 'Gestor'])
                    ->orderBy('valor', 'desc')
                    ->first();

                $idPlanoTeste = (int) ($planoMaisCaro?->id_plano ?? 0);
            }
            
            // Gerar token único para validação
            $token = \Str::random(60);
            
            // Preparar dados da empresa
            $dadosEmpresa = [
                'razao_social' => $request->razao_social,
                'nome_empresa' => $request->razao_social,
                'cnpj' => $request->cnpj,
                'email' => $request->email,
                'status' => 'validacao',
                'filial' => 'Unica'
            ];
            
            // Preparar dados do usuário
            $dadosUsuario = [
                'nome' => $request->nome,
                'login' => $request->email,
                'status' => 'ativo'
            ];
            
            // Criar empresa e usuário
            $resultado = $this->registroService->registrarEmpresaEUsuario($dadosEmpresa, $dadosUsuario);
            
            \Log::info('=== EMPRESA E USUÁRIO CRIADOS ===', [
                'id_empresa' => $resultado['empresa']->id_empresa,
                'id_usuario' => $resultado['usuario']->id_usuario
            ]);
            
            // Salvar dados temporários no cache para validação
            $dadosTemp = [
                'id_empresa' => $resultado['empresa']->id_empresa,
                'id_usuario' => $resultado['usuario']->id_usuario,
                'nome' => $request->nome,
                'email' => $request->email,
                'id_plano_teste' => $idPlanoTeste > 0 ? $idPlanoTeste : null,
                'token' => $token,
                'expires_at' => now()->addHours(24)
            ];
            
            \Cache::put("pre_registro_{$token}", $dadosTemp, now()->addHours(24));
            
            // Sempre enviar email com link para criar senha
            $this->enviarEmailCriarSenha($request, $token);
            
            \Log::info('=== EMAIL ENVIADO COM SUCESSO ===', ['email' => $request->email]);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email enviado com sucesso! Verifique sua caixa de entrada para criar sua senha.',
                ], 200);
            }
            
            return redirect()->route('email.enviado')
                ->with('email', $request->email)
                ->with('nome', $request->nome);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('=== ERRO DE VALIDAÇÃO ===', $e->errors());
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos.',
                    'errors' => $e->errors()
                ], 422);
            }

            return back()->withErrors($e->errors())->withInput();

        } catch (QueryException $e) {
            $raw = $e->getMessage();
            $field = null;
            if (preg_match("/Field '([^']+)' doesn't have a default value/i", $raw, $m)) {
                $field = $m[1];
            }

            \Log::error('RegistroController.registro: QUERY EXCEPTION', [
                'message' => $e->getMessage(),
                'sql' => method_exists($e, 'getSql') ? $e->getSql() : null,
                'bindings' => method_exists($e, 'getBindings') ? $e->getBindings() : null,
                'field' => $field
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro de banco de dados.',
                    'field' => $field,
                    'detail' => $e->getMessage(),
                ], 500);
            }

            return back()
                ->with('error', 'Erro de banco de dados' . ($field ? ": campo obrigatório '{$field}' sem valor" : ''))
                ->withInput();

        } catch (\Exception $e) {
            \Log::error('=== ERRO GERAL ===', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro: ' . $e->getMessage(),
                ], 500);
            }

            return back()
                ->with('error', 'Erro ao criar conta: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Enviar email para criar senha
     */
    private function enviarEmailCriarSenha($request, $token): void
    {
        try {
            $linkCriarSenha = route('validar.email', ['token' => $token]);
            
            \Log::info('=== PREPARANDO ENVIO DE EMAIL VALIDAÇÃO DE CONTA ===', [
                'email' => $request->email,
                'link' => $linkCriarSenha
            ]);
            
            \Mail::send('emails.validacao-conta', [
                'nome' => $request->nome,
                'razao_social' => $request->razao_social,
                'link' => $linkCriarSenha
            ], function ($message) use ($request) {
                $message->to($request->email)
                        ->from('contato@gestornow.com', 'GestorNow')
                        ->subject('Validação de Conta - GestorNow');
            });
            
            \Log::info('=== EMAIL VALIDAÇÃO DE CONTA ENVIADO COM SUCESSO ===', [
                'email' => $request->email,
                'timestamp' => now()
            ]);
            
        } catch (\Exception $emailError) {
            \Log::error('=== ERRO AO ENVIAR EMAIL CRIAR SENHA ===', [
                'error_message' => $emailError->getMessage(),
                'error_code' => $emailError->getCode(),
                'file' => $emailError->getFile(),
                'line' => $emailError->getLine(),
                'trace' => $emailError->getTraceAsString(),
                'email_destinatario' => $request->email
            ]);
            throw $emailError;
        }
    }

    public function verificarCnpj(string $cnpj): JsonResponse
    {
        try {
            // Implementar integração com API da Receita Federal aqui
            // Por enquanto, retorna dados fictícios
            
            $cnpjLimpo = preg_replace('/\D/', '', $cnpj);
            
            if (strlen($cnpjLimpo) !== 14) {
                return response()->json([
                    'success' => false,
                    'message' => 'CNPJ inválido.'
                ], 400);
            }

            // Simular consulta à API da Receita
            $dadosEmpresa = [
                'razao_social' => 'EMPRESA EXEMPLO LTDA',
                'nome_fantasia' => 'Empresa Exemplo',
                'endereco' => 'RUA EXEMPLO, 123',
                'bairro' => 'CENTRO',
                'cep' => '12345678',
                'uf' => 'SP',
                'telefone' => '1199999999',
                'email' => '',
                'cnae' => '6201501',
                'status' => 'ATIVA'
            ];

            return response()->json([
                'success' => true,
                'data' => $dadosEmpresa
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao consultar CNPJ.'
            ], 500);
        }
    }

    public function verificarCep(string $cep): JsonResponse
    {
        try {
            $cepLimpo = preg_replace('/\D/', '', $cep);
            
            if (strlen($cepLimpo) !== 8) {
                return response()->json([
                    'success' => false,
                    'message' => 'CEP inválido.'
                ], 400);
            }

            // Implementar integração com API de CEP (ViaCEP, por exemplo)
            $url = "https://viacep.com.br/ws/{$cepLimpo}/json/";
            $response = file_get_contents($url);
            $dados = json_decode($response, true);

            if (isset($dados['erro'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'CEP não encontrado.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'endereco' => $dados['logradouro'],
                    'bairro' => $dados['bairro'],
                    'cidade' => $dados['localidade'],
                    'uf' => $dados['uf'],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao consultar CEP.'
            ], 500);
        }
    }

    public function validarEmail($token)
    {
        $dadosTemp = \Cache::get("pre_registro_{$token}");
        
        if (!$dadosTemp) {
            return redirect()->route('login')
                ->with('error', 'Link de validação inválido ou expirado.');
        }
        
        // Buscar dados da empresa e usuário para exibir na tela
        $empresa = \App\Domain\Auth\Models\Empresa::find($dadosTemp['id_empresa']);
        $usuario = \App\Domain\Auth\Models\Usuario::find($dadosTemp['id_usuario']);
        
        if (!$empresa || !$usuario) {
            return redirect()->route('login')
                ->with('error', 'Dados de registro não encontrados.');
        }
        
        $dadosExibicao = [
            'razao_social' => $empresa->razao_social,
            'nome' => $usuario->nome,
            'email' => $usuario->email
        ];
        
        return view('auth.criar-senha', compact('dadosExibicao', 'token'));
    }

    public function criarSenha(\Illuminate\Http\Request $request)
    {
        \Log::info('=== INICIANDO CRIAÇÃO DE SENHA NO REGISTRO ===', [
            'token_informado' => !empty($request->token),
            'aceita_termos' => (bool) $request->aceita_termos,
        ]);

        $request->validate([
            'token' => 'required',
            'senha' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->letters()->numbers()],
            'senha_confirmation' => 'required',
            'aceita_termos' => 'required|accepted',
        ], [
            'senha.required' => 'A senha é obrigatória.',
            'senha.confirmed' => 'A confirmação da senha não confere.',
            'senha.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'senha.letters' => 'A senha deve conter pelo menos uma letra.',
            'senha.numbers' => 'A senha deve conter pelo menos um número.',
            'senha_confirmation.required' => 'A confirmação da senha é obrigatória.',
            'aceita_termos.required' => 'Você deve aceitar os termos de uso.',
            'aceita_termos.accepted' => 'Você deve aceitar os termos de uso.',
        ]);

        $dadosTemp = \Cache::get("pre_registro_{$request->token}");
        
        if (!$dadosTemp) {
            return redirect()->route('login')
                ->with('error', 'Token inválido ou expirado.');
        }

        try {
            $idPlanoTeste = isset($dadosTemp['id_plano_teste']) ? (int) $dadosTemp['id_plano_teste'] : null;

            // Atualizar senha do usuário e status da empresa
            $resultado = $this->registroService->finalizarRegistro(
                $dadosTemp['id_empresa'],
                $dadosTemp['id_usuario'],
                $request->senha,
                $idPlanoTeste > 0 ? $idPlanoTeste : null
            );            
            // Remover dados temporários
            \Cache::forget("pre_registro_{$request->token}");
            
            \Log::info('=== REGISTRO CONCLUÍDO ===', $resultado);

            return redirect()->route('login')
                ->with('success', 'Conta criada com sucesso! Faça login para acessar o sistema.');

        } catch (\Exception $e) {
            \Log::error('=== ERRO AO CRIAR CONTA ===', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return back()
                ->with('error', 'Erro ao criar conta: ' . $e->getMessage())
                ->withInput();
        }
    }
}