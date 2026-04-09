<?php

namespace App\Http\Controllers\auth;

use App\Http\Controllers\Controller;
use App\Domain\Auth\Models\Usuario;
use App\Domain\Auth\Services\PasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PasswordResetController extends Controller
{
    private PasswordResetService $passwordResetService;

    public function __construct(PasswordResetService $passwordResetService)
    {
        $this->passwordResetService = $passwordResetService;
    }

    /**
     * Exibe o formulário de solicitação de código
     */
    public function showForgotForm()
    {
        $pageConfigs = ['myLayout' => 'blank'];
        return view('content.authentications.auth-forgot-password-cover', ['pageConfigs' => $pageConfigs]);
    }

    /**
     * Envia código de redefinição de senha
     */
    public function sendResetCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ], [
            'email.required' => 'O campo email é obrigatório.',
            'email.email' => 'Digite um email válido.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $resultado = $this->passwordResetService->enviarCodigoRedefinicao($request->email);

        // Salva email na sessão para as próximas etapas, mesmo quando o email não existir,
        // evitando enumeração de contas por mensagens diferentes.
        session(['email' => $request->email]);

        return redirect()
            ->route('reset-code.form')
            ->with([
                'success' => $resultado['message'],
                'nome' => $resultado['data']['nome'] ?? null,
            ]);
    }

    /**
     * Exibe o formulário de inserção do código
     */
    public function showCodeForm()
    {
        \Log::info('=== EXIBINDO FORMULÁRIO DE CÓDIGO ===', [
            'tem_session_email' => !empty(session('email')),
        ]);
        
        if (!session('email')) {
            \Log::error('=== SESSÃO PERDIDA NO SHOW CODE FORM ===');
            return redirect()->route('forgot-password.form')
                ->with('error', 'Sessão expirada. Solicite um novo código.');
        }

        $pageConfigs = ['myLayout' => 'blank'];
        return view('content.authentications.auth-reset-code-cover', ['pageConfigs' => $pageConfigs]);
    }

    /**
     * Verifica o código e exibe formulário de nova senha
     */
    public function verifyCode(Request $request)
    {
        \Log::info('=== CONTROLLER: VERIFICAÇÃO DE CÓDIGO ===', [
            'sessao_email_presente' => !empty(session('email')),
            'codigo_informado' => (string) $request->input('code', '') !== ''
        ]);
        
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6'
        ], [
            'code.required' => 'Digite o código de 6 dígitos.',
            'code.size' => 'O código deve ter exatamente 6 dígitos.'
        ]);

        if ($validator->fails()) {
            \Log::warning('=== VALIDATION FAILED ===', [
                'errors' => $validator->errors()->toArray()
            ]);
            return back()->withErrors($validator);
        }

        $email = session('email');
        if (!$email) {
            \Log::error('=== SESSÃO EXPIRADA ===', [
                'session_email' => $email,
            ]);
            return redirect()->route('forgot-password.form')
                ->with('error', 'Sessão expirada. Solicite um novo código.');
        }

        \Log::info('=== CHAMANDO SERVICE ===', [
            'email' => $email,
            'code' => $request->code
        ]);

        $resultado = $this->passwordResetService->validarCodigo($email, $request->code);

        \Log::info('=== RESULTADO DO SERVICE ===', [
            'resultado' => $resultado
        ]);

        if (!$resultado['success']) {
            \Log::warning('=== CÓDIGO INVÁLIDO ===', [
                'message' => $resultado['message']
            ]);
            return back()->with('error', $resultado['message']);
        }

        \Log::info('=== REDIRECIONANDO PARA NOVA SENHA ===');

        // Salva dados na sessão permanente
        session([
            'verified' => true,
            'reset_token' => $resultado['data']['token_reset']
        ]);

        return redirect()
            ->route('reset-password.form')
            ->with('success', 'Código validado! Defina sua nova senha.');
    }

    /**
     * Exibe o formulário de nova senha
     */
    public function showResetForm()
    {
        \Log::info('=== EXIBINDO FORMULÁRIO DE NOVA SENHA ===', [
            'verified' => session('verified'),
            'tem_email' => !empty(session('email')),
            'tem_reset_token' => !empty(session('reset_token')),
        ]);
        
        if (!session('verified') || !session('email') || !session('reset_token')) {
            \Log::error('=== ACESSO NEGADO - DADOS FALTANDO ===', [
                'verified' => session('verified'),
                'email' => session('email'),
                'reset_token' => session('reset_token')
            ]);
            return redirect()->route('forgot-password.form')
                ->with('error', 'Acesso negado. Verifique seu código primeiro.');
        }

        $pageConfigs = ['myLayout' => 'blank'];
        return view('content.authentications.auth-new-password-cover', ['pageConfigs' => $pageConfigs]);
    }

    /**
     * Atualiza a senha
     */
    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required'
        ], [
            'password.required' => 'Digite sua nova senha.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $email = session('email');
        $resetToken = session('reset_token');

        Log::info('=== TENTATIVA DE ATUALIZAÇÃO DE SENHA ===', [
            'tem_email_sessao' => !empty($email),
            'tem_token_reset_sessao' => !empty($resetToken),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if (!session('verified') || !$email || !$resetToken) {
            Log::warning('=== UPDATE SENHA NEGADO: SESSÃO INVÁLIDA ===', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('forgot-password.form')
                ->with('error', 'Acesso negado.');
        }

        $resultado = $this->passwordResetService->atualizarSenha(
            $email, 
            $request->password, 
            $resetToken
        );

        if (!$resultado['success']) {
            Log::warning('=== UPDATE SENHA FALHOU ===', [
                'email' => $email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'motivo' => $resultado['message'] ?? 'indefinido',
            ]);

            return back()->with('error', $resultado['message']);
        }

        Log::info('=== SENHA ATUALIZADA COM SUCESSO ===', [
            'email' => $email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Limpa a sessão
        session()->forget(['email', 'verified', 'nome', 'reset_token']);

        return redirect()
            ->route('login')
            ->with('success', $resultado['message'] . ' Faça login com sua nova senha.');
    }

    /**
     * Reenvia o código
     */
    public function resendCode(Request $request)
    {
        $email = session('email');
        if (!$email) {
            return response()->json(['error' => 'Sessão expirada.'], 400);
        }

        $resultado = $this->passwordResetService->reenviarCodigo($email);

        return response()->json([
            'success' => $resultado['success'],
            'message' => $resultado['message']
        ], $resultado['success'] ? 200 : 400);
    }
}