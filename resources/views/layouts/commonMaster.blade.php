<!DOCTYPE html>

@php
// Garantir que o tema vem 100% do banco de dados - SEMPRE recarregar do BD
if (Auth::check()) {
    $userId = Auth::id();
    
    // Buscar tema DIRETO do banco, ignorando qualquer cache
    $userTheme = \DB::table('usuarios')
        ->where('id_usuario', $userId)
        ->value('tema');
    
    // Se for null ou vazio no banco, define como 'light'
    if (empty($userTheme) || !in_array($userTheme, ['light', 'dark'])) {
        $userTheme = 'light';
    }
} else {
    $userTheme = 'light'; // Usuário não autenticado sempre usa light
}
$themeClass = $userTheme === 'dark' ? 'dark-style' : 'light-style';
@endphp

<html lang="{{ session()->get('locale') ?? app()->getLocale() }}" class="{{ $themeClass }} {{ $navbarFixed ?? '' }} {{ $menuFixed ?? '' }} {{ $menuCollapsed ?? '' }} {{ $footerFixed ?? '' }} {{ $customizerHidden ?? '' }}" dir="{{ $configData['textDirection'] }}" data-theme="{{ $configData['theme'] }}" data-style="{{ $userTheme }}" data-assets-path="{{ asset('/assets') . '/' }}" data-base-url="{{url('/')}}" data-framework="laravel" data-template="{{ $configData['layout'] . '-menu-' . $configData['theme'] . '-' . $userTheme }}" data-user-theme="{{ $userTheme }}">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>@yield('title') |
    {{ config('variables.templateName') ? config('variables.templateName') : 'TemplateName' }} -
    {{ config('variables.templateSuffix') ? config('variables.templateSuffix') : 'TemplateSuffix' }}
  </title>
  <meta name="description" content="{{ config('variables.templateDescription') ? config('variables.templateDescription') : '' }}" />
  <meta name="keywords" content="{{ config('variables.templateKeyword') ? config('variables.templateKeyword') : '' }}">
  <!-- laravel CRUD token -->
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <!-- Canonical SEO -->
  <link rel="canonical" href="{{ config('variables.productPage') ? config('variables.productPage') : '' }}">
  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />

  

  <!-- Include Styles -->
  @include('layouts/sections/styles')

  <!-- Include Scripts for customizer, helper, analytics, config -->
  @include('layouts/sections/scriptsIncludes')
  
  <script>
    // Forçar tema do banco de dados
    (function() {
      const dbTheme = '{{ $userTheme }}';
      
      // Limpar localStorage do template customizer para usar tema do banco
      for (let i = localStorage.length - 1; i >= 0; i--) {
        const key = localStorage.key(i);
        if (key && key.includes('templateCustomizer') && key.includes('Style')) {
          localStorage.removeItem(key);
        }
      }
      
      const requiredClass = dbTheme === 'dark' ? 'dark-style' : 'light-style';
      const removeClass = dbTheme === 'dark' ? 'light-style' : 'dark-style';
      
      document.documentElement.classList.remove(removeClass);
      
      if (!document.documentElement.classList.contains(requiredClass)) {
        document.documentElement.classList.add(requiredClass);
      }
    })();
    
    // Configuração estável de CSRF para formulários e requisições AJAX
    (function() {
      var token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

      function aplicarTokenNosForms(csrfToken) {
        if (!csrfToken) return;

        document.querySelectorAll('form').forEach(function(form) {
          var method = (form.getAttribute('method') || 'GET').toUpperCase();
          if (!['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
            return;
          }

          var input = form.querySelector('input[name="_token"]');
          if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_token';
            form.appendChild(input);
          }

          input.value = csrfToken;
        });
      }

      function aplicarTokenGlobal(csrfToken) {
        if (!csrfToken) return;

        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
          meta.setAttribute('content', csrfToken);
        }

        aplicarTokenNosForms(csrfToken);

        if (typeof $ !== 'undefined' && $.ajaxSetup) {
          $.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': csrfToken }
          });
        }

        if (typeof window.updateCsrfToken !== 'undefined') {
          window.updateCsrfToken(csrfToken);
        }
      }

      if (!token) {
        token = '';
      }

      aplicarTokenGlobal(token);

      document.addEventListener('submit', function() {
        var tokenAtual = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || token;
        aplicarTokenNosForms(tokenAtual);
      }, true);

      // Sem refresh remoto automático: mantém o token renderizado no carregamento da página.
      // Isso evita troca de token/sessão durante edição de formulários longos.
    })();
  </script>
</head>

<body>

  <!-- Layout Content -->
  @yield('layoutContent')
  <!--/ Layout Content -->

  @yield('page-modals')

  

  <!-- Include Scripts -->
  @include('layouts/sections/scripts')

</body>

</html>