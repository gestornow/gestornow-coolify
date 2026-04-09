<?php

namespace App\Http\Controllers\Financeiro;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBancoRequest;
use App\Http\Resources\BancoResource;
use App\Models\Banco;
use App\Services\FinanceiroService;
use App\Services\LimiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BancosController extends Controller
{
    /**
     * The financeiro service instance.
     */
    protected FinanceiroService $financeiroService;

    /**
     * Create a new controller instance.
     */
    public function __construct(FinanceiroService $financeiroService)
    {
        $this->financeiroService = $financeiroService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $id_empresa = $this->getIdEmpresaAtual();

        if ($id_empresa) {
            $this->sincronizarBancosComPoliticaBoletos($id_empresa);
        }
        
        $bancos = Banco::where('id_empresa', $id_empresa)
            ->orderBy('nome_banco')
            ->get();

        $controleBoleto = $id_empresa ? $this->obterControleGeraBoleto($id_empresa) : null;

        return view('financeiro.bancos.index', compact('bancos', 'controleBoleto'));
    }

    /**
     * Get all bancos via AJAX.
     */
    public function list(): JsonResponse
    {
        try {
            $id_empresa = $this->getIdEmpresaAtual();

            if (!$id_empresa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa não identificada na sessão.',
                    'bancos' => [],
                ], 401);
            }

            $this->sincronizarBancosComPoliticaBoletos($id_empresa);

            $bancos = Banco::where('id_empresa', $id_empresa)
                ->orderBy('nome_banco')
                ->get();

            $controleBoleto = $this->obterControleGeraBoleto($id_empresa);

            return response()->json([
                'success' => true,
                'bancos' => $bancos,
                'controle_boleto' => $controleBoleto,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar bancos: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao listar bancos.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Store a newly created banco in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $idEmpresa = $this->getIdEmpresaAtual();

            if (!$idEmpresa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa não identificada na sessão.',
                ], 401);
            }

            $this->sincronizarBancosComPoliticaBoletos($idEmpresa);

            $request->validate([
                'nome_banco' => 'required|string|max:255',
                'agencia' => 'nullable|string|max:50',
                'conta' => 'nullable|string|max:50',
                'saldo_inicial' => 'nullable|numeric',
                'observacoes' => 'nullable|string',
                'gera_boleto' => 'nullable|boolean',
            ]);

            $querGeraBoleto = filter_var($request->input('gera_boleto', false), FILTER_VALIDATE_BOOLEAN);

            // Verificar limite de bancos com gera_boleto se estiver marcando como true
            if ($querGeraBoleto) {
                $resultado = LimiteService::podeMarcarBancoGeraBoleto($idEmpresa);
                if (!$resultado['pode']) {
                    return response()->json([
                        'success' => false,
                        'message' => $resultado['mensagem'],
                    ], 422);
                }
            }

            $banco = Banco::create([
                'id_empresa' => $idEmpresa,
                'nome_banco' => $request->nome_banco,
                'agencia' => $request->agencia,
                'conta' => $request->conta,
                'saldo_inicial' => $request->saldo_inicial ?? 0,
                'observacoes' => $request->observacoes,
                'gera_boleto' => $querGeraBoleto,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Banco criado com sucesso!',
                'banco' => $banco,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erro ao criar banco: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar banco. Por favor, tente novamente.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $idEmpresa = $this->getIdEmpresaAtual();

            $banco = Banco::where('id_empresa', $idEmpresa)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'banco' => $banco,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar banco: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Banco não encontrado.',
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $idEmpresa = $this->getIdEmpresaAtual();

            $this->sincronizarBancosComPoliticaBoletos($idEmpresa);

            $request->validate([
                'nome_banco' => 'required|string|max:255',
                'agencia' => 'nullable|string|max:50',
                'conta' => 'nullable|string|max:50',
                'saldo_inicial' => 'nullable|numeric',
                'observacoes' => 'nullable|string',
                'gera_boleto' => 'nullable|boolean',
            ]);

            $querGeraBoleto = filter_var($request->input('gera_boleto', false), FILTER_VALIDATE_BOOLEAN);

            $banco = Banco::where('id_empresa', $idEmpresa)
                ->findOrFail($id);

            // Verificar limite se estiver marcando gera_boleto como true e o banco ainda não tinha essa marcação
            if ($querGeraBoleto && !$banco->gera_boleto) {
                $resultado = LimiteService::podeMarcarBancoGeraBoleto($idEmpresa);
                if (!$resultado['pode']) {
                    return response()->json([
                        'success' => false,
                        'message' => $resultado['mensagem'],
                    ], 422);
                }
            }

            $banco->update([
                'nome_banco' => $request->nome_banco,
                'agencia' => $request->agencia,
                'conta' => $request->conta,
                'saldo_inicial' => $request->saldo_inicial ?? 0,
                'observacoes' => $request->observacoes,
                'gera_boleto' => $querGeraBoleto,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Banco atualizado com sucesso!',
                'banco' => $banco,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar banco: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar banco.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $idEmpresa = $this->getIdEmpresaAtual();

            $banco = Banco::where('id_empresa', $idEmpresa)
                ->findOrFail($id);

            // Verificar se há contas vinculadas
            $contasVinculadas = DB::table('contas_a_pagar')
                ->where('id_bancos', $id)
                ->count();

            if ($contasVinculadas > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é possível excluir este banco pois existem contas vinculadas a ele.',
                ], 400);
            }

            $banco->delete();

            return response()->json([
                'success' => true,
                'message' => 'Banco excluído com sucesso!',
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao excluir banco: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir banco.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Retorna o ID da empresa atual a partir da sessão.
     */
    private function getIdEmpresaAtual(): ?int
    {
        return session('id_empresa');
    }

    /**
     * Retorna status de habilitação da opção "Gera Boleto" para o frontend.
     */
    private function obterControleGeraBoleto(int $idEmpresa): array
    {
        $resultado = LimiteService::podeMarcarBancoGeraBoleto($idEmpresa);

        $limite = $resultado['limite'] ?? null;
        $semAbaBoletos = !$resultado['pode'] && (int) ($limite ?? 0) === 0;

        return [
            'pode_habilitar' => (bool) ($resultado['pode'] ?? false),
            'limite' => $limite,
            'atual' => (int) ($resultado['atual'] ?? 0),
            'restante' => array_key_exists('restante', $resultado) ? $resultado['restante'] : null,
            'mensagem' => (string) ($resultado['mensagem'] ?? ''),
            'sem_aba_boletos' => $semAbaBoletos,
        ];
    }

    /**
     * Se a empresa não tem aba Boletos, remove flags antigas de gera_boleto.
     */
    private function sincronizarBancosComPoliticaBoletos(int $idEmpresa): void
    {
        $controle = $this->obterControleGeraBoleto($idEmpresa);

        if (!empty($controle['sem_aba_boletos'])) {
            Banco::where('id_empresa', $idEmpresa)
                ->where('gera_boleto', 1)
                ->update(['gera_boleto' => 0]);
        }
    }
}
