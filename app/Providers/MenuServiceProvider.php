<?php

namespace App\Providers;

use App\Facades\Perm;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use App\Models\PlanoContratado;
use App\Models\Modulo;
use App\Models\CategoriaMenu;
use App\Models\Plano;
use App\Models\PlanoModulo;
use App\Models\AssinaturaPlano;
use App\Domain\Auth\Models\Empresa;
use Illuminate\Support\Facades\Schema;

class MenuServiceProvider extends ServiceProvider
{
  /**
   * Register services.
   */
  public function register(): void
  {
    //
  }

  /**
   * Bootstrap services.
   */
  public function boot(): void
  {
    // Compartilhar menu dinâmico com todas as views
    View::composer('*', function ($view) {
      $verticalMenuData = $this->buildDynamicMenu();
      
      // Menu horizontal (pode ser estático ou também dinâmico)
      $horizontalMenuJson = file_get_contents(base_path('resources/menu/horizontalMenu.json'));
      $horizontalMenuData = json_decode($horizontalMenuJson);

      $view->with('menuData', [$verticalMenuData, $horizontalMenuData]);
    });
  }

  /**
   * Construir menu dinâmico baseado no plano ativo da empresa
   */
  private function buildDynamicMenu()
  {
    // Verificar se o usuário está autenticado
    if (!Auth::check()) {
      return (object)['menu' => []];
    }

    $user = Auth::user();
    $idEmpresa = session('id_empresa') ?? $user->id_empresa;

    if (!$idEmpresa) {
      return (object)['menu' => []];
    }

    // Buscar a empresa
    $empresa = Empresa::find($idEmpresa);

    if (!$empresa) {
      return (object)['menu' => []];
    }

    // Empresa bloqueada acessa apenas dashboard/planos, sem menu funcional.
    if (in_array($empresa->status, ['teste bloqueado', 'bloqueado'])) {
      return (object)['menu' => []];
    }

    // Durante adesão/onboarding, mantém menu vazio para forçar o fluxo obrigatório.
    if (Schema::hasTable('assinaturas_planos')) {
      $assinaturaRestrita = AssinaturaPlano::query()
        ->where('id_empresa', $idEmpresa)
        ->whereIn('status', [
          AssinaturaPlano::STATUS_PENDENTE_PAGAMENTO,
          AssinaturaPlano::STATUS_ONBOARDING_DADOS,
          AssinaturaPlano::STATUS_ONBOARDING_CONTRATO,
        ])
        ->latest('id')
        ->first();

      if ($assinaturaRestrita) {
        return (object)['menu' => []];
      }
    }

    // Buscar plano ativo da empresa (mais recente com status 'ativo')
    $planoAtivo = PlanoContratado::where('id_empresa', $idEmpresa)
      ->where('status', 'ativo')
      ->orderBy('created_at', 'desc')
      ->first();

    // Se não tem plano ativo E a empresa está em teste, usar o plano de teste da empresa.
    if (!$planoAtivo && $empresa->status === 'teste') {
      return $this->buildMenuPlanoTeste($empresa);
    }

    if (!$planoAtivo) {
      return (object)['menu' => []];
    }

    // Buscar IDs dos módulos contratados
    $idsModulosContratados = $planoAtivo->modulosContratados()
      ->where('ativo', 1)
      ->pluck('id_modulo')
      ->toArray();

    if (empty($idsModulosContratados)) {
      return (object)['menu' => []];
    }

    return $this->buildMenuFromModulos($idsModulosContratados);
  }

  /**
   * Construir menu baseado no plano mais caro (para empresas em teste sem plano)
   */
  private function buildMenuPlanoMaisCaro()
  {
    // Buscar o plano mais caro ativo
    $planoMaisCaro = Plano::where('ativo', 1)
      ->orderBy('valor', 'desc')
      ->first();

    if (!$planoMaisCaro) {
      return (object)['menu' => []];
    }

    // Buscar IDs dos módulos do plano mais caro
    $idsModulosPlano = PlanoModulo::where('id_plano', $planoMaisCaro->id_plano)
      ->where('ativo', 1)
      ->pluck('id_modulo')
      ->toArray();

    if (empty($idsModulosPlano)) {
      return (object)['menu' => []];
    }

    return $this->buildMenuFromModulos($idsModulosPlano);
  }

