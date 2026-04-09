<!-- BEGIN: Vendor JS-->
<script src="{{ asset(mix('assets/vendor/libs/jquery/jquery.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/libs/popper/popper.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/js/bootstrap.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/libs/node-waves/node-waves.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/libs/hammer/hammer.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/libs/typeahead-js/typeahead.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/libs/select2/select2.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/js/menu.js')) }}"></script>
@yield('vendor-script')
<!-- END: Page Vendor JS-->

<!-- Global AJAX Configuration and CSRF Token Management -->
<script>
$(document).ready(function() {
    // Restaurar botões desabilitados quando a página carrega  
    // (útil quando há erro de validação e a página recarrega)
    $('button[type="submit"]:disabled').each(function() {
        var $btn = $(this);
        // Se o botão contém "Salvando..." ou spinner, restaurar
        if ($btn.html().includes('Salvando') || $btn.html().includes('spinner-border')) {
            $btn.prop('disabled', false);
            var originalHtml = $btn.data('original-html');
            if (originalHtml) {
                $btn.html(originalHtml);
            } else {
                // Tentar remover o spinner e texto "Salvando..."
                $btn.html($btn.html().replace(/<span class="spinner-border[^>]*><\/span>/g, '').replace(/Salvando\.\.\./g, '').trim());
            }
        }
    });
    
    // Configurar CSRF token globalmente
    window.updateCsrfToken = function(newToken) {
        if (newToken) {
            $('meta[name="csrf-token"]').attr('content', newToken);
            $('input[name="_token"]').val(newToken);
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': newToken
                }
            });
        }
    };
    
    // Obter token CSRF atual (da meta tag, sem buscar do servidor)
    window.getCsrfToken = function() {
        return $('meta[name="csrf-token"]').attr('content');
    };
    
    // Manter compatibilidade com código que usa getFreshCsrfToken
    // Agora apenas retorna o token atual, sem buscar do servidor
    window.getFreshCsrfToken = function() {
        return Promise.resolve(window.getCsrfToken());
    };
    
    // Manter compatibilidade com código que usa ajaxPostWithFreshToken
    // Agora faz requisição normal usando o token da página
    window.ajaxPostWithFreshToken = function(options) {
        var token = window.getCsrfToken();
        
        // Garantir que o token está nos dados
        if (options.data) {
            if (typeof options.data === 'object' && !(options.data instanceof FormData)) {
                options.data._token = token;
            }
        }
        
        // Garantir que o token está no header
        if (!options.headers) options.headers = {};
        options.headers['X-CSRF-TOKEN'] = token;
        
        return $.ajax(options);
    };
    
    // Configurar CSRF token inicial
    var csrfToken = $('meta[name="csrf-token"]').attr('content');
    if (csrfToken) {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        });
    }
    
    // Renovação automática do token CSRF a cada 30 minutos para evitar expiração
    // DESABILITADO: estava causando demasiadas requisições em background
    /*
    var isRequestInProgress = false;
    var pendingRequests = 0;
    
    // Rastrear quando há requisições em andamento
    $(document).ajaxStart(function() {
        pendingRequests++;
        isRequestInProgress = true;
    }).ajaxStop(function() {
        pendingRequests--;
        if (pendingRequests <= 0) {
            isRequestInProgress = false;
            pendingRequests = 0;
        }
    });
    
    window.csrfRenewalInterval = setInterval(function() {
        // Não renovar token durante requisições em andamento
        if (isRequestInProgress || pendingRequests > 0) {
            console.debug('Token CSRF: Pulando renovação porque há requisições em andamento');
            return;
        }
        
        $.ajax({
            url: '/auth/renovar-sessao',
            method: 'POST',
            data: { _token: window.getCsrfToken() },
            silent: true, // Flag para não disparar handlers de erro
            timeout: 5000, // Timeout de 5 segundos
            success: function(response) {
                if (response.success && response.csrf_token) {
                    window.updateCsrfToken(response.csrf_token);
                    console.info('Token CSRF renovado automaticamente em background');
                }
            },
            error: function(xhr) {
                // Se receber novo token mesmo com erro, atualizar
                if (xhr.responseJSON && xhr.responseJSON.new_token) {
                    window.updateCsrfToken(xhr.responseJSON.new_token);
                    console.info('Token CSRF renovado via resposta de erro');
                }
            }
        });
    }, 30 * 60 * 1000); // 30 minutos
    */
    
    // Restaurar estado de botões quando a página é carregada (caso tenha ocorrido erro de servidor)
    $(window).on('pageshow', function(event) {
        // Se a página voltou do cache (navegação back/forward), resetar todos os botões
        if (event.originalEvent.persisted || (window.performance && window.performance.navigation.type === 2)) {
            $('button[type="submit"]').each(function() {
                var $btn = $(this);
                if ($btn.prop('disabled')) {
                    var originalHtml = $btn.data('original-html');
                    $btn.prop('disabled', false);
                    if (originalHtml) {
                        $btn.html(originalHtml);
                    }
                }
            });
        }
    });
    
    // Interceptar submissão de formulários para atualizar CSRF token dinamicamente
    $(document).on('submit', 'form:not([data-no-csrf-update])', function(e) {
        var token = window.getCsrfToken();
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        
        // Atualizar campo _token no formulário
        var tokenInput = $(this).find('input[name="_token"]');
        if (tokenInput.length) {
            tokenInput.val(token);
        }
        // Se não tiver input _token, criar um
        if (!tokenInput.length) {
            $(this).prepend('<input type="hidden" name="_token" value="' + token + '">');
        }
        
        // Adicionar indicador visual ao botão de submit (apenas em formulários HTML, não AJAX)
        if ($submitBtn.length && !$form.data('ajax-form')) {
            var originalBtnHtml = $submitBtn.html();
            $submitBtn.data('original-html', originalBtnHtml);
            $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Salvando...');
            
            // Resetar botão após 10 segundos caso algo dê errado
            setTimeout(function() {
                if ($submitBtn.prop('disabled')) {
                    $submitBtn.prop('disabled', false).html(originalBtnHtml);
                }
            }, 10000);
        }
        
        console.log('Formulário submetido com token:', token.substring(0, 10) + '...');
    });
    
    // Handler global para erros AJAX - tratamento simplificado
    // NOTA: Com o novo VerifyCsrfToken, erros 419 só ocorrem para não logados
    $(document).ajaxError(function(event, xhr, settings, error) {
        // Ignorar requisições silenciosas
        if (settings.silent) {
            return;
        }

        // Erro 419 - só acontece se não estiver logado
        if (xhr.status === 419) {
            var redirectUrl = (xhr.responseJSON && xhr.responseJSON.redirect) || '{{ route("login.form") }}';
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'info',
                    title: 'Sessão não encontrada',
                    text: 'Faça login para continuar.',
                    confirmButtonText: 'Fazer Login',
                    allowOutsideClick: false
                }).then(function() {
                    window.location.href = redirectUrl;
                });
            } else {
                alert('Faça login para continuar.');
                window.location.href = redirectUrl;
            }
            return false;
        }
        
        // Erro 401 - Não autenticado (ignorar se veio do keepalive — já trata internamente)
        if (xhr.status === 401) {
            // O keepalive (/auth/status) já lida com 401 internamente
            if (settings.url && settings.url.indexOf('/auth/') !== -1) {
                return;
            }

            var redirectUrl = (xhr.responseJSON && xhr.responseJSON.redirect) || '{{ route("login.form") }}';
            var message = (xhr.responseJSON && xhr.responseJSON.message) || 'Faça login para continuar.';
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'info',
                    title: 'Não autenticado',
                    text: message,
                    confirmButtonText: 'Fazer Login',
                    allowOutsideClick: false
                }).then(function() {
                    window.location.href = redirectUrl;
                });
            } else {
                alert(message);
                window.location.href = redirectUrl;
            }
            return false;
        }
    });

    // ================== Keepalive de Sessão (sem rotação de CSRF) ==================
    // Mantém a sessão ativa sem regenerar token CSRF para não invalidar formulários abertos.
    (function() {
        var publicAuthPaths = [
            '/login',
            '/registro',
            '/recuperar-senha',
            '/esqueci-senha',
            '/codigo-redefinicao',
            '/nova-senha'
        ];
        var currentPath = window.location.pathname || '/';
        var isPublicAuthPage = publicAuthPaths.indexOf(currentPath) !== -1 || !!document.getElementById('formAuthentication');

        if (isPublicAuthPage) {
            return;
        }

        var keepaliveInterval = 4 * 60 * 1000; // 4 minutos
        var inactivityThreshold = 120 * 60 * 1000; // 120 minutos
        var lastActivity = Date.now();
        var failCount = 0;

        function pingSession() {
            $.ajax({
                url: '/auth/status',
                method: 'GET',
                silent: true,
                dataType: 'json',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                timeout: 10000,
                success: function(response) {
                    failCount = 0;
                    // Se o servidor diz que não está autenticado, tentar recuperar a sessão
                    if (response && response.authenticated === false) {
                        console.warn('Keepalive: sessão perdida, tentando recuperar...');
                        $.ajax({
                            url: '/auth/renovar-sessao',
                            method: 'POST',
                            data: { _token: window.getCsrfToken() },
                            silent: true,
                            timeout: 8000,
                            success: function(r) {
                                if (r && r.csrf_token) {
                                    window.updateCsrfToken(r.csrf_token);
                                }
                            }
                        });
                    }
                },
                error: function(xhr) {
                    failCount++;
                    // Só redirecionar ao login se falhar 3 vezes seguidas
                    // (evita deslogar por uma falha de rede pontual)
                    if (failCount < 3) {
                        console.debug('Keepalive: falha temporária (' + failCount + '/3)');
                        return false; // Impede o handler global de redirecionar
                    }
                }
            });
        }

        setInterval(function() {
            if (Date.now() - lastActivity <= inactivityThreshold) {
                pingSession();
            }
        }, keepaliveInterval);

        $(document).on('mousedown keydown touchstart scroll', function() {
            var now = Date.now();
            if (now - lastActivity > inactivityThreshold) {
                pingSession();
            }
            lastActivity = now;
        });
    })();
});
</script>

<!-- BEGIN: Theme JS-->
<script src="{{ asset(mix('assets/js/main.js')) }}"></script>
<!-- END: Theme JS-->

<!-- Select2 Global Initialization -->
<script src="{{ asset('assets/js/select2-init.js') }}"></script>
<!-- Pricing Modal JS-->
@stack('pricing-script')
<!-- END: Pricing Modal JS-->
<!-- BEGIN: Page JS-->
@yield('page-script')
<!-- END: Page JS-->

{{-- Garantir carregamento do script de ações de usuário nas rotas de usuários. --}}
@if(request()->is('usuarios*') || request()->routeIs('usuarios.*'))
	<script src="{{ asset('assets/js/usuarios-actions.js') }}"></script>
	<script src="{{ asset('assets/js/usuarios-index.js') }}"></script>
@endif
