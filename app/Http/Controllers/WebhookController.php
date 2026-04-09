<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class WebhookController extends Controller
{
    public function github(Request $request)
    {
        date_default_timezone_set('America/Sao_Paulo');

        $payload = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');
        $event = $request->header('X-GitHub-Event');

        // 🔹 DEBUG: registrar o payload bruto para ver o que chega
        Log::info('Webhook payload raw', ['payload' => $payload]);

        if (!$this->verifySignature($payload, $signature)) {
            Log::warning('GitHub webhook assinatura inválida', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'signature' => $signature
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        

        // Tenta decodificar JSON; se falhar, tenta pegar de input 'payload' (ex: x-www-form-urlencoded)
        $data = json_decode($payload, true);
        if (!$data && $request->has('payload')) {
            $data = json_decode($request->input('payload'), true);
        }

        if (!$data) {
            Log::error('Payload inválido recebido', ['raw_payload' => $payload]);
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        Log::info('Webhook recebido', [
            'event' => $event,
            'repository' => $data['repository']['name'] ?? 'unknown',
            'ref' => $data['ref'] ?? null
        ]);

        if ($event === 'push') {
            $this->handlePushEvent($data);
        }

        return response()->json(['status' => 'success', 'message' => 'Webhook processado']);
    }

    private function verifySignature($payload, $signature)
    {
        $secret = config('services.github.webhook_secret');

        if (!$secret || !$signature) return false;

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    private function handlePushEvent(array $data)
    {
        $branch = str_replace('refs/heads/', '', $data['ref'] ?? '');
        $repository = $data['repository']['name'] ?? 'unknown';
        $pusher = $data['pusher']['name'] ?? 'unknown';
        $commits = count($data['commits'] ?? []);

        Log::info('Push recebido', [
            'repository' => $repository,
            'branch' => $branch,
            'pusher' => $pusher,
            'commits' => $commits,
            'time' => now()->toDateTimeString()
        ]);

        if (!in_array($branch, ['main', 'master', 'dev'])) {
            Log::info("Push ignorado para branch: {$branch}");
            return;
        }

        $this->deployApplication($data);
    }

    private function deployApplication(array $webhookData)
    {
        try {
            Log::info('Iniciando deploy...', [
                'triggered_by' => $webhookData['pusher']['name'] ?? 'unknown',
                'commits' => count($webhookData['commits'] ?? []),
                'time' => now()->toDateTimeString()
            ]);

            $branch = str_replace('refs/heads/', '', $webhookData['ref'] ?? 'main');
            $scriptPath = base_path('deploy-smart.sh');

            if (!file_exists($scriptPath)) {
                throw new \Exception('Script de deploy não encontrado em: ' . $scriptPath);
            }

            Log::info('Usando script de deploy inteligente: ' . basename($scriptPath) . ' para branch: ' . $branch);

            $process = new Process(['bash', $scriptPath, $branch], base_path());
            $process->setEnv(['HOME' => base_path()]);
            $process->setTimeout(300);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error('Script de deploy falhou', [
                    'exit_code' => $process->getExitCode(),
                    'output' => $process->getOutput(),
                    'error_output' => $process->getErrorOutput()
                ]);
                throw new ProcessFailedException($process);
            }

            Log::info('Deploy concluído com sucesso', [
                'script' => basename($scriptPath),
                'output' => $process->getOutput(),
                'time' => now()->toDateTimeString()
            ]);

            $this->notifyDeploymentSuccess($webhookData);

        } catch (\Exception $e) {
            Log::error('Falha no deploy', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'time' => now()->toDateTimeString()
            ]);
            $this->notifyDeploymentFailure($e, $webhookData);
        }
    }

    private function notifyDeploymentSuccess(array $webhookData)
    {
        Log::info('Notificação de deploy: Sucesso');
    }

    private function notifyDeploymentFailure(\Exception $e, array $webhookData)
    {
        Log::error('Notificação de deploy: Falha', [
            'error' => $e->getMessage()
        ]);
    }
}
