<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Plano;
use App\Models\Modulo;
use Illuminate\Http\JsonResponse;

class LandingPageApiController extends Controller
{
    /**
     * Retorna os planos ativos para exibição na landing page
     */
    /**
     * Planos internos que não devem aparecer na landing page
     */
    private const PLANOS_INTERNOS = ['Plano Gestor', 'Gestor'];

    public function planos(): JsonResponse
    {
        $planos = Plano::query()
            ->ativos()
            ->whereNotIn('nome', self::PLANOS_INTERNOS)
            ->select([
                'id_plano',
                'nome',
                'descricao',
                'valor',
                'adesao',
                'relatorios',
                'bancos',
                'assinatura_digital',
                'contratos',
                'faturas',
            ])
            ->with(['modulos' => function ($query) {
                $query->select('planos_modulos.id_plano', 'modulos.id_modulo', 'modulos.nome', 'modulos.descricao', 'modulos.icone')
                    ->join('modulos', 'planos_modulos.id_modulo', '=', 'modulos.id_modulo')
                    ->where('modulos.ativo', 1)
                    ->orderBy('modulos.ordem');
            }])
            ->orderBy('valor')
            ->get()
            ->map(function ($plano) {
                return [
                    'id' => $plano->id_plano,
                    'nome' => $plano->nome,
                    'descricao' => $plano->descricao,
                    'valor' => (float) $plano->valor,
                    'valor_formatado' => 'R$ ' . number_format($plano->valor, 2, ',', '.'),
                    'adesao' => (float) $plano->adesao,
                    'adesao_formatada' => $plano->adesao > 0 ? 'R$ ' . number_format($plano->adesao, 2, ',', '.') : 'Grátis',
                    'recursos' => [
                        'relatorios' => $plano->relatorios,
                        'bancos' => $plano->bancos,
                        'assinatura_digital' => $plano->assinatura_digital,
                        'contratos' => $plano->contratos,
                        'faturas' => $plano->faturas,
                    ],
                    'modulos' => $plano->modulos->map(function ($mod) {
                        return [
                            'nome' => $mod->nome,
                            'descricao' => $mod->descricao,
                            'icone' => $mod->icone,
                        ];
                    }),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $planos,
        ]);
    }

    /**
     * Retorna os módulos ativos (principais, sem pai) para exibição na landing page
     */
    public function modulos(): JsonResponse
    {
        $modulos = Modulo::query()
            ->ativos()
            ->whereNull('id_modulo_pai')
            ->select(['id_modulo', 'nome', 'descricao', 'icone', 'categoria'])
            ->orderBy('ordem')
            ->limit(8) // Limitar para landing page
            ->get()
            ->map(function ($modulo) {
                return [
                    'id' => $modulo->id_modulo,
                    'nome' => $modulo->nome,
                    'descricao' => $modulo->descricao,
                    'icone' => $this->mapIcone($modulo->icone),
                    'categoria' => $modulo->categoria,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $modulos,
        ]);
    }

    /**
     * Retorna planos e módulos em uma única requisição
     */
    public function dadosLandingPage(): JsonResponse
    {
        $planosResponse = $this->planos()->getData();
        $modulosResponse = $this->modulos()->getData();

        return response()->json([
            'success' => true,
            'data' => [
                'planos' => $planosResponse->data ?? [],
                'modulos' => $modulosResponse->data ?? [],
            ],
        ]);
    }

    /**
     * Mapeia ícones do BD para ícones LineIcons da landing page
     */
    private function mapIcone(?string $icone): string
    {
        $mapeamento = [
            'bx-home' => 'lni-home',
            'bx-layer' => 'lni-layers',
            'bx-wallet' => 'lni-coin',
            'bx-box' => 'lni-exit-down',
            'bx-chart' => 'lni-stats-up',
            'bx-file' => 'lni-files',
            'bx-user' => 'lni-users',
            'bx-cog' => 'lni-cog',
            'bx-cart' => 'lni-cart',
            'bx-diamond' => 'lni-diamond-alt',
            'bx-money' => 'lni-coin',
            'bx-receipt' => 'lni-files',
            'bx-package' => 'lni-package',
            // Adicione mais conforme necessário
        ];

        if (!$icone) {
            return 'lni-layers';
        }

        // Se já for um ícone lni, retorna direto
        if (str_starts_with($icone, 'lni-')) {
            return $icone;
        }

        return $mapeamento[$icone] ?? 'lni-layers';
    }
}
