<?php

namespace App\ActivityLog\Jobs;

use App\Models\RegistroAtividade;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GravarRegistroAtividadeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public array $payload)
    {
    }

    public function handle(): void
    {
        self::gravar($this->payload);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Falha definitiva ao gravar registro de atividade na fila.', [
            'erro' => $exception->getMessage(),
            'payload' => $this->payload,
        ]);
    }

    public static function gravar(array $payload): void
    {
        $dados = [
            'id_empresa' => $payload['id_empresa'] ?? null,
            'id_usuario' => $payload['id_usuario'] ?? null,
            'nome_responsavel' => $payload['nome_responsavel'] ?? null,
            'email_responsavel' => $payload['email_responsavel'] ?? null,
            'acao' => $payload['acao'] ?? null,
            'descricao' => $payload['descricao'] ?? null,
            'entidade_tipo' => $payload['entidade_tipo'] ?? null,
            'entidade_id' => $payload['entidade_id'] ?? null,
            'entidade_label' => $payload['entidade_label'] ?? null,
            'valor' => $payload['valor'] ?? null,
            'contexto' => $payload['contexto'] ?? null,
            'antes' => $payload['antes'] ?? null,
            'depois' => $payload['depois'] ?? null,
            'ip' => $payload['ip'] ?? null,
            'origem' => $payload['origem'] ?? 'web',
            'icone' => $payload['icone'] ?? 'activity',
            'cor' => $payload['cor'] ?? 'azul',
            'tags' => $payload['tags'] ?? null,
            'ocorrido_em' => $payload['ocorrido_em'] ?? now(),
        ];

        RegistroAtividade::create($dados);
    }
}
