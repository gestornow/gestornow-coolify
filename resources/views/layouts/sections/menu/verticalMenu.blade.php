@php
$configData = Helper::appClasses();
@endphp

<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">

  <!-- ! Hide app brand if navbar-full -->
  @if(!isset($navbarFull))
  <div class="app-brand demo" style="overflow: visible !important;">
    <a href="{{url('/')}}" class="app-brand-link" style="gap: 0.8rem; overflow: visible !important;">
      <span class="app-brand-logo demo" style="display: flex; align-items: center; overflow: visible !important;">
        <img src="{{ asset('assets/img/gestor_now_transparent2.png') }}" 
             alt="GestorNow Logo" 
             style="max-height: 38px; width: auto; object-fit: contain;"
             onerror="this.onerror=null;this.src='{{ asset('assets/img/gestor_now_transparent.png') }}';" />
      </span>
      <span class="app-brand-text demo menu-text fw-bold" id="gestornow-menu-logo-text">GestorNow</span>
    </a>

    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
      <i class="ti menu-toggle-icon d-none d-xl-block ti-sm align-middle"></i>
      <i class="ti ti-x d-block d-xl-none ti-sm align-middle"></i>
    </a>
  </div>
  @endif


  <div class="menu-inner-shadow"></div>

  <ul class="menu-inner py-1">
    @foreach ($menuData[0]->menu as $menu)

    {{-- adding active and open class if child is active --}}

    {{-- menu headers --}}
    @if (isset($menu->menuHeader))
    <li class="menu-header small text-uppercase">
      <span class="menu-header-text">{{ $menu->menuHeader }}</span>
    </li>

    @else

    {{-- active menu method --}}
    @php
    $activeClass = null;
    $currentRouteName = Route::currentRouteName();

    if ($currentRouteName === $menu->slug) {
    $activeClass = 'active';
    }
    elseif (isset($menu->submenu)) {
    if (gettype($menu->slug) === 'array') {
    foreach($menu->slug as $slug){
    if (str_contains($currentRouteName,$slug) and strpos($currentRouteName,$slug) === 0) {
    $activeClass = 'active open';
    }
    }
    }
    else{
    if (str_contains($currentRouteName,$menu->slug) and strpos($currentRouteName,$menu->slug) === 0) {
    $activeClass = 'active open';
    }
    }

    }
    @endphp

    {{-- main menu --}}
    <li class="menu-item {{$activeClass}}">
      <a href="{{ isset($menu->url) ? url($menu->url) : 'javascript:void(0);' }}" class="{{ isset($menu->submenu) ? 'menu-link menu-toggle' : 'menu-link' }}" @if (isset($menu->target) and !empty($menu->target)) target="_blank" @endif>
        @isset($menu->icon)
        <i class="{{ $menu->icon }}"></i>
        @endisset
        <div>{{ isset($menu->name) ? __($menu->name) : '' }}</div>
        @isset($menu->badge)
        <div class="badge bg-label-{{ $menu->badge[0] }} rounded-pill ms-auto">{{ $menu->badge[1] }}</div>

        @endisset
      </a>

      {{-- submenu --}}
      @isset($menu->submenu)
      @include('layouts.sections.menu.submenu',['menu' => $menu->submenu])
      @endisset
    </li>
    @endif
    @endforeach
  </ul>

</aside>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const menuLogoText = document.getElementById('gestornow-menu-logo-text');
  const menuLogoImg = document.querySelector('.app-brand-logo img');
  const fallbackLogo = '{{ asset('assets/img/gestor_now_transparent.png') }}';
  const primaryLogo = '{{ asset('assets/img/gestor_now_transparent2.png') }}';
  
  function updateMenuLogo() {
    const html = document.documentElement;
    if (html.classList.contains('dark-style')) {
      // Tema escuro
      if (menuLogoText) menuLogoText.style.color = '#fff';
      if (menuLogoImg) {
        menuLogoImg.onerror = function() {
          this.onerror = null;
          this.src = fallbackLogo;
        };
        menuLogoImg.src = primaryLogo;
      }
    } else {
      // Tema claro
      if (menuLogoText) menuLogoText.style.color = '';
      if (menuLogoImg) {
        menuLogoImg.onerror = function() {
          this.onerror = null;
          this.src = fallbackLogo;
        };
        menuLogoImg.src = primaryLogo;
      }
    }
  }
  
  // Aplicar configurações iniciais
  updateMenuLogo();
  
  // Observar mudanças na classe do html
  const observer = new MutationObserver(updateMenuLogo);
  observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});
</script>
