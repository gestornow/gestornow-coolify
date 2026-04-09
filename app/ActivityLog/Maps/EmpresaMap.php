<?php

namespace App\ActivityLog\Maps;

use App\ActivityLog\Contracts\ActivityMap;
use App\Domain\Auth\Models\Empresa;
use Closure;

class EmpresaMap implements ActivityMap
{
    public static function entidadeTipo(): string
    {
        return 'Empresa';
    }

    public static function tags(): array
    {
        return ['empresa', 'configuracoes', 'billing', 'onboarding'];
    }

    public static function label(): Closure
    {
        return static function ($model): string {
            if (!$model instanceof Empresa) {
                return 'Empresa';
            }

            $nome = self::nomeEmpresa($model);
            $status = self::statusLegivel($model->status ?? null);
            $doc = self::documento($model);

            if ($doc !== '') {
                return "Empresa {$nome} - {$status} - {$doc}";
            }

            return "Empresa {$nome} - {$status}";
        };
    }

    public static function valor(): ?Closure
    {
        return null;
    }

    public static function camposSensiveis(): array
    {
        return [
            'cnpj',
            'cpf',
            'email',
            'telefone',
        ];
    }

    public static function eventos(): array
    {
        return [
            'created' => [
                'acao' => 'empresa.criada',
                'icone' => 'building-2',
                'cor' => 'verde',
                'descricao' => static function (Empresa $empresa): string {
                    $nome = self::nomeEmpresa($empresa);
                    $status = self::statusLegivel($empresa->status ?? null);

                    return "Criou empresa {$nome} com status {$status}";
                },
                'tags' => ['cadastro'],
            ],
            'updated' => [
                'acao' => 'empresa.editada',
                'icone' => 'edit',
                'cor' => 'amarelo',
                'descricao' => static function (Empresa $empresa): string {
                    $nome = self::nomeEmpresa($empresa);
                    return "Editou dados da empresa {$nome}";
                },
                'tags' => ['edicao'],
            ],
            'deleted' => [
                'acao' => 'empresa.excluida',
                'icone' => 'trash-2',
                'cor' => 'vermelho',
                'descricao' => static function (Empresa $empresa): string {
                    $nome = self::nomeEmpresa($empresa);
                    return "Excluiu empresa {$nome}";
                },
                'tags' => ['exclusao'],
            ],
            'status_alterado' => [
                'acao' => 'empresa.status_alterado',
                'icone' => 'refresh-cw',
                'cor' => 'azul',
                'descricao' => static function (Empresa $empresa): string {
                    $nome = self::nomeEmpresa($empresa);
                    $antes = self::statusLegivel($empresa->getOriginal('status'));
                    $depois = self::statusLegivel($empresa->status);

                    return "Alterou status da empresa {$nome} de '{$antes}' para '{$depois}'";
                },
                'tags' => ['status'],
            ],
            'bloqueio' => [
                'acao' => 'empresa.bloqueada',
                'icone' => 'lock',
                'cor' => 'vermelho',
                'descricao' => static function (Empresa $empresa): string {
                    $nome = self::nomeEmpresa($empresa);
                    $motivo = self::motivoConfiguracao($empresa, 'motivo_bloqueio');

                    if ($motivo !== '') {
                        return "Bloqueou empresa {$nome} - Motivo: {$motivo}";
                    }

                    return "Bloqueou empresa {$nome}";
                },
                'tags' => ['status', 'bloqueio'],
            ],
            'desbloqueio' => [
                'acao' => 'empresa.desbloqueada',
                'icone' => 'unlock',
                'cor' => 'verde',
                'descricao' => static function (Empresa $empresa): string {
                    $nome = self::nomeEmpresa($empresa);
                    return "Desbloqueou empresa {$nome}";
                },
                'tags' => ['status', 'desbloqueio'],
            ],
            'cancelamento' => [
                'acao' => 'empresa.cancelada',
                'icone' => 'x-circle',
                'cor' => 'vermelho',
                'descricao' => static function (Empresa $empresa): string {
                    $nome = self::nomeEmpresa($empresa);
                    $motivo = self::motivoConfiguracao($empresa, 'motivo_cancelamento');

                    if ($motivo !== '') {
                        return "Cancelou empresa {$nome} - Motivo: {$motivo}";
                    }

                    return "Cancelou empresa {$nome}";
                },
                'tags' => ['status', 'cancelamento'],
            ],
            'configuracoes_atualizadas' => [
                'acao' => 'empresa.configuracoes_atualizadas',
                'icone' => 'settings',
                'cor' => 'azul-claro',
                'descricao' => static function (Empresa $empresa): string {
                    $nome = self::nomeEmpresa($empresa);
                    return "Atualizou configuracoes da empresa {$nome}";
                },
                'tags' => ['configuracoes'],
            ],
            'onboarding_dados_atualizados' => [
                'acao' => 'empresa.onboarding_dados_atualizados',
                'icone' => 'clipboard-check',
                'cor' => 'azul',
                'descricao' => static function (Empresa $empresa): string {
                    $nome = self::nomeEmpresa($empresa);
                    return "Atualizou dados cadastrais no onboarding da empresa {$nome}";
                },
                'tags' => ['onboarding', 'cadastro'],
            ],
            'plano_vinculado_ou_reativado' => [
                'acao' => 'empresa.plano_vinculado_ou_reativada',
                'icone' => 'badge-check',
                'cor' => 'verde-escuro',
                'descricao' => static function (Empresa $empresa): string {
                    $nome = self::nomeEmpresa($empresa);
                    $status = self::statusLegivel($empresa->status ?? null);

                    return "Vinculou plano e/ou reativou empresa {$nome} - Status atual: {$status}";
                },
                'tags' => ['billing', 'plano', 'status'],
            ],
        ];
    }

    private static function nomeEmpresa(Empresa $empresa): string
    {
        $nome = trim((string) ($empresa->nome_empresa ?? ''));
        if ($nome !== '') {
            return $nome;
        }

        $razao = trim((string) ($empresa->razao_social ?? ''));
        if ($razao !== '') {
            return $razao;
        }

        return 'Empresa nao identificada';
    }

    private static function documento(Empresa $empresa): string
    {
        $cnpj = trim((string) ($empresa->cnpj ?? ''));
        if ($cnpj !== '') {
            return "CNPJ {$cnpj}";
        }

        $cpf = trim((string) ($empresa->cpf ?? ''));
        if ($cpf !== '') {
            return "CPF {$cpf}";
        }

        return '';
    }

    private static function motivoConfiguracao(Empresa $empresa, string $chave): string
    {
        $cfg = $empresa->configuracoes;
        if (is_string($cfg)) {
            $decoded = json_decode($cfg, true);
            $cfg = is_array($decoded) ? $decoded : [];
        }

        $cfg = is_array($cfg) ? $cfg : [];
        return trim((string) ($cfg[$chave] ?? ''));
    }

    private static function statusLegivel($status): string
    {
        $mapa = [
            'ativo' => 'Ativo',
            'inativo' => 'Inativo',
            'bloqueado' => 'Bloqueado',
            'validacao' => 'Em Validacao',
            'teste' => 'Em Teste',
            'teste bloqueado' => 'Teste Bloqueado',
            'cancelado' => 'Cancelado',
        ];

        $chave = trim((string) $status);
        if ($chave === '') {
            return 'Nao informado';
        }

        return $mapa[$chave] ?? $chave;
    }
}
