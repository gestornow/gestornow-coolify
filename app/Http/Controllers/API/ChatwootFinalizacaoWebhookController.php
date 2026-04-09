<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatwootFinalizacaoWebhookController extends Controller
{
    /**
     * Conversa resolvida → deleta sessão do bot pra liberar próximo contato.
     */
    public function finalizacao(Request $request): JsonResponse
    {
        $payload = $this->resolverPayload($request);

        $status = strtolower((string) (
            data_get($payload, 'conversation.status')
            ?? data_get($payload, 'status')
            ?? ''
        ));

        if (!$this->isResolvedStatus($status)) {
            return response()->json([
                'success' => true,
                'skipped' => true,
                'message' => 'Status recebido nao e resolvido.',
            ]);
        }

        [$baseUrl, $instance, $apiKey, $erro] = $this->resolverConfigEvolution();
        if ($erro !== null) {
            return $erro;
        }

        $ownerJid = $this->resolverOwnerJid($baseUrl, $apiKey, $instance);
        $remoteJid = $this->resolverRemoteJid($payload, $ownerJid);

        if ($remoteJid === null) {
            Log::warning('Webhook finalizacao: telefone nao identificado.', ['payload' => $payload]);
            return response()->json([
                'success' => false,
                'message' => 'Nao foi possivel identificar o telefone do cliente.',
            ], 422);
        }

        // Remove da lista de ignorados → bot pode criar sessão nova
        $this->removerIgnoreJid($remoteJid);

        // Deletar sessão → próximo "oi" cria sessão nova normalmente
        $this->alterarStatusSessao($baseUrl, $apiKey, $instance, $remoteJid, 'delete');

        Log::info('Finalizacao executada.', ['remote_jid' => $remoteJid]);

        return response()->json([
            'success' => true,
            'message' => 'Sessao encerrada.',
            'remote_jid' => $remoteJid,
        ]);
    }

    /**
     * Humano assumiu → pausa sessão do bot pra impedir resposta.
     * Com sessão pausada, keywords não criam nova sessão.
     * Labels no Chatwoot garantem proteção extra (Regra 01 não dispara).
     */
    public function bloqueioHumano(Request $request): JsonResponse
    {
        $payload = $this->resolverPayload($request);

        [$baseUrl, $instance, $apiKey, $erro] = $this->resolverConfigEvolution();
        if ($erro !== null) {
            return $erro;
        }

        $ownerJid = $this->resolverOwnerJid($baseUrl, $apiKey, $instance);
        $remoteJid = $this->resolverRemoteJid($payload, $ownerJid);

        if ($remoteJid === null) {
            Log::warning('Bloqueio humano: telefone nao identificado.', ['payload' => $payload]);
            return response()->json([
                'success' => false,
                'message' => 'Nao foi possivel identificar o telefone do cliente.',
            ], 422);
        }

        // Adiciona à lista de ignorados → impede criação de sessão nova
        $this->adicionarIgnoreJid($remoteJid);

        // Pausar sessão existente (se houver)
        $this->alterarStatusSessao($baseUrl, $apiKey, $instance, $remoteJid, 'paused');

        Log::info('Bloqueio humano executado.', ['remote_jid' => $remoteJid]);

        return response()->json([
            'success' => true,
            'message' => 'Bot pausado para este contato.',
            'remote_jid' => $remoteJid,
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function resolverPayload(Request $request): array
    {
        $payload = $request->json()->all();
        if (!is_array($payload) || $payload === []) {
            $payload = $request->all();
        }
        return is_array($payload) ? $payload : [];
    }

    private function resolverConfigEvolution(): array
    {
        $baseUrl = rtrim((string) config('services.evolution.base_url', ''), '/');
        $instance = trim((string) config('services.evolution.typebot_instance', ''));
        $apiKey = trim((string) config('services.evolution.api_key', ''));

        if ($baseUrl === '' || $instance === '' || $apiKey === '') {
            Log::error('Configuracao Evolution incompleta.');
            return [$baseUrl, $instance, $apiKey, response()->json([
                'success' => false,
                'message' => 'Configuracao do Evolution incompleta no servidor.',
            ], 500)];
        }

        return [$baseUrl, $instance, $apiKey, null];
    }

    private function isResolvedStatus(string $status): bool
    {
        return in_array($status, ['resolved', 'resolvida', 'resolvidas'], true);
    }

    private function alterarStatusSessao(string $baseUrl, string $apiKey, string $instance, string $remoteJid, string $status): void
    {
        $url = $baseUrl . '/typebot/changeStatus/' . rawurlencode($instance);

        try {
            $response = Http::timeout(20)
                ->withHeaders(['apikey' => $apiKey])
                ->asJson()
                ->post($url, [
                    'remoteJid' => $remoteJid,
                    'status' => $status,
                ]);

            Log::debug('changeStatus Typebot.', [
                'status' => $status,
                'remote_jid' => $remoteJid,
                'http_status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Falha ao alterar status sessao Typebot.', [
                'remote_jid' => $remoteJid,
                'status' => $status,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    // ─── ignoreJids (direto no PostgreSQL da Evolution) ─────────────────

    private function getInstanceId(): string
    {
        return trim((string) config('services.evolution.instance_id', ''));
    }

    private function adicionarIgnoreJid(string $remoteJid): void
    {
        $instanceId = $this->getInstanceId();
        if ($instanceId === '') {
            Log::warning('EVOLUTION_INSTANCE_ID nao configurado.');
            return;
        }

        try {
            $tabelas = ['TypebotSetting', 'Typebot'];

            foreach ($tabelas as $tabela) {
                $registro = DB::connection('evolution')
                    ->table($tabela)
                    ->where('instanceId', $instanceId)
                    ->first(['ignoreJids']);

                if ($registro === null) {
                    continue;
                }

                $ignoreJids = json_decode($registro->ignoreJids ?? '[]', true) ?: [];

                if (in_array($remoteJid, $ignoreJids, true)) {
                    continue;
                }

                $ignoreJids[] = $remoteJid;

                DB::connection('evolution')
                    ->table($tabela)
                    ->where('instanceId', $instanceId)
                    ->update(['ignoreJids' => json_encode(array_values($ignoreJids))]);
            }

            Log::debug('ignoreJid adicionado.', ['remote_jid' => $remoteJid]);
        } catch (\Throwable $e) {
            Log::warning('Falha ao adicionar ignoreJid.', [
                'remote_jid' => $remoteJid,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    private function removerIgnoreJid(string $remoteJid): void
    {
        $instanceId = $this->getInstanceId();
        if ($instanceId === '') {
            Log::warning('EVOLUTION_INSTANCE_ID nao configurado.');
            return;
        }

        try {
            $tabelas = ['TypebotSetting', 'Typebot'];

            foreach ($tabelas as $tabela) {
                $registro = DB::connection('evolution')
                    ->table($tabela)
                    ->where('instanceId', $instanceId)
                    ->first(['ignoreJids']);

                if ($registro === null) {
                    continue;
                }

                $ignoreJids = json_decode($registro->ignoreJids ?? '[]', true) ?: [];
                $filtrado = array_values(array_filter($ignoreJids, fn ($jid) => $jid !== $remoteJid));

                if (count($filtrado) === count($ignoreJids)) {
                    continue;
                }

                DB::connection('evolution')
                    ->table($tabela)
                    ->where('instanceId', $instanceId)
                    ->update(['ignoreJids' => json_encode($filtrado)]);
            }

            Log::debug('ignoreJid removido.', ['remote_jid' => $remoteJid]);
        } catch (\Throwable $e) {
            Log::warning('Falha ao remover ignoreJid.', [
                'remote_jid' => $remoteJid,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    // ─── Resolução de JID ───────────────────────────────────────────────

    private function resolverRemoteJid(array $payload, ?string $ownerJid = null): ?string
    {
        $candidatos = [
            data_get($payload, 'conversation.meta.sender.phone_number'),
            data_get($payload, 'meta.sender.phone_number'),
            data_get($payload, 'conversation.contact.phone_number'),
            data_get($payload, 'contact.phone_number'),
            data_get($payload, 'conversation.additional_attributes.phone_number'),
            data_get($payload, 'additional_attributes.phone_number'),
            data_get($payload, 'conversation.last_non_activity_message.sender.phone_number'),
            data_get($payload, 'conversation.last_non_activity_message.source_id'),
            data_get($payload, 'conversation.contact_inbox.source_id'),
            data_get($payload, 'contact_inbox.source_id'),
            data_get($payload, 'conversation.phone_number'),
            data_get($payload, 'phone_number'),
        ];

        $mensagens = data_get($payload, 'conversation.messages', []);
        if (is_array($mensagens)) {
            foreach (array_reverse($mensagens) as $mensagem) {
                if (!is_array($mensagem)) {
                    continue;
                }
                $candidatos[] = data_get($mensagem, 'sender.phone_number');
                $candidatos[] = data_get($mensagem, 'source_id');
            }
        }

        foreach ($candidatos as $candidato) {
            if (!is_scalar($candidato)) {
                continue;
            }

            $remoteJid = $this->normalizarRemoteJid((string) $candidato);
            if ($remoteJid === null) {
                continue;
            }

            if ($ownerJid !== null && $remoteJid === $ownerJid) {
                continue;
            }

            return $remoteJid;
        }

        return null;
    }

    private function resolverOwnerJid(string $baseUrl, string $apiKey, string $instance): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['apikey' => $apiKey])
                ->get($baseUrl . '/instance/fetchInstances');

            if (!$response->successful()) {
                return null;
            }

            $payload = $response->json();
            if (!is_array($payload)) {
                return null;
            }

            $instancias = $this->normalizarPayloadInstancias($payload);

            foreach ($instancias as $instancia) {
                $nome = (string) (
                    data_get($instancia, 'name')
                    ?? data_get($instancia, 'instanceName')
                    ?? ''
                );

                if ($nome === '' || strcasecmp($nome, $instance) !== 0) {
                    continue;
                }

                $ownerJid = data_get($instancia, 'ownerJid')
                    ?? data_get($instancia, 'owner.jid');

                if (!is_string($ownerJid) || trim($ownerJid) === '') {
                    return null;
                }

                return $this->normalizarRemoteJid($ownerJid);
            }
        } catch (\Throwable $e) {
            Log::debug('Nao foi possivel resolver ownerJid da instancia Evolution.', [
                'instance' => $instance,
                'erro' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function normalizarPayloadInstancias(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        $primeiraChave = array_key_first($payload);
        if (is_int($primeiraChave)) {
            return array_values(array_filter($payload, fn ($item) => is_array($item)));
        }

        return [$payload];
    }

    private function normalizarRemoteJid(string $valor): ?string
    {
        $base = trim($valor);
        if ($base === '') {
            return null;
        }

        if (str_contains($base, '@')) {
            $base = explode('@', $base, 2)[0];
        }

        $digitos = preg_replace('/\D+/', '', $base);
        if (!is_string($digitos) || $digitos === '') {
            return null;
        }

        if (!str_starts_with($digitos, '55') && strlen($digitos) >= 10 && strlen($digitos) <= 11) {
            $digitos = '55' . $digitos;
        }

        if (strlen($digitos) < 12) {
            return null;
        }

        return $digitos . '@s.whatsapp.net';
    }
}
