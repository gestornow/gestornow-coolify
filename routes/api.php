<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\API\Auth\AuthApiController;
use App\Http\Controllers\API\UserApiController;
use App\Http\Controllers\API\BoletoWebhookController;
use App\Http\Controllers\API\AssinaturaWebhookController;
use App\Http\Controllers\API\ChatwootFinalizacaoWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/webhook/github', [WebhookController::class, 'github']);

// ================== WEBHOOKS DE BOLETOS ==================
Route::prefix('webhooks/boletos')->group(function () {
    Route::post('/inter', [BoletoWebhookController::class, 'inter']);
    Route::post('/asaas', [BoletoWebhookController::class, 'asaas']);
    Route::post('/mercado-pago', [BoletoWebhookController::class, 'mercadoPago']);
    Route::post('/paghiper', [BoletoWebhookController::class, 'pagHiper']);
});

Route::prefix('webhooks/assinaturas')->group(function () {
    Route::post('/asaas', [AssinaturaWebhookController::class, 'asaas']);
});

Route::prefix('webhooks/chatwoot')->group(function () {
    Route::post('/finalizacao', [ChatwootFinalizacaoWebhookController::class, 'finalizacao']);
    Route::post('/bloqueio-humano', [ChatwootFinalizacaoWebhookController::class, 'bloqueioHumano']);
});

// ================== AUTH (Flutter) ==================
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthApiController::class, 'login'])->middleware('throttle:auth-login-api');
    Route::post('/pre-registro', [AuthApiController::class, 'preRegistro'])->middleware('throttle:auth-register-api');
    Route::get('/pre-registro/dados', [AuthApiController::class, 'preRegistroDados'])->middleware('throttle:auth-register-api');
    Route::post('/finalizar-registro', [AuthApiController::class, 'finalizarRegistro'])->middleware('throttle:auth-register-api');
    Route::post('/reset/enviar-codigo', [AuthApiController::class, 'iniciarResetSenha'])->middleware('throttle:auth-reset-api');
    Route::post('/reset/validar-codigo', [AuthApiController::class, 'validarCodigoReset'])->middleware('throttle:auth-reset-api');
    Route::post('/reset/atualizar-senha', [AuthApiController::class, 'atualizarSenha'])->middleware('throttle:auth-reset-api');
    Route::post('/logout', [AuthApiController::class, 'logout']);
    Route::middleware('auth:sanctum')->get('me', [AuthApiController::class, 'me']);
});

// ================== USUÁRIOS (Flutter) ==================
Route::prefix('usuarios')->middleware('auth:sanctum')->group(function () {
    // Rotas especiais (devem vir antes das rotas com parâmetros)
    Route::get('/stats', [UserApiController::class, 'stats']);
    Route::get('/search', [UserApiController::class, 'search']);
    
    // CRUD básico
    Route::get('/', [UserApiController::class, 'index']);
    Route::post('/', [UserApiController::class, 'store']);
    Route::get('/{id}', [UserApiController::class, 'show']);
    Route::put('/{id}', [UserApiController::class, 'update']);
    Route::patch('/{id}', [UserApiController::class, 'update']);
    Route::delete('/{id}', [UserApiController::class, 'destroy']);
    
    // Ações de status
    Route::post('/{id}/block', [UserApiController::class, 'block']);
    Route::post('/{id}/unlock', [UserApiController::class, 'unlock']);
    Route::post('/{id}/activate', [UserApiController::class, 'activate']);
});

// ================== LANDING PAGE (Pública) ==================
use App\Http\Controllers\API\LandingPageApiController;

Route::prefix('landing')->group(function () {
    Route::get('/planos', [LandingPageApiController::class, 'planos']);
    Route::get('/modulos', [LandingPageApiController::class, 'modulos']);
    Route::get('/dados', [LandingPageApiController::class, 'dadosLandingPage']);
});

