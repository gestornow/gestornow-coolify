// Aguarda o documento estar pronto
$(document).ready(function() {
    // Verifica se jQuery está disponível
    if (typeof $ === 'undefined') {
        console.error('jQuery não está carregado!');
        return;
    }
    
    // Verifica se Select2 está disponível
    if (typeof $.fn.select2 === 'undefined') {
        console.error('Select2 não está carregado!');
        return;
    }
    
    // Previne conflitos com Bootstrap dropdowns
    $(document).on('click', '.select2-container', function(e) {
        e.stopPropagation();
    });
    
    // Previne fechamento ao clicar na caixa de pesquisa
    $(document).on('click', '.select2-dropdown', function(e) {
        e.stopPropagation();
    });
    
    // Previne fechamento ao clicar no campo de busca
    $(document).on('click', '.select2-search__field', function(e) {
        e.stopPropagation();
    });
    
    // Previne fechamento ao clicar nos resultados
    $(document).on('click', '.select2-results', function(e) {
        e.stopPropagation();
    });
    
    // Previne fechamento em todos os elementos do Select2
    $(document).on('click mousedown', '[class*="select2"]', function(e) {
        e.stopPropagation();
    });
    
    // Intercepta eventos de input e teclado no Select2
    $(document).on('keydown keyup keypress input', '.select2-search__field', function(e) {
        e.stopPropagation();
    });
    
    // Previne que Bootstrap dropdown feche quando interagir com Select2
    $(document).on('click.bs.dropdown.data-api', '.dropdown-menu', function(e) {
        if ($(e.target).closest('[class*="select2"]').length > 0) {
            e.stopPropagation();
            return false;
        }
    });
    
    // Inicializa Select2
    initSelect2();
    
    // Observer para elementos dinâmicos
    if (window.MutationObserver) {
        var observer = new MutationObserver(function(mutations) {
            var shouldReinit = false;
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            if ($(node).is('select.select2') || $(node).find('select.select2').length > 0) {
                                shouldReinit = true;
                            }
                        }
                    });
                }
            });
            if (shouldReinit) {
                setTimeout(initSelect2, 100);
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
});

// Função para inicializar Select2
function initSelect2() {
    // Encontra todos os selects com classe select2 que ainda não foram inicializados
    var $selects = $('select.select2').not('.select2-hidden-accessible');
    
    if ($selects.length === 0) {
        return;
    }
    
    $selects.each(function() {
        var $select = $(this);
        var isInDropdown = $select.closest('.dropdown-menu').length > 0;
        
        try {
            var config = {
                width: '100%',
                placeholder: $select.data('placeholder') || 'Pesquisar...',
                allowClear: false,
                dropdownAutoWidth: false,
                dropdownCssClass: 'select2-custom-dropdown' + (isInDropdown ? ' select2-in-dropdown' : ''),
                containerCssClass: 'select2-custom-container' + (isInDropdown ? ' select2-in-dropdown' : '')
            };
            
            // Se está em dropdown, anexa o dropdown ao body para evitar conflitos
            if (isInDropdown) {
                config.dropdownParent = $('body');
            }
            
            $select.select2(config);
            
            // Previne que qualquer clique no Select2 feche dropdowns pai
            var $container = $select.next('.select2-container');
            
            $container.on('click mousedown', function(e) {
                e.stopPropagation();
                e.preventDefault();
                return false;
            });
            
            // Event listeners para o dropdown do Select2
            $select.on('select2:open', function(e) {
                setTimeout(function() {
                    var $dropdown = $('.select2-dropdown');
                    var $container = $select.next('.select2-container');
                    var containerWidth = $container.outerWidth();

                    $dropdown.on('click mousedown', function(e) {
                        e.stopPropagation();
                    });
                    $('.select2-search__field').on('click mousedown keydown keyup', function(e) {
                        e.stopPropagation();
                    });

                    // Força o dropdown ter exatamente a mesma largura do container
                    $dropdown.css({
                        'max-width': containerWidth + 'px',
                        'width': containerWidth + 'px',
                        'overflow': 'hidden'
                    });

                    // Garante que as opções quebrem linha corretamente
                    $dropdown.find('.select2-results__option').css({
                        'white-space': 'normal',
                        'word-break': 'break-word',
                        'overflow-wrap': 'break-word'
                    });
                }, 1);
            });
        } catch (error) {
            console.error('Erro ao inicializar Select2:', error, this);
        }
    });
}

// Função global para reinicializar Select2
window.initSelect2 = initSelect2;
window.reinitSelect2 = function() {
    // Destroi instâncias existentes
    $('select.select2').each(function() {
        if ($(this).hasClass('select2-hidden-accessible')) {
            try {
                $(this).select2('destroy');
            } catch (error) {
                console.warn('Erro ao destruir Select2:', error);
            }
        }
    });
    
    // Reinicializa
    initSelect2();
};