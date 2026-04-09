<?php

namespace App\Http\Controllers;

use App\Models\Plano;
use App\Services\TesteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TesteController extends Controller
{
    protected TesteService $testeService;

    public function __construct(TesteService $testeService)
    {
        $this->testeService = $testeService;
    }

    /**
     * Exibir formulário de criação de teste
     */
    public function criar(Request $request): View
    {
        $planoSlug = $request->query('plano');
        $idPlano = $request->query('id');

        // Buscar plano específico ou listar todos
        $planoSelecionado = null;
        if ($idPlano) {
            $planoSelecionado = Plano::where('id_plano', $idPlano)->ativos()->first();
        } elseif ($planoSlug) {
            $planoSelecionado = Plano::where('nome', 'LIKE', "%{$planoSlug}%")
                ->ativos()
                ->first();
        }

        // Listar todos os planos para exibição
        $planos = Plano::ativos()
            ->whereNotIn('nome', ['Plano Gestor', 'Gestor'])
            ->orderBy('valor')
            ->get();

        return view('auth.teste', [
            'planoSelecionado' => $planoSelecionado,
            'planos' => $planos,
            'diasTeste' => TesteService::DIAS_TESTE,
        ]);
    }

    /**
     * Processar criação do teste
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'cpf' => 'nullable|string|max:14',
            'email' => 'required|email|max:255',
            'nome' => 'required|string|max:255',
            'senha' => 'required|string|min:6|confirmed',
            'id_plano' => 'nullable|integer|exists:planos,id_plano',
        ], [
            'razao_social.required' => 'Informe o nome da empresa.',
            'email.required' => 'Informe seu email.',
            'email.email' => 'Email inválido.',
            'nome.required' => 'Informe seu nome.',
            'senha.required' => 'Crie uma senha.',
            'senha.min' => 'A senha deve ter pelo menos 6 caracteres.',
            'senha.confirmed' => 'As senhas não conferem.',
            'id_plano.exists' => 'Plano inválido.',
        ]);

        try {
            // Se não selecionou plano, usa o mais caro
            $idPlano = $request->id_plano;
            if (!$idPlano) {
                $planoMaisCaro = Plano::ativos()
                    ->whereNotIn('nome', ['Plano Gestor', 'Gestor'])
                    ->orderBy('valor', 'desc')
                    ->first();
                
                $idPlano = $planoMaisCaro?->id_plano;
            }

            if (!$idPlano) {
                throw new \Exception('Nenhum plano disponível para teste.');
            }

            $dadosEmpresa = [
                'razao_social' => $request->razao_social,
                'nome_empresa' => $request->razao_social,
                'cnpj' => $request->cnpj,
                'cpf' => $request->cpf,
                'email' => $request->email,
            ];

            $dadosUsuario = [
                'nome' => $request->nome,
                'login' => $request->email,
                'senha' => $request->senha,
            ];

            $resultado = $this->testeService->criarTeste(
                $dadosEmpresa,
                $dadosUsuario,
                $idPlano
            );

            // Auto-login após criar teste
            Auth::guard('empresa')->login($resultado['usuario']);
            
            session([
                'id_usuario' => $resultado['usuario']->id_usuario,
                'id_empresa' => $resultado['empresa']->id_empresa,
                'unique_session_token' => \Str::random(60),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Conta de teste criada com sucesso!',
                    'redirect' => route('dashboard'),
                ]);
            }

            return redirect()->route('dashboard')
                ->with('success', 'Bem-vindo ao GestorNow! Seu período de teste de ' . TesteService::DIAS_TESTE . ' dias começou.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos.',
                    'errors' => $e->errors(),
                ], 422);
            }

            return back()->withErrors($e->errors())->withInput();

        } catch (\Exception $e) {
            \Log::error('Erro ao criar teste', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao criar conta de teste. Tente novamente.',
                ], 500);
            }

            return back()
                ->with('error', 'Erro ao criar conta de teste. Tente novamente.')
                ->withInput();
        }
    }
}
