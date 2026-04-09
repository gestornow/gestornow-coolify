<?php

namespace App\ActivityLog;

use App\ActivityLog\Maps\ContasPagarMap;
use App\ActivityLog\Maps\ContasReceberMap;
use App\ActivityLog\Maps\BoletosMap;
use App\ActivityLog\Maps\ClientesMap;
use App\ActivityLog\Maps\EmpresaMap;
use App\ActivityLog\Maps\FluxoCaixaMap;
use App\ActivityLog\Maps\FaturamentoLocacaoMap;
use App\ActivityLog\Maps\LocacaoDespesaMap;
use App\ActivityLog\Maps\LocacaoMap;
use App\ActivityLog\Maps\LocacaoProdutoTerceiroMap;
use App\ActivityLog\Maps\LocacaoProdutosMap;
use App\ActivityLog\Maps\LocacaoRetornoPatrimonioMap;
use App\ActivityLog\Maps\LocacaoServicoMap;
use App\ActivityLog\Maps\LocacaoTrocaProdutoMap;
use App\ActivityLog\Maps\PatrimoniosMap;
use App\ActivityLog\Maps\PdvVendasMap;
use App\ActivityLog\Maps\ProdutosMap;
use App\ActivityLog\Maps\TabelaPrecosMap;
use App\ActivityLog\Maps\UsuariosMap;

class LogMap
{
    private static array $maps = [
        'ContaPagar' => ContasPagarMap::class,
        'ContaReceber' => ContasReceberMap::class,
        'Boleto' => BoletosMap::class,
        'Cliente' => ClientesMap::class,
        'Empresa' => EmpresaMap::class,
        'FluxoCaixa' => FluxoCaixaMap::class,
        'Usuario' => UsuariosMap::class,
        'User' => UsuariosMap::class,
        'Venda' => PdvVendasMap::class,
        'Produto' => ProdutosMap::class,
        'TabelaPreco' => TabelaPrecosMap::class,
        'Patrimonio' => PatrimoniosMap::class,
        'Locacao' => LocacaoMap::class,
        'LocacaoProduto' => LocacaoProdutosMap::class,
        'LocacaoServico' => LocacaoServicoMap::class,
        'LocacaoDespesa' => LocacaoDespesaMap::class,
        'ProdutoTerceirosLocacao' => LocacaoProdutoTerceiroMap::class,
        'LocacaoRetornoPatrimonio' => LocacaoRetornoPatrimonioMap::class,
        'LocacaoTrocaProduto' => LocacaoTrocaProdutoMap::class,
        'FaturamentoLocacao' => FaturamentoLocacaoMap::class,
    ];

    public static function get(string $modelo, string $evento): ?array
    {
        $mapClass = self::resolveMapClass($modelo);

        if (!$mapClass) {
            return null;
        }

        $eventos = $mapClass::eventos();
        $configEvento = $eventos[$evento] ?? null;

        if (!$configEvento) {
            return null;
        }

        return [
            'map' => $mapClass,
            'acao' => (string) ($configEvento['acao'] ?? ''),
            'icone' => (string) ($configEvento['icone'] ?? 'activity'),
            'cor' => (string) ($configEvento['cor'] ?? 'azul'),
            'descricao' => $configEvento['descricao'] ?? null,
            'tags' => is_array($configEvento['tags'] ?? null) ? $configEvento['tags'] : [],
            'entidade_tipo' => $mapClass::entidadeTipo(),
            'tags_padrao' => $mapClass::tags(),
            'label' => $mapClass::label(),
            'valor' => $mapClass::valor(),
            'campos_sensiveis' => $mapClass::camposSensiveis(),
        ];
    }

    public static function registrado(string $modelo): bool
    {
        return self::resolveMapClass($modelo) !== null;
    }

    private static function resolveMapClass(string $modelo): ?string
    {
        $nomeModelo = class_basename($modelo);

        if (isset(self::$maps[$nomeModelo])) {
            return self::$maps[$nomeModelo];
        }

        if ($nomeModelo === 'ContasAPagar' && isset(self::$maps['ContaPagar'])) {
            return self::$maps['ContaPagar'];
        }

        if ($nomeModelo === 'ContasAReceber' && isset(self::$maps['ContaReceber'])) {
            return self::$maps['ContaReceber'];
        }

        return null;
    }
}
