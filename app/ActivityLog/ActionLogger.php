<?php

namespace App\ActivityLog;

use App\ActivityLog\Jobs\GravarRegistroAtividadeJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActionLogger
{
    public static function log(Model $model, string $evento): void
    {
        $map = LogMap::get(class_basename($model), $evento);

        if (!$map) {
            return;
        }

        $descricao = self::resolverDescricao($map, $model);
        $entidadeLabel = self::resolverLabel($map, $model);
        $valor = self::resolverValor($map, $model);
        $tags = self::resolverTags($map);

        self::logDireto(
            model: $model,
            evento: $evento,
            acao: $map['acao'],
            descricao: $descricao,
            entidadeTipo: $map['entidade_tipo'],
            entidadeLabel: $entidadeLabel,
            valor: $valor,
            contexto: ['evento' => $evento],
            antes: null,
            depois: null,
            icone: $map['icone'],
            cor: $map['cor'],
            tags: $tags
        );
    }

    public static function logDireto(
        Model $model,
        string $evento,
        string $acao,
        string $descricao,
        string $entidadeTipo,
        ?string $entidadeLabel = null,
        ?float $valor = null,
        ?array $contexto = null,
        ?array $antes = null,
        ?array $depois = null,
        string $icone = 'activity',
        string $cor = 'azul',
        array $tags = []
    ): void {
        $usuario = Auth::user();

        $idEmpresa = $model->id_empresa
            ?? ($usuario->id_empresa ?? null)
            ?? session('id_empresa');

        if (!$idEmpresa) {
            return;
        }

        $payload = [
            'id_empresa' => (int) $idEmpresa,
            'id_usuario' => $usuario->id_usuario ?? null,
            'nome_responsavel' => $usuario->nome ?? ($usuario->name ?? null),
            'email_responsavel' => $usuario->login ?? ($usuario->email ?? null),
            'acao' => $acao,
            'descricao' => $descricao,
            'entidade_tipo' => $entidadeTipo,
            'entidade_id' => (int) $model->getKey(),
            'entidade_label' => $entidadeLabel,
            'valor' => $valor,
            'contexto' => $contexto,
            'antes' => $antes,
            'depois' => $depois,
            'ip' => self::ipAtual(),
            'origem' => self::origemAtual(),
            'icone' => $icone,
            'cor' => $cor,
            'tags' => $tags,
            'ocorrido_em' => now(),
            'evento' => $evento,
        ];

        try {
            GravarRegistroAtividadeJob::dispatch($payload)->onQueue('logs');
        } catch (Throwable $e) {
            Log::warning('Falha ao despachar job de atividade. Fallback síncrono aplicado.', [
                'acao' => $acao,
                'evento' => $evento,
                'erro' => $e->getMessage(),
            ]);

            self::gravarSincrono($payload);
        }
    }

    private static function gravarSincrono(array $payload): void
    {
        try {
            GravarRegistroAtividadeJob::gravar($payload);
        } catch (Throwable $e) {
            Log::error('Falha no fallback síncrono do log de atividade.', [
                'erro' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }
    }

    private static function origemAtual(): string
    {
        if (app()->runningInConsole()) {
            return 'console';
        }

        try {
            $request = request();
            if ($request && $request->is('api/*')) {
                return 'api';
            }
        } catch (Throwable $e) {
            return 'web';
        }

        return 'web';
    }

    private static function ipAtual(): ?string
    {
        try {
            $request = request();
            return $request ? $request->ip() : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function resolverDescricao(array $map, Model $model): string
    {
        $resolver = $map['descricao'] ?? null;

        if (is_callable($resolver)) {
            return (string) $resolver($model);
        }

        return 'Atividade registrada';
    }

    private static function resolverLabel(array $map, Model $model): ?string
    {
        $resolver = $map['label'] ?? null;

        if (!is_callable($resolver)) {
            return null;
        }

        return (string) $resolver($model);
    }

    private static function resolverValor(array $map, Model $model): ?float
    {
        $resolver = $map['valor'] ?? null;

        if (!is_callable($resolver)) {
            return null;
        }

        $valor = $resolver($model);

        return $valor === null ? null : (float) $valor;
    }

    private static function resolverTags(array $map): array
    {
        $tagsPadrao = is_array($map['tags_padrao'] ?? null) ? $map['tags_padrao'] : [];
        $tagsEvento = is_array($map['tags'] ?? null) ? $map['tags'] : [];

        return array_values(array_unique(array_merge($tagsPadrao, $tagsEvento)));
    }
}