  /**
   * Construir menu baseado no id_plano_teste da empresa.
   * Se não houver plano de teste válido, usa fallback para o plano mais caro.
   */
  private function buildMenuPlanoTeste(Empresa $empresa)
  {
    if (!empty($empresa->id_plano_teste)) {
      $planoTeste = Plano::where('id_plano', (int) $empresa->id_plano_teste)
        ->where('ativo', 1)
        ->first();

      if ($planoTeste) {
        $idsModulosPlanoTeste = PlanoModulo::where('id_plano', $planoTeste->id_plano)
          ->where('ativo', 1)
          ->pluck('id_modulo')
          ->toArray();

        if (!empty($idsModulosPlanoTeste)) {
          return $this->buildMenuFromModulos($idsModulosPlanoTeste);
        }
      }
    }

    return $this->buildMenuPlanoMaisCaro();
  }

  /**
   * Construir menu a partir de uma lista de IDs de módulos
   */
  private function buildMenuFromModulos(array $idsModulos)
  {
    // Buscar módulos principais (sem pai) que estão na lista
    $modulosPrincipais = Modulo::whereNull('id_modulo_pai')
      ->whereIn('id_modulo', $idsModulos)
      ->where('ativo', 1)
      ->orderBy('ordem', 'asc')
      ->get();

    // Obter permissões do usuário autenticado
    $user = Auth::user();
    $usuarioPermissoes = [];
    
    if ($user) {
      // Carregar permissões do usuário
      $usuarioPermissoes = $user->permissoes()
        ->pluck('pode_ler', 'id_modulo')
        ->toArray();
    }

    // Filtrar módulos principais baseado em permissões
    $modulosPrincipais = $modulosPrincipais->filter(function ($modulo) use ($usuarioPermissoes, $user) {
      // Usuário de suporte tem acesso a tudo
      if ($user && ($user->is_suporte ?? false)) {
        return true;
      }

      // Usuário com finalidade "administrador" tem acesso a tudo
      if ($user && strtolower($user->finalidade ?? '') === 'administrador') {
        return true;
      }

      // Menu "Admin" só para suporte e administradores (já verificado acima)
      // Se chegou aqui e é Admin, não mostrar
      if (strtolower($modulo->nome ?? '') === 'admin') {
        return false;
      }

      // Se tem permissões definidas, verificar especificamente
      if (!empty($usuarioPermissoes)) {
        $temPermissaoLegada = ($usuarioPermissoes[$modulo->id_modulo] ?? false) == true;
        if (!$temPermissaoLegada) {
          return false;
        }

        return $this->temPermissaoRotaNovoSistema($modulo->rota ?? null, $user);
      }

      // Se não tem permissões legadas definidas, aplicar filtro do novo sistema de grupos.
      return $this->temPermissaoRotaNovoSistema($modulo->rota ?? null, $user);
    });

    // Para cada módulo principal, buscar seus submódulos que estão na lista
    foreach ($modulosPrincipais as $modulo) {
      $modulo->submodulosContratados = Modulo::where('id_modulo_pai', $modulo->id_modulo)
        ->whereIn('id_modulo', $idsModulos)
        ->where('ativo', 1)
        ->orderBy('ordem', 'asc')
        ->get()
        // Filtrar submódulos por permissão também
        ->filter(function ($submodulo) use ($usuarioPermissoes, $user) {
          // Usuário de suporte tem acesso a tudo
          if ($user && ($user->is_suporte ?? false)) {
            return true;
          }

          // Usuário com finalidade "administrador" tem acesso a tudo
          if ($user && strtolower($user->finalidade ?? '') === 'administrador') {
            return true;
          }

          // Se tem permissões definidas, verificar especificamente
          if (!empty($usuarioPermissoes)) {
            // Verificar se tem permissão de leitura para este submódulo
            $temPermissaoLegada = ($usuarioPermissoes[$submodulo->id_modulo] ?? false) == true;
            if (!$temPermissaoLegada) {
              return false;
            }

            return $this->temPermissaoRotaNovoSistema($submodulo->rota ?? null, $user);
          }

          // Se não tem permissões legadas definidas, aplicar filtro do novo sistema de grupos.
          return $this->temPermissaoRotaNovoSistema($submodulo->rota ?? null, $user);
        });
    }

    // Buscar categorias ativas ordenadas
    $categorias = CategoriaMenu::ativas()->ordenadas()->get();

    // Agrupar módulos por categoria
    $modulosPorCategoria = [];
    foreach ($modulosPrincipais as $modulo) {
      $nomeCategoria = $modulo->categoria ?? 'Outros';
      
      if (!isset($modulosPorCategoria[$nomeCategoria])) {
        $modulosPorCategoria[$nomeCategoria] = [];
      }
      
      $modulosPorCategoria[$nomeCategoria][] = $modulo;
    }

    // Construir menu com separadores
    $menu = [];
    
    // Adicionar categorias da tabela primeiro
    foreach ($categorias as $categoria) {
      $nomeCategoria = $categoria->nome;
      
      if (!isset($modulosPorCategoria[$nomeCategoria]) || empty($modulosPorCategoria[$nomeCategoria])) {
        continue; // Pular categoria vazia
      }

      // Adicionar separador de categoria
      $menu[] = (object)[
        'menuHeader' => $nomeCategoria
      ];

      // Adicionar módulos da categoria
      foreach ($modulosPorCategoria[$nomeCategoria] as $modulo) {
        $menuItem = (object)[
          'name' => $modulo->nome,
          'icon' => 'menu-icon tf-icons ' . ($modulo->icone ?? 'ti ti-circle'),
          'slug' => 'modulo-' . $modulo->id_modulo,
        ];

        // Se tem submódulos contratados, criar dropdown
        if ($modulo->submodulosContratados && $modulo->submodulosContratados->isNotEmpty()) {
          $submenu = [];
          foreach ($modulo->submodulosContratados as $submodulo) {
            $submenu[] = (object)[
              'url' => $submodulo->rota ?? '#',
              'name' => $submodulo->nome,
              'slug' => 'submodulo-' . $submodulo->id_modulo,
            ];
          }
          $menuItem->submenu = $submenu;
        } else {
          // Se não tem submódulos, adicionar URL direta
          if ($modulo->rota) {
            $menuItem->url = $modulo->rota;
          }
        }

        $menu[] = $menuItem;
      }
    }

    // Adicionar módulos "Outros" (sem categoria) ao final
    if (isset($modulosPorCategoria['Outros']) && !empty($modulosPorCategoria['Outros'])) {
      foreach ($modulosPorCategoria['Outros'] as $modulo) {
        $menuItem = (object)[
          'name' => $modulo->nome,
          'icon' => 'menu-icon tf-icons ' . ($modulo->icone ?? 'ti ti-circle'),
          'slug' => 'modulo-' . $modulo->id_modulo,
        ];

        // Se tem submódulos contratados, criar dropdown
        if ($modulo->submodulosContratados && $modulo->submodulosContratados->isNotEmpty()) {
          $submenu = [];
          foreach ($modulo->submodulosContratados as $submodulo) {
            $submenu[] = (object)[
              'url' => $submodulo->rota ?? '#',
              'name' => $submodulo->nome,
              'slug' => 'submodulo-' . $submodulo->id_modulo,
            ];
          }
          $menuItem->submenu = $submenu;
        } else {
          // Se não tem submódulos, adicionar URL direta
          if ($modulo->rota) {
            $menuItem->url = $modulo->rota;
          }
        }

        $menu[] = $menuItem;
      }
    }

    return (object)['menu' => $menu];
  }

