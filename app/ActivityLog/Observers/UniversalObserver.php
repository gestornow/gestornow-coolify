<?php

namespace App\ActivityLog\Observers;

use App\ActivityLog\ActionLogger;
use App\ActivityLog\LogMap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class UniversalObserver
{
    private const CAMPOS_IGNORADOS_DIFF = [
        'updated_at',
        'deleted_at',
    ];

    private const CAMPOS_SENSIVEIS_GLOBAIS = [
        'senha',
        'remember_token',
        'session_token',
        'codigo_reset',
        'google_calendar_token',
    ];

    public function created(Model $model): void
    {
        $map = LogMap::get(class_basename($model), 'created');

        if (!$map) {
            return;
        }

        $camposSensiveis = $this->camposSensiveis($map);
        $depois = $this->filtrarCampos($this->snapshot($model), $camposSensiveis);

        ActionLogger::logDireto(
            model: $model,
            evento: 'created',
            acao: $map['acao'],
            descricao: $this->resolverDescricao($map, $model),
            entidadeTipo: $map['entidade_tipo'],
            entidadeLabel: $this->resolverLabel($map, $model),
            valor: $this->resolverValor($map, $model),
            contexto: ['evento' => 'created'],
            antes: null,
            depois: $depois,
            icone: $map['icone'],
            cor: $map['cor'],
            tags: $this->resolverTags($map)
        );
    }

    public function updating(Model $model): void
    {
        if (!LogMap::registrado(class_basename($model))) {
            return;
        }

        // During the updating event the model already contains dirty values.
        // We must cache the original persisted values to produce a correct "before" diff.
        Cache::put($this->cacheKey($model), $model->getOriginal(), now()->addMinutes(5));
    }

    public function updated(Model $model): void
    {
        if (Cache::pull($this->silenciarUpdatedKey($model), false)) {
            return;
        }

        $map = LogMap::get(class_basename($model), 'updated');

        if (!$map) {
            return;
        }

        $antesCompleto = Cache::pull($this->cacheKey($model), []);
        $mudancas = $model->getChanges();

        foreach (self::CAMPOS_IGNORADOS_DIFF as $campoIgnorado) {
            unset($mudancas[$campoIgnorado]);
        }

        if (empty($mudancas)) {
            return;
        }

        $antes = [];
        $depois = [];

        foreach (array_keys($mudancas) as $campo) {
            $antes[$campo] = $antesCompleto[$campo] ?? null;
            $depois[$campo] = $model->getAttribute($campo);
        }

        $camposSensiveis = $this->camposSensiveis($map);
        $antesFiltrado = $this->filtrarCampos($antes, $camposSensiveis);
        $depoisFiltrado = $this->filtrarCampos($depois, $camposSensiveis);

        ActionLogger::logDireto(
            model: $model,
            evento: 'updated',
            acao: $map['acao'],
            descricao: $this->resolverDescricao($map, $model),
            entidadeTipo: $map['entidade_tipo'],
            entidadeLabel: $this->resolverLabel($map, $model),
            valor: $this->resolverValor($map, $model),
            contexto: [
                'evento' => 'updated',
                'campos_alterados' => array_keys($depoisFiltrado),
            ],
            antes: $antesFiltrado,
            depois: $depoisFiltrado,
            icone: $map['icone'],
            cor: $map['cor'],
            tags: $this->resolverTags($map)
        );
    }

    public function deleted(Model $model): void
    {
        $map = LogMap::get(class_basename($model), 'deleted');

        if (!$map) {
            return;
        }

        $camposSensiveis = $this->camposSensiveis($map);
        $antes = $this->filtrarCampos($this->snapshot($model), $camposSensiveis);

        ActionLogger::logDireto(
            model: $model,
            evento: 'deleted',
            acao: $map['acao'],
            descricao: $this->resolverDescricao($map, $model),
            entidadeTipo: $map['entidade_tipo'],
            entidadeLabel: $this->resolverLabel($map, $model),
            valor: $this->resolverValor($map, $model),
            contexto: ['evento' => 'deleted'],
            antes: $antes,
            depois: null,
            icone: $map['icone'],
            cor: $map['cor'],
            tags: $this->resolverTags($map)
        );
    }

    private function cacheKey(Model $model): string
    {
        return 'audit_antes_' . $model->getKey();
    }

    private function silenciarUpdatedKey(Model $model): string
    {
        return 'audit_silenciar_updated_' . class_basename($model) . '_' . $model->getKey();
    }

    private function snapshot(Model $model): array
    {
        return $model->getAttributes();
    }

    private function camposSensiveis(array $map): array
    {
        $mapSensiveis = is_array($map['campos_sensiveis'] ?? null) ? $map['campos_sensiveis'] : [];

        return array_values(array_unique(array_merge(self::CAMPOS_SENSIVEIS_GLOBAIS, $mapSensiveis)));
    }

    private function filtrarCampos(array $dados, array $camposSensiveis): array
    {
        foreach ($camposSensiveis as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dados[$campo] = '[OCULTO]';
            }
        }

        return $dados;
    }

    private function resolverDescricao(array $map, Model $model): string
    {
        $resolver = $map['descricao'] ?? null;

        if (is_callable($resolver)) {
            return (string) $resolver($model);
        }

        return 'Atividade registrada';
    }

    private function resolverLabel(array $map, Model $model): ?string
    {
        $resolver = $map['label'] ?? null;

        if (!is_callable($resolver)) {
            return null;
        }

        return (string) $resolver($model);
    }

    private function resolverValor(array $map, Model $model): ?float
    {
        $resolver = $map['valor'] ?? null;

        if (!is_callable($resolver)) {
            return null;
        }

        $valor = $resolver($model);

        return $valor === null ? null : (float) $valor;
    }

    private function resolverTags(array $map): array
    {
        $tagsPadrao = is_array($map['tags_padrao'] ?? null) ? $map['tags_padrao'] : [];
        $tagsEvento = is_array($map['tags'] ?? null) ? $map['tags'] : [];

        return array_values(array_unique(array_merge($tagsPadrao, $tagsEvento)));
    }
}
