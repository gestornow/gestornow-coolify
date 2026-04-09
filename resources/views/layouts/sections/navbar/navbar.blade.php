@php
$containerNav = $containerNav ?? 'container-fluid';
$navbarDetached = ($navbarDetached ?? '');
@endphp

<!-- Navbar -->
@if(isset($navbarDetached) && $navbarDetached == 'navbar-detached')
<nav class="layout-navbar {{$containerNav}} navbar navbar-expand-xl {{$navbarDetached}} align-items-center bg-navbar-theme" id="layout-navbar">
  @endif
  @if(isset($navbarDetached) && $navbarDetached == '')
  <nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
    <div class="{{$containerNav}}">
      @endif

      <!--  Brand demo (display only for navbar-full and hide on below xl) -->
      @if(isset($navbarFull))
      <div class="navbar-brand app-brand demo d-none d-xl-flex py-0 me-4">
        <a href="{{url('/')}}" class="app-brand-link gap-2">
          <span class="app-brand-logo demo">
            @include('_partials.macros',["height"=>20])
          </span>
          <span class="app-brand-text demo menu-text fw-bold">{{config('variables.templateName')}}</span>
        </a>
      </div>
      @endif

      <!-- ! Not required for layout-without-menu -->
      @if(!isset($navbarHideToggle))
      <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0{{ isset($menuHorizontal) ? ' d-xl-none ' : '' }} {{ isset($contentNavbar) ?' d-xl-none ' : '' }}">
        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
          <i class="ti ti-menu-2 ti-sm"></i>
        </a>
      </div>
      @endif

      <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
        @if(!isset($menuHorizontal))
        <!-- Search -->
        <div class="navbar-nav align-items-center">
          <div class="nav-item navbar-search-wrapper mb-0 d-flex align-items-center">
            <a class="nav-item nav-link search-toggler d-flex align-items-center px-0" href="javascript:void(0);">
              <i class="ti ti-search ti-md me-2"></i>
              <span class="d-none d-md-inline-block text-muted">Buscar (Ctrl+/)</span>
            </a>
          </div>
        </div>
        <!-- /Search -->
        @endif
        <ul class="navbar-nav flex-row align-items-center ms-auto">
          {{-- Saudação com nome do usuário ou da filial --}}
          @php
            $idEmpresaAtiva = session('id_empresa') ?: (Auth::check() ? (Auth::user()->id_empresa ?? null) : null);
            $empresaAtual = $idEmpresaAtiva
              ? \App\Domain\Auth\Models\Empresa::withTrashed()->find($idEmpresaAtiva)
              : null;

            $codigoFilial = $empresaAtual->codigo ?? $idEmpresaAtiva;
            $nomeUsuario = Auth::check() ? (Auth::user()->nome ?? Auth::user()->name ?? null) : null;
            $nomeFilial = $empresaAtual->nome_empresa ?? null;
            $nomeExibicao = $nomeUsuario ? trim($nomeUsuario) : trim($nomeFilial ?? 'Usuário');
          @endphp
          @if($nomeExibicao || $codigoFilial)
            <span class="me-2 me-md-3 fw-bold d-none d-sm-inline">
              Olá, {{ $nomeExibicao }} {{ $codigoFilial ? '[' . $codigoFilial . ']' : '' }}
            </span>
            <span class="me-2 fw-bold d-inline d-sm-none" style="font-size: 0.75rem;">
              {{ Str::limit(trim($nomeExibicao . ' ' . ($codigoFilial ? '[' . $codigoFilial . ']' : '')), 22) }}
            </span>
          @endif
          <!-- Language removido -->

          @if(isset($menuHorizontal))
          <!-- Search -->
          <li class="nav-item navbar-search-wrapper me-2 me-xl-0">
            <a class="nav-link search-toggler" href="javascript:void(0);">
              <i class="ti ti-search ti-md"></i>
            </a>
          </li>
          <!-- /Search -->
          @endif

          <!-- Style Switcher -->
          <li class="nav-item me-2 me-xl-0">
            <a class="nav-link style-switcher-toggle hide-arrow" href="javascript:void(0);">
              <i class='ti ti-md'></i>
            </a>
          </li>
          <!--/ Style Switcher -->

          <!-- Quick links  -->
          <li class="nav-item dropdown-shortcuts navbar-dropdown dropdown me-2 me-xl-0">
            <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
              <i class='ti ti-layout-grid-add ti-md'></i>
            </a>
            <div class="dropdown-menu dropdown-menu-end py-0">
              <div class="dropdown-menu-header border-bottom">
                <div class="dropdown-header d-flex align-items-center py-3">
                  <h5 class="text-body mb-0 me-auto">Atalhos</h5>
                </div>
              </div>
              <div class="dropdown-shortcuts-list scrollable-container">
                <div class="row row-bordered overflow-visible g-0">
                  @pode('clientes.visualizar')
                    <div class="dropdown-shortcuts-item col">
                      <span class="dropdown-shortcuts-icon rounded-circle mb-2">
                        <i class="ti ti-users fs-4"></i>
                      </span>
                      <a href="{{ route('clientes.index') }}" class="stretched-link">Clientes</a>
                      <small class="text-muted mb-0">Gerenciar clientes</small>
                    </div>
                  @endpode
                  @pode('produtos.visualizar')
                    <div class="dropdown-shortcuts-item col">
                      <span class="dropdown-shortcuts-icon rounded-circle mb-2">
                        <i class="ti ti-package fs-4"></i>
                      </span>
                      <a href="{{ route('produtos.index') }}" class="stretched-link">Produtos</a>
                      <small class="text-muted mb-0">Gerenciar produtos</small>
                    </div>
                  @endpode
                </div>
                <div class="row row-bordered overflow-visible g-0">
                  @pode('locacoes.visualizar')
                    <div class="dropdown-shortcuts-item col">
                      <span class="dropdown-shortcuts-icon rounded-circle mb-2">
                        <i class="ti ti-file-text fs-4"></i>
                      </span>
                      <a href="{{ route('locacoes.index') }}" class="stretched-link">Locações</a>
                      <small class="text-muted mb-0">Lista de contratos</small>
                    </div>
                  @endpode
                  @pode('expedicao.logistica.visualizar')
                    <div class="dropdown-shortcuts-item col">
                      <span class="dropdown-shortcuts-icon rounded-circle mb-2">
                        <i class="ti ti-truck-delivery fs-4"></i>
                      </span>
                      <a href="{{ route('locacoes.expedicao') }}" class="stretched-link">Expedição</a>
                      <small class="text-muted mb-0">Logística</small>
                    </div>
                  @endpode
                </div>
                <div class="row row-bordered overflow-visible g-0">
                  @pode('financeiro.contas-pagar.visualizar')
                    <div class="dropdown-shortcuts-item col">
                      <span class="dropdown-shortcuts-icon rounded-circle mb-2">
                        <i class="ti ti-arrow-down-left fs-4"></i>
                      </span>
                      <a href="{{ route('financeiro.index') }}" class="stretched-link">Contas a Pagar</a>
                      <small class="text-muted mb-0">Despesas</small>
                    </div>
                  @endpode
                  @pode('financeiro.contas-receber.visualizar')
                    <div class="dropdown-shortcuts-item col">
                      <span class="dropdown-shortcuts-icon rounded-circle mb-2">
                        <i class="ti ti-arrow-up-right fs-4"></i>
                      </span>
                      <a href="{{ route('financeiro.contas-a-receber.index') }}" class="stretched-link">Contas a Receber</a>
                      <small class="text-muted mb-0">Receitas</small>
                    </div>
                  @endpode
                </div>
              </div>
            </div>
          </li>
          <!-- Quick links -->

          <!-- Notification -->
          @include('components.notificacoes')
          <!--/ Notification -->

          <!-- User -->
          @php
            // Buscar foto do usuário logado via serviço de imagens
            $usuarioLogado = Auth::user();
            $fotoPerfilUrl = null;
            $iniciaisUsuario = '??';
            $isPerfilAdmin = false;
            $alertaFinanceiroCor = null;
            $alertaFinanceiroTexto = null;
            
            if ($usuarioLogado) {
              $isPerfilAdmin = strtolower(trim((string) ($usuarioLogado->finalidade ?? ''))) === 'administrador';

                // Tentar obter foto via ImageService
                try {
                    $imageService = app(\App\Domain\User\Services\UserImageService::class);
                    $fotoPerfilUrl = $imageService->getUserPhotoUrl($usuarioLogado->id_usuario);
                } catch (\Exception $e) {
                    // Fallback silencioso
                }
                
                // Gerar iniciais
                $nome = $usuarioLogado->nome ?? $usuarioLogado->name ?? 'Usuario';
                $palavras = explode(' ', trim($nome));
                if (count($palavras) > 1) {
                    $iniciaisUsuario = mb_strtoupper(mb_substr($palavras[0], 0, 1) . mb_substr($palavras[1], 0, 1));
                } else {
                    $iniciaisUsuario = mb_strtoupper(mb_substr($nome, 0, 2));
                }

                if ($isPerfilAdmin) {
                  $idEmpresaSessao = session('id_empresa_selecionada') ?: session('id_empresa');
                  $idEmpresaAlerta = is_numeric($idEmpresaSessao) ? (int) $idEmpresaSessao : 0;

                  if ($idEmpresaAlerta > 0) {
                    $hoje = now()->toDateString();
                    $statusFechados = [
                      \App\Models\AssinaturaPlanoPagamento::STATUS_PAGO,
                      \App\Models\AssinaturaPlanoPagamento::STATUS_CANCELADO,
                    ];

                    $queryFaturasAbertas = \App\Models\AssinaturaPlanoPagamento::query()
                      ->where('id_empresa', $idEmpresaAlerta)
                      ->where('tipo_cobranca', \App\Models\AssinaturaPlanoPagamento::TIPO_MENSALIDADE)
                      ->where(function ($query) use ($statusFechados) {
                        $query
                          ->whereNull('status')
                          ->orWhereNotIn('status', $statusFechados);
                      });

                    $temFaturaAtrasada = (clone $queryFaturasAbertas)
                      ->where(function ($query) use ($hoje) {
                        $query
                          ->whereIn('status', [
                            \App\Models\AssinaturaPlanoPagamento::STATUS_VENCIDO,
                            \App\Models\AssinaturaPlanoPagamento::STATUS_FALHOU,
                          ])
                          ->orWhereDate('data_vencimento', '<', $hoje);
                      })
                      ->exists();

                    if ($temFaturaAtrasada) {
                      $alertaFinanceiroCor = 'danger';
                      $alertaFinanceiroTexto = 'Fatura atrasada';
                    } else {
                      $temFaturaVencendoHoje = (clone $queryFaturasAbertas)
                        ->whereDate('data_vencimento', $hoje)
                        ->exists();

                      if ($temFaturaVencendoHoje) {
                        $alertaFinanceiroCor = 'warning';
                        $alertaFinanceiroTexto = 'Fatura vence hoje';
                      }
                    }
                  }
                }
            }
          @endphp
          <li class="nav-item navbar-dropdown dropdown-user dropdown">
            <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
              <div class="avatar avatar-online">
                @if(!empty($fotoPerfilUrl))
                  <img src="{{ $fotoPerfilUrl }}" alt="Avatar" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                  <span class="avatar-initial rounded-circle bg-label-secondary text-primary fw-semibold" style="display: none; width: 40px; height: 40px; align-items: center; justify-content: center;">{{ $iniciaisUsuario }}</span>
                @else
                  <span class="avatar-initial rounded-circle bg-label-secondary text-primary fw-semibold" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">{{ $iniciaisUsuario }}</span>
                @endif
              </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li>
                <div class="dropdown-item">
                  <div class="d-flex">
                    <div class="flex-shrink-0 me-3">
                      <div class="avatar avatar-online">
                        @if(!empty($fotoPerfilUrl))
                          <img src="{{ $fotoPerfilUrl }}" alt="Avatar" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                          <span class="avatar-initial rounded-circle bg-label-secondary text-primary fw-semibold" style="display: none; width: 40px; height: 40px; align-items: center; justify-content: center;">{{ $iniciaisUsuario }}</span>
                        @else
                          <span class="avatar-initial rounded-circle bg-label-secondary text-primary fw-semibold" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">{{ $iniciaisUsuario }}</span>
                        @endif
                      </div>
                    </div>
                    <div class="flex-grow-1">
                      <span class="fw-semibold d-block">
                        @if (Auth::check())
                        {{ Auth::user()->nome ?? Auth::user()->name }}
                        @else
                        Usuário
                        @endif
                      </span>
                      <small class="text-muted">{{ ucfirst(Auth::user()->finalidade ?? 'Usuário') }}</small>
                    </div>
                  </div>
                </div>
              </li>
              <li>
                <div class="dropdown-divider"></div>
              </li>
              {{-- Editar Perfil --}}
              @if(Auth::check())
              <li>
                <a class="dropdown-item" href="{{ url('usuarios/' . Auth::user()->id_usuario . '/edit') }}">
                  <i class="ti ti-user-edit me-2 ti-sm"></i>
                  <span class="align-middle">Editar Perfil</span>
                </a>
              </li>
              @endif
              {{-- Se usuário for suporte, exibe select de empresas --}}
              @if(Auth::check() && (Auth::user()->is_suporte ?? Auth::user()->isSuporte ?? 0) == 1)
                @php
                  $empresas = \App\Domain\Auth\Models\Empresa::orderBy('nome_empresa', 'asc')->get();
                @endphp
                <li>
                  <div class="dropdown-item-text p-3" style="width: 280px; min-width: 280px; max-width: 280px;">
                    <div class="d-flex flex-column">
                      <label for="select-filial" class="form-label mb-2 fw-semibold">Selecionar Filial</label>
                      <select id="select-filial" class="form-select select2" onchange="trocarFilial(this.value)">
                        <option value="">Escolha uma filial...</option>
                        @foreach($empresas as $empresa)
                          @php
                            $codigoEmpresa = $empresa->codigo ?? $empresa->id_empresa;
                            $nomeEmpresa = $empresa->nome_empresa ?? ('Filial ' . $codigoEmpresa);
                          @endphp
                          <option value="{{ $empresa->id_empresa }}" @if(($idEmpresaAtiva ?? null) == $empresa->id_empresa) selected @endif>[{{ $codigoEmpresa }}] {{ $nomeEmpresa }}</option>
                        @endforeach
                      </select>
                    </div>
                  </div>
                </li>
                <li>
                  <div class="dropdown-divider"></div>
                </li>
                <li>
                  <a class="dropdown-item" href="{{ route('admin.planos.index') }}">
                    <i class="ti ti-layout-kanban me-2 ti-sm"></i>
                    <span class="align-middle">Planos</span>
                  </a>
                </li>
                <li>
                  <a class="dropdown-item" href="{{ route('admin.filiais.index') }}">
                    <i class="ti ti-building me-2 ti-sm"></i>
                    <span class="align-middle">Lista de Filiais</span>
                  </a>
                </li>
              @endif
              <script>
                function trocarFilial(idEmpresa) {
                  const id = Number(idEmpresa);
                  if (!id) return;

                  fetch('/trocar-filial', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                      'Content-Type': 'application/json',
                      'Accept': 'application/json',
                      'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ id_empresa: id })
                  })
                  .then(async (res) => {
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.success) {
                      throw new Error(data.message || 'Nao foi possivel trocar de filial.');
                    }

                    location.reload();
                  })
                  .catch((error) => {
                    console.error('Erro ao trocar filial:', error);
                    alert(error.message || 'Erro ao trocar filial.');
                  });
                }
              </script>
              {{-- API Tokens removido - Jetstream não instalado --}}
              @if($isPerfilAdmin)
              <li>
                <a class="dropdown-item d-flex align-items-center justify-content-between" href="{{ url('/billing/meu-financeiro') }}">
                  <span class="d-flex align-items-center">
                    <i class="ti ti-wallet me-2 ti-sm"></i>
                    <span class="align-middle">Financeiro</span>
                  </span>
                  @if($alertaFinanceiroCor)
                    <i class="ti ti-alert-triangle ti-sm text-{{ $alertaFinanceiroCor }}" title="{{ $alertaFinanceiroTexto }}" aria-label="{{ $alertaFinanceiroTexto }}"></i>
                  @endif
                </a>
              </li>
              @endif
              {{-- Team Features removido - Jetstream não instalado --}}
              <li>
                <div class="dropdown-divider"></div>
              </li>
              @if (Auth::check() && session('session_token'))
              <li>
                <a class="dropdown-item" href="{{ route('logout') }}" onclick="event.preventDefault(); performLogout();">
                  <i class='ti ti-logout me-2'></i>
                  <span class="align-middle">Logout</span>
                </a>
              </li>
              <form method="POST" id="logout-form" action="{{ route('logout') }}">
                @csrf
              </form>
              <script>
                function performLogout() {
                  // Tentar submeter o formulário
                  const form = document.getElementById('logout-form');
                  if (form) {
                    form.submit();
                  } else {
                    // Fallback: fazer requisição direta
                    window.location.href = '{{ route("logout") }}';
                  }
                }
              </script>
              @else
              <li>
                <a class="dropdown-item" href="{{ Route::has('login') ? route('login') : url('auth/login-basic') }}">
                  <i class='ti ti-login me-2'></i>
                  <span class="align-middle">Login</span>
                </a>
              </li>
              @endif
            </ul>
          </li>
          <!--/ User -->
        </ul>
      </div>

      <!-- Search Small Screens -->
      <div class="navbar-search-wrapper search-input-wrapper {{ isset($menuHorizontal) ? $containerNav : '' }} d-none">
        <input type="text" class="form-control navbar-global-search-no-typeahead border-0" id="navbar-global-search-input" placeholder="Buscar..." aria-label="Buscar..." autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true">
        <div class="navbar-global-search-actions">
          <select class="form-select form-select-sm" id="navbar-global-search-type">
            <option value="locacao" selected>Locação</option>
            <option value="cliente">Cliente</option>
            <option value="produto">Produto</option>
          </select>
          <i class="ti ti-x ti-sm search-toggler cursor-pointer"></i>
        </div>
      </div>
      @if(isset($navbarDetached) && $navbarDetached == '')
    </div>
    @endif
  </nav>
  <!-- / Navbar -->

  <style>
    #layout-navbar.navbar-search-active #navbar-collapse {
      display: none !important;
    }

    #layout-navbar .search-input-wrapper {
      display: flex;
      align-items: center;
      gap: .5rem;
      position: relative;
      width: 100%;
    }

    #layout-navbar .search-input-wrapper #navbar-global-search-input {
      flex: 1 1 auto;
      min-width: 0;
      padding-right: 13.5rem;
      height: 100%;
      box-shadow: none;
    }

    #layout-navbar .search-input-wrapper .navbar-global-search-actions {
      position: absolute;
      right: .75rem;
      left: auto;
      top: 50%;
      transform: translateY(-50%);
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      flex: 0 0 auto;
      z-index: 10;
    }

    #layout-navbar .search-input-wrapper .navbar-global-search-actions #navbar-global-search-type {
      width: 155px;
      min-width: 155px;
      height: 2.1rem;
      border-radius: .55rem;
      border-color: var(--bs-border-color);
      background-color: var(--bs-body-bg);
      box-shadow: none;
      position: relative;
      z-index: 2;
      font-weight: 500;
    }

    #layout-navbar .search-input-wrapper .navbar-global-search-actions .search-toggler {
      position: static !important;
      top: auto;
      right: auto;
      margin-left: 0;
      color: #6f6b7d;
      z-index: 3;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 1.9rem;
      height: 1.9rem;
      border-radius: .45rem;
    }

    #layout-navbar .search-input-wrapper .navbar-global-search-actions .search-toggler:hover {
      color: #5d596c;
    }
  </style>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const switcher = document.querySelector('.style-switcher-toggle');
    if (switcher) {
      switcher.addEventListener('click', function(e) {
        
        // Prevenir qualquer propagação que possa interferir
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        const html = document.documentElement;
        let novoTema = 'light';
        
        if (html.classList.contains('light-style')) {
          html.classList.remove('light-style');
          html.classList.add('dark-style');
          novoTema = 'dark';
        } else {
          html.classList.remove('dark-style');
          html.classList.add('light-style');
          novoTema = 'light';
        }

        // Salvar tema no banco de dados
        fetch('/salvar-tema', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json'
          },
          body: JSON.stringify({ tema: novoTema })
        })
        .then(res => {
          if (!res.ok) {
            // Se CSRF token mismatch ou outro erro, tentar recarregar mesmo assim
            if (res.status === 419) {
              console.warn('CSRF token expirado, recarregando página...');
              window.location.reload();
              return;
            }
            throw new Error('Erro HTTP: ' + res.status);
          }
          return res.json();
        })
        .then(data => {
          if (!data) return; // Já tratado acima
          
          if (!data.success) {
            
            // Se não autenticado, pode ser que a sessão foi perdida
            if (data.message === 'Usuário não autenticado') {
              window.location.href = '/login';
            }
          } else {
            // Recarregar a página para aplicar o tema
            window.location.reload();
          }
        })
        .catch(err => {
          console.error('Erro ao salvar o tema:', err);
          // Mesmo com erro, recarregar para aplicar tema visualmente
          window.location.reload();
        });
      }, { capture: true }); // Usar capture para pegar o evento antes de outros listeners
    }

    const navbarElement = document.getElementById('layout-navbar');
    const searchWrapper = document.querySelector('.search-input-wrapper');
    const searchType = document.getElementById('navbar-global-search-type');
    const searchInput = document.getElementById('navbar-global-search-input');
    const searchTogglers = document.querySelectorAll('.search-toggler');

    function removerTypeaheadNavbarGlobal() {
      if (!searchInput || !searchWrapper) return;

      const pai = searchInput.parentElement;
      if (pai && pai.classList && pai.classList.contains('twitter-typeahead')) {
        const wrapperTypeahead = pai;
        searchWrapper.insertBefore(searchInput, searchWrapper.firstChild);
        wrapperTypeahead.remove();
      }
    }

    function sincronizarModoBuscaNavbar() {
      if (!navbarElement || !searchWrapper) return;
      const aberto = !searchWrapper.classList.contains('d-none');
      navbarElement.classList.toggle('navbar-search-active', aberto);
    }

    function placeholderBusca(tipo) {
      if (tipo === 'cliente') {
        return 'Buscar cliente (Razão Social ou Nome Fantasia)';
      }
      if (tipo === 'produto') {
        return 'Buscar produto por código';
      }
      return 'Buscar locação (código ou cliente)';
    }

    function executarBuscaGlobal() {
      if (!searchInput) return;

      const termo = (searchInput.value || '').trim();
      if (!termo) return;

      const tipo = (searchType && searchType.value) ? searchType.value : 'locacao';

      if (tipo === 'cliente') {
        window.location.href = '/clientes?search=' + encodeURIComponent(termo);
        return;
      }

      if (tipo === 'produto') {
        window.location.href = '/produtos?codigo=' + encodeURIComponent(termo);
        return;
      }

      window.location.href = '/locacoes/contratos?aba=todos&busca=' + encodeURIComponent(termo);
    }

    if (searchType && searchInput) {
      searchInput.placeholder = placeholderBusca(searchType.value);

      searchType.addEventListener('change', function () {
        searchInput.placeholder = placeholderBusca(this.value);
      });

      searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          executarBuscaGlobal();
        }
      });
    }

    if (searchTogglers.length) {
      searchTogglers.forEach(function (toggler) {
        toggler.addEventListener('click', function () {
          removerTypeaheadNavbarGlobal();
          setTimeout(sincronizarModoBuscaNavbar, 0);
        });
      });
    }

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        setTimeout(sincronizarModoBuscaNavbar, 0);
      }
    });

    removerTypeaheadNavbarGlobal();
    setTimeout(removerTypeaheadNavbarGlobal, 100);
    sincronizarModoBuscaNavbar();
  });
  </script>