  private function temPermissaoRotaNovoSistema(?string $rota, $user): bool
  {
    if (!$user || !$rota) {
      return true;
    }

    $rota = ltrim(strtolower($rota), '/');

    if (strpos($rota, 'clientes') === 0) {
      return Perm::pode($user, 'clientes.visualizar');
    }

    if (strpos($rota, 'fornecedores') === 0) {
      return Perm::pode($user, 'fornecedores.visualizar');
    }

    if (strpos($rota, 'produtos-venda') === 0) {
      return Perm::pode($user, 'produtos-venda.gerenciar');
    }

    if (strpos($rota, 'admin/logs') === 0) {
      return Perm::pode($user, 'admin.visualizar') && Perm::pode($user, 'admin.logs.visualizar');
    }

    if (strpos($rota, 'usuarios') === 0) {
      return Perm::pode($user, 'admin.visualizar') && Perm::pode($user, 'usuarios.visualizar');
    }

    if (strpos($rota, 'admin') === 0) {
      return Perm::pode($user, 'admin.visualizar');
    }

    if (strpos($rota, 'configuracoes/grupos-permissoes') === 0) {
      return Perm::pode($user, 'configuracoes.permissoes.visualizar');
    }

    if (strpos($rota, 'configuracoes') === 0) {
      return Perm::pode($user, 'configuracoes.empresa.visualizar');
    }

    if (strpos($rota, 'documentos') === 0 || strpos($rota, 'modelos-contrato') === 0) {
      return Perm::pode($user, 'configuracoes.documentos.visualizar');
    }

    if (strpos($rota, 'pdv/relatorio-vendas') === 0 || strpos($rota, 'pdv/relatorio-vendas-pdf') === 0) {
      return Perm::pode($user, 'pdv.relatorio');
    }

    if (strpos($rota, 'pdv/cancelar') === 0) {
      return Perm::pode($user, 'pdv.cancelar-venda');
    }

    if (strpos($rota, 'pdv') === 0) {
      return Perm::pode($user, 'pdv.acessar');
    }

    if (
      strpos($rota, 'produtos') === 0 ||
      strpos($rota, 'tabela-precos') === 0 ||
      strpos($rota, 'acessorios') === 0 ||
      strpos($rota, 'manutencoes') === 0 ||
      strpos($rota, 'patrimonios') === 0
    ) {
      return Perm::pode($user, 'produtos.visualizar');
    }

    if (strpos($rota, 'locacoes/expedicao') === 0) {
      return Perm::pode($user, 'expedicao.logistica.visualizar');
    }

    if (strpos($rota, 'locacoes') === 0) {
      return Perm::pode($user, 'locacoes.visualizar');
    }

    if (strpos($rota, 'financeiro/') !== 0 && strpos($rota, 'formas-pagamento') !== 0) {
      return true;
    }

    if (strpos($rota, 'financeiro/contas-a-pagar') === 0) {
      return Perm::pode($user, 'financeiro.contas-pagar.visualizar');
    }

    if (strpos($rota, 'financeiro/contas-a-receber') === 0) {
      return Perm::pode($user, 'financeiro.contas-receber.visualizar');
    }

    if (strpos($rota, 'financeiro/fluxo-caixa') === 0) {
      return Perm::pode($user, 'financeiro.fluxo-caixa');
    }

    if (strpos($rota, 'financeiro/boletos') === 0) {
      return Perm::pode($user, 'financeiro.boletos');
    }

    if (strpos($rota, 'financeiro/bancos') === 0) {
      return Perm::pode($user, 'financeiro.bancos');
    }

    if (strpos($rota, 'financeiro/categorias') === 0 || strpos($rota, 'financeiro/categoria/') === 0) {
      return Perm::pode($user, 'financeiro.categorias');
    }

    if (strpos($rota, 'financeiro/faturamento') === 0) {
      return Perm::pode($user, 'financeiro.faturamento');
    }

    if (strpos($rota, 'financeiro/relatorios/') === 0) {
      return Perm::pode($user, 'financeiro.relatorios');
    }

    if (strpos($rota, 'formas-pagamento') === 0) {
      return Perm::pode($user, 'financeiro.formas-pagamento');
    }

    return Perm::pode($user, 'financeiro.visualizar');
  }
}
