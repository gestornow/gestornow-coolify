@extends('layouts.layoutMaster')

@section('title', 'Gerenciar Módulos')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<style>
    /* Fix para dropdown não ser cortado pela tabela responsiva */
    .card-body {
        overflow: visible !important;
    }
    
    .table-responsive {
        overflow: visible !important;
    }
    
    /* Em telas menores (mobile), manter scroll horizontal */
    @media (max-width: 991px) {
        .table-responsive {
            overflow-x: auto !important;
            overflow-y: visible !important;
        }
    }
    
    /* Garantir que dropdown fique acima de outros elementos */
    .dropdown-menu {
        z-index: 1050;
        box-shadow: 0 0.25rem 1rem rgba(161, 172, 184, 0.45);
    }
    
    /* Posição do dropdown */
    .table .dropdown {
        position: static;
    }
    
    /* Estilo para cabeçalhos de categoria */
    .categoria-header td {
        font-weight: 600;
        font-size: 0.9rem;
        padding: 12px 16px !important;
        border-bottom: 2px solid rgba(0, 0, 0, 0.1);
    }
    
    /* Estilo para módulos principais com submenu */
    .modulo-principal.has-submenu .modulo-nome-cell {
        cursor: pointer;
        user-select: none;
    }
    
    .modulo-principal.has-submenu:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }
    
    /* Ícone de expansão */
    .icon-expand {
        transition: transform 0.2s ease;
        display: inline-block;
        vertical-align: middle;
        cursor: pointer;
    }
    
    .icon-expand.expanded {
        transform: rotate(90deg);
    }
    
    /* Submódulos */
    .submodulo-row {
        background-color: rgba(0, 0, 0, 0.015);
    }
    
    .submodulo-row:hover {
        background-color: rgba(0, 0, 0, 0.035);
    }
    
    /* Cursor padrão para células */
    .modulo-nome-cell {
        cursor: default;
    }
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
    // ========================================
    // CONFIGURAÇÃO GERAL
    // ========================================
    
    // O CSRF token é configurado globalmente no scripts.blade.php
    // Usamos a função window.ajaxPostWithFreshToken para requisições POST
    
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-danger me-2',
            cancelButton: 'btn btn-secondary'
        },
        buttonsStyling: false
    });

    // ========================================
    // GERENCIAMENTO DE MÓDULOS - FUNÇÕES AUXILIARES
    // ========================================
    
    // Mostrar mensagem de erro
    function mostrarErro(mensagem) {
        Swal.fire({
            title: 'Erro!',
            text: mensagem,
            icon: 'error'
        });
    }

    // Limpar formulário de módulo
    window.limparFormularioModulo = function() {
        $('#modulo_nome, #modulo_id_modulo_pai, #modulo_descricao, #modulo_icone, #modulo_rota, #modulo_categoria').val('');
        $('#modulo_ordem').val('0');
        $('#modulo_is_submodulo').prop('checked', false).trigger('change');
        $('#moduloFormMsg').html('');
    };

    // Preencher select de módulo pai (apenas módulos principais)
    function preencherSelectModuloPai(modulos) {
        const select = $('#modulo_id_modulo_pai');
        select.find('option:not(:first)').remove();
        
        modulos.forEach(function(modulo) {
            if (!modulo.id_modulo_pai) {
                select.append(`<option value="${modulo.id_modulo}">${modulo.nome}</option>`);
            }
        });
    }

    // ========================================
    // GERENCIAMENTO DE MÓDULOS - CARREGAMENTO E RENDERIZAÇÃO
    // ========================================
    
    // Carregar módulos via AJAX
    function carregarModulos() {
        $('#modulosLoader').show();
        $('#modulosContainer').hide();
        $('#modulosEmpty').hide();

        $.ajax({
            url: '{{ route("admin.modulos.list") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    renderizarModulos(response.modulos);
                    preencherSelectModuloPai(response.modulos);
                    atualizarEstatisticas(response.modulos);
                } else {
                    mostrarErro('Erro ao carregar módulos');
                }
            },
            error: function() {
                mostrarErro('Erro ao carregar módulos');
            },
            complete: function() {
                $('#modulosLoader').hide();
            }
        });
    }

    // Atualizar cards estatísticos
    function atualizarEstatisticas(modulos) {
        const total = modulos.length;
        const ativos = modulos.filter(m => m.ativo).length;
        const inativos = total - ativos;
        const submodulos = modulos.filter(m => m.id_modulo_pai).length;
        
        $('#totalModulos').text(total);
        $('#modulosAtivos').text(ativos);
        $('#modulosInativos').text(inativos);
        $('#totalSubmodulos').text(submodulos);
    }

    // Renderizar lista de módulos
    function renderizarModulos(modulos) {
        const tbody = $('#modulosTableBody');
        tbody.empty();

        // Aplicar filtros
        const busca = $('#filtro_busca').val().toLowerCase();
        const status = $('#filtro_status').val();

        let modulosFiltrados = modulos.filter(function(modulo) {
            // Filtro de busca
            if (busca && !modulo.nome.toLowerCase().includes(busca)) {
                return false;
            }
            
            // Filtro de status
            if (status !== '' && modulo.ativo != status) {
                return false;
            }
            
            return true;
        });

        if (modulosFiltrados.length === 0) {
            $('#modulosEmpty').show();
            $('#resultadoCount').text(0);
            return;
        }

        // Separar módulos principais e submódulos
        const principais = modulosFiltrados.filter(m => !m.id_modulo_pai);
        const submodulos = modulosFiltrados.filter(m => m.id_modulo_pai);

        // Agrupar módulos principais por categoria
        const modulosPorCategoria = {};
        principais.forEach(function(modulo) {
            const categoria = modulo.categoria || 'Outros';
            if (!modulosPorCategoria[categoria]) {
                modulosPorCategoria[categoria] = [];
            }
            modulosPorCategoria[categoria].push(modulo);
        });

        // Criar array de categorias com suas ordens para ordenação
        const categorias = @json($categorias->pluck('nome', 'ordem')->toArray());
        const categoriasOrdenadas = [];
        
        // Adicionar categorias existentes no banco com suas ordens
        @foreach($categorias as $cat)
            if (modulosPorCategoria['{{ $cat->nome }}']) {
                categoriasOrdenadas.push({
                    nome: '{{ $cat->nome }}',
                    ordem: {{ $cat->ordem }}
                });
            }
        @endforeach
        
        // Adicionar categoria "Outros" no final se existir
        if (modulosPorCategoria['Outros']) {
            categoriasOrdenadas.push({
                nome: 'Outros',
                ordem: 9999
            });
        }
        
        // Ordenar categorias pela ordem
        categoriasOrdenadas.sort(function(a, b) {
            return a.ordem - b.ordem;
        });

        // Renderizar módulos agrupados por categoria
        let totalRendered = 0;
        categoriasOrdenadas.forEach(function(categoriaObj) {
            const categoria = categoriaObj.nome;
            
            // Adicionar linha de cabeçalho da categoria
            tbody.append(`
                <tr class="categoria-header categoria-header-${categoria.replace(/\s+/g, '-').toLowerCase()}">
                    <td colspan="4" class="bg-label-dark">
                        <strong><i class="ti ti-folder me-2"></i>${categoria}</strong>
                    </td>
                </tr>
            `);

            // Ordenar módulos da categoria por ordem
            const modulosCategoria = modulosPorCategoria[categoria];
            modulosCategoria.sort(function(a, b) {
                const ordemA = parseInt(a.ordem) || 0;
                const ordemB = parseInt(b.ordem) || 0;
                return ordemA !== ordemB ? ordemA - ordemB : a.nome.localeCompare(b.nome, 'pt-BR');
            });

            // Renderizar cada módulo da categoria
            modulosCategoria.forEach(function(modulo) {
                // Buscar submódulos deste módulo
                const subsDoModulo = submodulos.filter(s => s.id_modulo_pai == modulo.id_modulo);
                subsDoModulo.sort(function(a, b) {
                    const ordemA = parseInt(a.ordem) || 0;
                    const ordemB = parseInt(b.ordem) || 0;
                    return ordemA !== ordemB ? ordemA - ordemB : a.nome.localeCompare(b.nome, 'pt-BR');
                });

                const icone = modulo.icone ? `<i class="${modulo.icone} me-2"></i>` : '';
                const statusBadge = modulo.ativo 
                    ? '<span class="badge bg-label-success">Ativo</span>' 
                    : '<span class="badge bg-label-secondary">Inativo</span>';
                const ordemBadge = `<span class="badge bg-label-secondary">#${modulo.ordem || 0}</span>`;
                
                // Ícone de expansão se tiver submódulos
                const expandIcon = subsDoModulo.length > 0 
                    ? `<i class="ti ti-chevron-right me-2 icon-expand cursor-pointer" data-modulo-id="${modulo.id_modulo}"></i>` 
                    : '<i class="ti ti-circle-filled me-2" style="font-size: 6px; opacity: 0.3;"></i>';
                
                const subsBadge = subsDoModulo.length > 0 
                    ? `<span class="badge bg-label-primary ms-2">${subsDoModulo.length} Sub.</span>` 
                    : '';

                const row = `
                    <tr id="modulo-${modulo.id_modulo}" class="modulo-principal ${subsDoModulo.length > 0 ? 'has-submenu' : ''}" data-modulo-id="${modulo.id_modulo}">
                        <td class="modulo-nome-cell">
                            ${expandIcon}${icone}<strong>${modulo.nome}</strong>${subsBadge}
                            ${modulo.descricao ? `<br><small class="text-muted ps-4">${modulo.descricao}</small>` : ''}
                        </td>
                        <td>${statusBadge}</td>
                        <td>${ordemBadge}</td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                    <i class="ti ti-dots-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item btn-edit-modulo" href="javascript:void(0);" 
                                       data-id="${modulo.id_modulo}" data-nome="${modulo.nome}">
                                        <i class="ti ti-edit me-1"></i> Editar
                                    </a>
                                    <a class="dropdown-item text-danger btn-delete-modulo" href="javascript:void(0);" 
                                       data-id="${modulo.id_modulo}" data-nome="${modulo.nome}">
                                        <i class="ti ti-trash me-1"></i> Excluir
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
                tbody.append(row);
                totalRendered++;

                // Renderizar submódulos (inicialmente ocultos)
                subsDoModulo.forEach(function(sub) {
                    const subIcone = sub.icone ? `<i class="${sub.icone} me-2"></i>` : '';
                    const subStatus = sub.ativo 
                        ? '<span class="badge bg-label-success">Ativo</span>' 
                        : '<span class="badge bg-label-secondary">Inativo</span>';
                    const subOrdem = `<span class="badge bg-label-secondary">#${sub.ordem || 0}</span>`;

                    const subRow = `
                        <tr id="modulo-${sub.id_modulo}" class="submodulo-row submodulo-de-${modulo.id_modulo}" style="display: none;">
                            <td class="ps-5">
                                <i class="ti ti-corner-down-right me-2 text-muted"></i>
                                ${subIcone}<span class="text-muted">${sub.nome}</span>
                                <span class="badge bg-label-info ms-2">Submódulo</span>
                                ${sub.descricao ? `<br><small class="text-muted ps-5">${sub.descricao}</small>` : ''}
                            </td>
                            <td>${subStatus}</td>
                            <td>${subOrdem}</td>
                            <td class="text-center">
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                        <i class="ti ti-dots-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item btn-edit-modulo" href="javascript:void(0);" 
                                           data-id="${sub.id_modulo}" data-nome="${sub.nome}">
                                            <i class="ti ti-edit me-1"></i> Editar
                                        </a>
                                        <a class="dropdown-item text-danger btn-delete-modulo" href="javascript:void(0);" 
                                           data-id="${sub.id_modulo}" data-nome="${sub.nome}">
                                            <i class="ti ti-trash me-1"></i> Excluir
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    `;
                    tbody.append(subRow);
                    totalRendered++;
                });
            });
        });

        $('#modulosContainer').show();
        $('#resultadoCount').text(totalRendered);
    }

    // ========================================
    // GERENCIAMENTO DE MÓDULOS - INTERAÇÕES
    // ========================================
    
    // Expandir/colapsar submódulos ao clicar no módulo principal
    $(document).on('click', '.modulo-principal.has-submenu .modulo-nome-cell', function(e) {
        // Não fazer nada se clicar em botões ou ações
        if ($(e.target).closest('.dropdown, .btn').length > 0) {
            return;
        }
        
        const moduloId = $(this).closest('.modulo-principal').data('modulo-id');
        const subRows = $(`.submodulo-de-${moduloId}`);
        const icon = $(this).find('.icon-expand');
        
        if (subRows.first().is(':visible')) {
            subRows.slideUp(200);
            icon.removeClass('expanded');
        } else {
            subRows.slideDown(200);
            icon.addClass('expanded');
        }
    });

    // Clicar diretamente no ícone de expansão
    $(document).on('click', '.icon-expand', function(e) {
        e.stopPropagation();
        $(this).closest('.modulo-nome-cell').trigger('click');
    });
    
    // Filtro de busca (debounce)
    let buscaTimeout;
    $('#filtro_busca').on('input', function() {
        clearTimeout(buscaTimeout);
        buscaTimeout = setTimeout(function() {
            $.ajax({
                url: '{{ route("admin.modulos.list") }}',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        renderizarModulos(response.modulos);
                    }
                }
            });
        }, 300);
    });

    // Filtro de status
    $('#filtro_status').on('change', function() {
        $.ajax({
            url: '{{ route("admin.modulos.list") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    renderizarModulos(response.modulos);
                }
            }
        });
    });

    // Limpar filtros
    $('#btnLimparFiltros').on('click', function() {
        $('#filtro_busca').val('');
        $('#filtro_status').val('');
        carregarModulos();
    });

    // Abrir modal de novo módulo
    $('#btnNovoModulo').on('click', function() {
        limparFormularioModulo();
        $('#moduloFormMsg').html('');
        $('#modulo_is_submodulo').prop('checked', false);
        $('#modulo_tem_submodulos').prop('checked', true); // Marcar por padrão
        $('#modulo_is_submodulo').trigger('change');
        $('#modalNovoModulo').modal('show');
    });
    
    // Toggle de campos baseado no tipo de módulo (Novo Módulo)
    $('#modulo_is_submodulo').on('change', function() {
        const isSubmodulo = $(this).is(':checked');
        
        if (isSubmodulo) {
            // É submódulo - mostrar campo de módulo pai e rota
            $('.campo-submodulo').show();
            $('.campo-rota-modulo').show();
            $('#modulo_id_modulo_pai').prop('required', true);
            
            // Ocultar campos de módulo principal
            $('.campo-modulo-principal').hide();
            $('.campo-tem-submodulos').hide(); // Ocultar "Terá Submódulos"
            $('#modulo_icone, #modulo_categoria').val('').prop('required', false);
            $('#modulo_tem_submodulos').prop('checked', false);
        } else {
            // É módulo principal - ocultar campo de módulo pai
            $('.campo-submodulo').hide();
            $('.campo-modulo-principal').show();
            $('.campo-tem-submodulos').show(); // Mostrar "Terá Submódulos"
            
            // Remover required do módulo pai
            $('#modulo_id_modulo_pai').val('').prop('required', false);
            
            // Verificar se terá submódulos para mostrar/ocultar rota
            $('#modulo_tem_submodulos').trigger('change');
        }
    });

    // Toggle de campo de rota baseado em "Terá Submódulos" (Novo Módulo)
    $('#modulo_tem_submodulos').on('change', function() {
        const temSubmodulo = $(this).is(':checked');
        
        if (temSubmodulo) {
            // Ocultar campo de rota (módulo com submódulos não precisa de rota)
            $('.campo-rota-modulo').hide();
            $('#modulo_rota').val('').prop('required', false);
        } else {
            // Mostrar campo de rota (módulo sem submódulos precisa de rota)
            $('.campo-rota-modulo').show();
        }
    });

    // Toggle de campos baseado no tipo de módulo (Editar Módulo)
    $('#editModulo_is_submodulo').on('change', function() {
        const isSubmodulo = $(this).is(':checked');
        
        if (isSubmodulo) {
            // É submódulo - sempre mostrar rota
            $('.campo-edit-rota-modulo').show();
            $('#editModuloIdModuloPai').prop('required', true);
            
            // Ocultar campos de módulo principal
            $('.campo-edit-modulo-principal').hide();
            $('.campo-edit-tem-submodulos').hide(); // Ocultar "Terá Submódulos"
            $('#editModuloIcone, #editModuloCategoria').val('').prop('required', false);
        } else {
            // É módulo principal
            $('.campo-edit-modulo-principal').show();
            $('.campo-edit-tem-submodulos').show(); // Mostrar "Terá Submódulos"
            
            // Ocultar módulo pai
            $('#editModuloIdModuloPai').val('').prop('required', false);
            
            // Verificar se terá submódulos para mostrar/ocultar rota
            $('#editModulo_tem_submodulos').trigger('change');
        }
    });

    // Toggle de campo de rota baseado em "Terá Submódulos" (Editar Módulo)
    $('#editModulo_tem_submodulos').on('change', function() {
        const temSubmodulo = $(this).is(':checked');
        
        if (temSubmodulo) {
            // Ocultar campo de rota (módulo com submódulos não precisa de rota)
            $('.campo-edit-rota-modulo').hide();
            $('#editModuloRota').val('').prop('required', false);
        } else {
            // Mostrar campo de rota (módulo sem submódulos precisa de rota)
            $('.campo-edit-rota-modulo').show();
        }
    });

    // ========================================
    // GERENCIAMENTO DE MÓDULOS - CRUD
    // ========================================
    
    // Criar novo módulo
    $('#formNovoModulo').on('submit', function(e) {
        e.preventDefault();
        $('#moduloFormMsg').html('');
        
        const isSubmodulo = $('#modulo_is_submodulo').is(':checked');
        const temSubmodulo = $('#modulo_tem_submodulos').is(':checked');
        
        // Coletar rota (se for submódulo OU módulo sem submódulos)
        const rota = (isSubmodulo || !temSubmodulo) ? ($('#modulo_rota').val().trim() || null) : null;
        
        const dados = {
            nome: $('#modulo_nome').val().trim(),
            id_modulo_pai: $('#modulo_id_modulo_pai').val() || null,
            descricao: $('#modulo_descricao').val().trim() || null,
            icone: $('#modulo_icone').val().trim() || null,
            rota: rota,
            categoria: $('#modulo_categoria').val() || null,
            ordem: $('#modulo_ordem').val() || 0,
            tem_submodulos: temSubmodulo ? 1 : 0
        };
        
        console.log('Criando módulo:', {isSubmodulo, temSubmodulo, rota, dados});

        if (!dados.nome) {
            $('#moduloFormMsg').html(`
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="ti ti-alert-circle me-1"></i>
                    O nome do módulo é obrigatório.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `);
            return;
        }

        const btnSubmit = $(this).find('button[type="submit"]');
        btnSubmit.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Salvando...');

        // Usar função com token fresco para evitar erro 419
        window.ajaxPostWithFreshToken({
            url: '{{ route("admin.modulos.store") }}',
            method: 'POST',
            data: dados
        }).then(function(response) {
            if (response.success) {
                $('#modalNovoModulo').modal('hide');
                
                Swal.fire({
                    title: 'Sucesso!',
                    text: 'Módulo criado com sucesso!',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
                
                limparFormularioModulo();
                carregarModulos();
            } else {
                $('#moduloFormMsg').html(`
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="ti ti-alert-circle me-1"></i>
                        ${response.message || 'Erro ao criar módulo.'}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `);
            }
        }).catch(function(xhr) {
            // Erro 419 já é tratado globalmente, só tratar outros erros
            if (xhr.status !== 419) {
                let errorMsg = 'Erro ao criar módulo.';
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    errorMsg = Object.values(xhr.responseJSON.errors)[0][0];
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                
                $('#moduloFormMsg').html(`
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="ti ti-alert-circle me-1"></i>
                        ${errorMsg}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `);
            }
        }).finally(function() {
            btnSubmit.prop('disabled', false).html('<i class="ti ti-device-floppy me-1"></i> Salvar Módulo');
        });
    });

    // Abrir modal de edição
    $(document).on('click', '.btn-edit-modulo', function() {
        const id = $(this).data('id');
        
        $.ajax({
            url: `/admin/modulos/${id}/edit`,
            method: 'GET',
            success: function(response) {
                $('#editModuloId').val(id);
                $('#editModuloNome').val(response.nome || '');
                $('#editModuloDescricao').val(response.descricao || '');
                $('#editModuloIcone').val(response.icone || '');
                $('#editModuloCategoria').val(response.categoria || '');
                $('#editModuloOrdem').val(response.ordem || 0);
                $('#editModuloAtivo').val(response.ativo ? '1' : '0');
                $('#editModuloIdModuloPai').val(response.id_modulo_pai || '');
                
                // Configurar checkbox de submódulo
                const isSubmodulo = response.id_modulo_pai ? true : false;
                $('#editModulo_is_submodulo').prop('checked', isSubmodulo).trigger('change');
                
                // Configurar checkbox de tem submódulo
                const temSubmodulo = response.tem_submodulos ? true : false;
                $('#editModulo_tem_submodulos').prop('checked', temSubmodulo).trigger('change');
                
                // Definir valor da rota (agora usamos apenas um campo)
                $('#editModuloRota').val(response.rota || '');
                
                // Atualizar select de módulo pai
                const select = $('#editModuloIdModuloPai');
                select.find('option:not(:first)').remove();
                
                $.ajax({
                    url: '{{ route("admin.modulos.list") }}',
                    method: 'GET',
                    success: function(modulosResponse) {
                        if (modulosResponse.success) {
                            modulosResponse.modulos.forEach(function(modulo) {
                                if (!modulo.id_modulo_pai && modulo.id_modulo != id) {
                                    const selected = response.id_modulo_pai == modulo.id_modulo ? 'selected' : '';
                                    select.append(`<option value="${modulo.id_modulo}" ${selected}>${modulo.nome}</option>`);
                                }
                            });
                        }
                    }
                });
                
                $('#modalEditarModulo').modal('show');
                $('#editModuloNome').focus();
            },
            error: function() {
                mostrarErro('Erro ao carregar dados do módulo');
            }
        });
    });

    // Salvar edição do módulo
    $('#formEditarModulo').on('submit', function(e) {
        e.preventDefault();
        
        const id = $('#editModuloId').val();
        const nome = $('#editModuloNome').val().trim();
        
        if (!nome) {
            $('#editModuloNome').addClass('is-invalid');
            $('#editModuloNome').siblings('.invalid-feedback').text('Nome é obrigatório');
            return;
        }
        
        if (nome.length > 100) {
            $('#editModuloNome').addClass('is-invalid');
            $('#editModuloNome').siblings('.invalid-feedback').text('Nome não pode ter mais que 100 caracteres');
            return;
        }
        
        $('#editModuloNome').removeClass('is-invalid');
        
        const btnSubmit = $(this).find('button[type="submit"]');
        btnSubmit.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Salvando...');
        
        const isSubmodulo = $('#editModulo_is_submodulo').is(':checked');
        const temSubmodulo = $('#editModulo_tem_submodulos').is(':checked');
        
        // Coletar rota (se for submódulo OU módulo sem submódulos)
        const rota = (isSubmodulo || !temSubmodulo) ? ($('#editModuloRota').val().trim() || null) : null;
        
        console.log('Editando módulo:', {isSubmodulo, temSubmodulo, rota});
        
        // Usar função com token fresco para evitar erro 419
        window.ajaxPostWithFreshToken({
            url: `/admin/modulos/${id}`,
            method: 'POST',
            data: {
                _method: 'PUT',
                nome: nome,
                descricao: $('#editModuloDescricao').val().trim() || null,
                icone: $('#editModuloIcone').val().trim() || null,
                rota: rota,
                categoria: $('#editModuloCategoria').val() || null,
                ordem: $('#editModuloOrdem').val() || 0,
                ativo: $('#editModuloAtivo').val(),
                id_modulo_pai: $('#editModuloIdModuloPai').val() || null,
                tem_submodulos: temSubmodulo ? 1 : 0
            }
        }).then(function(response) {
            if (response && response.success) {
                $('#modalEditarModulo').modal('hide');
                Swal.fire({
                    title: 'Sucesso!',
                    text: response.message,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
                carregarModulos();
            } else {
                mostrarErro(response && response.message ? response.message : 'Erro ao atualizar módulo');
            }
        }).catch(function(xhr) {
            if (xhr.status !== 419) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    const errorMsg = Object.values(xhr.responseJSON.errors).flat().join(', ');
                    $('#editModuloNome').addClass('is-invalid');
                    $('#editModuloNome').siblings('.invalid-feedback').text(errorMsg);
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    mostrarErro(xhr.responseJSON.message);
                } else {
                    mostrarErro('Erro ao atualizar módulo');
                }
            }
        }).finally(function() {
            btnSubmit.prop('disabled', false).html('<i class="ti ti-check me-1"></i> Salvar');
        });
    });

    // Excluir módulo
    $(document).on('click', '.btn-delete-modulo', function() {
        const id = $(this).data('id');
        const nome = $(this).data('nome');

        swalWithBootstrapButtons.fire({
            title: 'Tem certeza?',
            html: `Deseja realmente excluir o módulo <strong>"${nome}"</strong>?<br><br><small class="text-muted">Se estiver sendo usado em planos, será removido automaticamente.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Usar função com token fresco para evitar erro 419
                window.ajaxPostWithFreshToken({
                    url: `/admin/modulos/${id}`,
                    method: 'POST',
                    data: {
                        _method: 'DELETE'
                    }
                }).then(function(response) {
                    if (response && response.success) {
                        Swal.fire({
                            title: 'Sucesso!',
                            text: response.message,
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        
                        // Guardar referência do elemento antes de remover
                        const moduloRow = $(`#modulo-${id}`);
                        const isSubmodulo = moduloRow.hasClass('submodulo-row');
                        
                        // Se for módulo principal, pegar a categoria
                        let categoriaHeader = null;
                        if (!isSubmodulo) {
                            // Procurar o cabeçalho de categoria anterior
                            categoriaHeader = moduloRow.prevAll('.categoria-header').first();
                        }
                        
                        // Remover o módulo e seus submódulos
                        if (!isSubmodulo) {
                            // Remover submódulos associados
                            $(`.submodulo-de-${id}`).fadeOut(300, function() {
                                $(this).remove();
                            });
                        }
                        
                        moduloRow.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Verificar se a categoria ficou vazia (sem módulos principais)
                            if (categoriaHeader && categoriaHeader.length > 0) {
                                const nextElement = categoriaHeader.next();
                                // Se o próximo elemento for outro cabeçalho de categoria ou não existir, remover a categoria vazia
                                if (!nextElement.length || nextElement.hasClass('categoria-header')) {
                                    categoriaHeader.fadeOut(300, function() {
                                        $(this).remove();
                                    });
                                }
                            }
                            
                            // Verificar se a tabela ficou vazia
                            if ($('#modulosTableBody tr:visible').length === 0) {
                                $('#modulosContainer').hide();
                                $('#modulosEmpty').show();
                            }
                            
                            // Recarregar estatísticas
                            carregarModulos();
                        });
                    } else {
                        mostrarErro(response && response.message ? response.message : 'Erro ao excluir módulo');
                    }
                }).catch(function(xhr) {
                    if (xhr.status !== 419) {
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            mostrarErro(xhr.responseJSON.message);
                        } else {
                            mostrarErro('Erro ao excluir módulo');
                        }
                    }
                });
            }
        });
    });

    // Alternar ícone e texto do botão de expandir formulário
    $('#collapseFormModulo').on('show.bs.collapse', function() {
        $('#iconToggleFormModulo').removeClass('ti-plus').addClass('ti-minus');
        $('#textToggleFormModulo').text('Ocultar Formulário');
    });

    $('#collapseFormModulo').on('hide.bs.collapse', function() {
        $('#iconToggleFormModulo').removeClass('ti-minus').addClass('ti-plus');
        $('#textToggleFormModulo').text('Adicionar Novo Módulo');
    });

    // Carregar módulos quando a página carrega
    carregarModulos();
});
</script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <!-- Cards Estatísticos -->
            <div class="row mb-2">
                <div class="col-md-3 col-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-primary">
                                    <i class="ti ti-package ti-28"></i>
                                </span>
                            </div>
                            <div>
                                <p class="mb-0 text-muted small">Total de Módulos</p>
                                <h4 class="mb-0" id="totalModulos">0</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-success">
                                    <i class="ti ti-check ti-28"></i>
                                </span>
                            </div>
                            <div>
                                <p class="mb-0 text-muted small">Módulos Ativos</p>
                                <h4 class="mb-0" id="modulosAtivos">0</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-warning">
                                    <i class="ti ti-x ti-28"></i>
                                </span>
                            </div>
                            <div>
                                <p class="mb-0 text-muted small">Módulos Inativos</p>
                                <h4 class="mb-0" id="modulosInativos">0</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-info">
                                    <i class="ti ti-corner-down-right ti-28"></i>
                                </span>
                            </div>
                            <div>
                                <p class="mb-0 text-muted small">Submódulos</p>
                                <h4 class="mb-0" id="totalSubmodulos">0</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros de Busca -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Filtros de Busca</h5>
                    <form id="filtroForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Buscar</label>
                                <input type="text" class="form-control" id="filtro_busca" placeholder="Nome do módulo...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="filtro_status">
                                    <option value="">Todos</option>
                                    <option value="1">Ativos</option>
                                    <option value="0">Inativos</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-label-secondary w-100" id="btnLimparFiltros">
                                    <i class="ti ti-x me-1"></i>
                                    Limpar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de Módulos -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="ti ti-list me-1"></i>
                        Lista de Módulos
                    </h5>
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.planos.index') }}" class="btn btn-secondary">
                            <i class="ti ti-arrow-left me-1"></i>
                            Voltar
                        </a>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoModulo">
                            <i class="ti ti-plus me-1"></i>
                            Novo Módulo
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3"><span id="resultadoCount">0</span> resultado(s)</p>
                    
                    <div id="modulosLoader" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>

                    <div id="modulosContainer" style="display: none;">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50%">MÓDULO</th>
                                        <th width="15%">STATUS</th>
                                        <th width="15%">ORDEM</th>
                                        <th width="20%" class="text-center">AÇÕES</th>
                                    </tr>
                                </thead>
                                <tbody id="modulosTableBody">
                                    <!-- Módulos serão carregados aqui via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="modulosEmpty" style="display: none;" class="text-center py-5">
                        <i class="ti ti-package-off" style="font-size: 48px;" class="text-muted mb-3"></i>
                        <p class="text-muted">Nenhum módulo encontrado</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Novo Módulo -->
<div class="modal fade" id="modalNovoModulo" tabindex="-1" aria-labelledby="modalNovoModuloLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNovoModuloLabel">
                    <i class="ti ti-plus me-2"></i>
                    Novo Módulo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formNovoModulo">
                <div class="modal-body">
                    <div class="alert alert-info mb-4">
                        <i class="ti ti-info-circle me-1"></i>
                        <strong>Dica:</strong> Marque "É Submódulo" para criar um item dentro de um módulo principal.
                    </div>

                    <div id="moduloFormMsg"></div>

                    @csrf
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="modulo_is_submodulo" name="is_submodulo">
                                <label class="form-check-label" for="modulo_is_submodulo">
                                    <strong>É Submódulo?</strong>
                                    <small class="text-muted d-block">Marque se este módulo pertence a outro módulo</small>
                                </label>
                            </div>
                        </div>

                        <!-- Campo Terá Submódulos - Apenas para módulos principais -->
                        <div class="col-md-12 mb-4 campo-modulo-principal campo-tem-submodulos">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="modulo_tem_submodulos" name="tem_submodulos" checked>
                                <label class="form-check-label" for="modulo_tem_submodulos">
                                    <strong>Terá Submódulos?</strong>
                                    <small class="text-muted d-block">Marque se este módulo terá submódulos vinculados</small>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="modulo_nome" class="form-label">Nome do Módulo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="modulo_nome" name="nome" required placeholder="Ex: Financeiro, Produtos">
                            <div class="invalid-feedback"></div>
                        </div>

                        <!-- Campo Módulo Pai - Apenas para submódulos -->
                        <div class="col-md-12 mb-3 campo-submodulo" style="display: none;">
                            <label for="modulo_id_modulo_pai" class="form-label">Módulo Pai <span class="text-danger">*</span></label>
                            <select class="form-select" id="modulo_id_modulo_pai" name="id_modulo_pai">
                                <option value="">Selecione o módulo pai</option>
                            </select>
                            <small class="text-muted">Módulo ao qual este submódulo pertence</small>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="modulo_descricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="modulo_descricao" name="descricao" rows="2" placeholder="Descrição opcional do módulo"></textarea>
                        </div>

                        <!-- Campo Ícone - Apenas para módulos principais -->
                        <div class="col-md-6 mb-3 campo-modulo-principal campo-icone-principal">
                            <label for="modulo_icone" class="form-label">Ícone (Tabler Icons)</label>
                            <input type="text" class="form-control" id="modulo_icone" name="icone" placeholder="Ex: ti ti-wallet, ti ti-box">
                            <small class="text-muted">Veja: <a href="https://tabler-icons.io/" target="_blank">tabler-icons.io</a></small>
                        </div>

                        <!-- Campo Categoria - Apenas para módulos principais -->
                        <div class="col-md-6 mb-3 campo-modulo-principal campo-categoria-principal">
                            <label for="modulo_categoria" class="form-label">Categoria do Menu</label>
                            <select class="form-select" id="modulo_categoria" name="categoria">
                                <option value="">Nenhuma (Outros)</option>
                                @foreach($categorias as $categoria)
                                    <option value="{{ $categoria->nome }}">{{ $categoria->nome }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Categoria em que o módulo aparecerá no menu</small>
                        </div>

                        <!-- Campo Rota - Para submódulos E módulos principais sem submódulos -->
                        <div class="col-md-12 mb-3 campo-rota-modulo" style="display: none;">
                            <label for="modulo_rota" class="form-label">Rota</label>
                            <input type="text" class="form-control" id="modulo_rota" placeholder="/admin/clientes">
                            <small class="text-muted">URL da página (Ex: /admin/clientes, /admin/produtos/listar)</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="modulo_ordem" class="form-label">Ordem de Exibição</label>
                            <input type="number" class="form-control" id="modulo_ordem" name="ordem" value="0" min="0" placeholder="0">
                            <small class="text-muted">Ordem dentro da categoria (0 = primeiro)</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i>
                        Salvar Módulo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Editar Módulo -->
<div class="modal fade" id="modalEditarModulo" tabindex="-1" aria-labelledby="modalEditarModuloLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarModuloLabel">
                    <i class="ti ti-edit me-2"></i>
                    Editar Módulo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formEditarModulo">
                <div class="modal-body">
                    <input type="hidden" id="editModuloId">
                    
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editModulo_is_submodulo" name="is_submodulo" disabled>
                                <label class="form-check-label" for="editModulo_is_submodulo">
                                    <strong>É Submódulo?</strong>
                                    <small class="text-muted d-block">Não é possível alterar o tipo após a criação</small>
                                </label>
                            </div>
                        </div>

                        <!-- Campo Terá Submódulos - Apenas para módulos principais -->
                        <div class="col-md-12 mb-4 campo-edit-modulo-principal campo-edit-tem-submodulos">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editModulo_tem_submodulos" name="tem_submodulos">
                                <label class="form-check-label" for="editModulo_tem_submodulos">
                                    <strong>Terá Submódulos?</strong>
                                    <small class="text-muted d-block">Marque se este módulo terá submódulos vinculados</small>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="editModuloNome" class="form-label">Nome do Módulo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editModuloNome" name="nome" required maxlength="100" placeholder="Ex: Financeiro, Produtos">
                            <div class="invalid-feedback"></div>
                        </div>

                        <!-- Campo Módulo Pai - Apenas para submódulos -->
                        <div class="col-md-12 mb-3 campo-edit-submodulo" style="display: none;">
                            <label for="editModuloIdModuloPai" class="form-label">Módulo Pai <span class="text-danger">*</span></label>
                            <select class="form-select" id="editModuloIdModuloPai" name="id_modulo_pai">
                                <option value="">Selecione o módulo pai</option>
                            </select>
                            <small class="text-muted">Módulo ao qual este submódulo pertence</small>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="editModuloDescricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="editModuloDescricao" name="descricao" rows="2" placeholder="Descrição opcional do módulo"></textarea>
                        </div>

                        <!-- Campo Ícone - Apenas para módulos principais -->
                        <div class="col-md-6 mb-3 campo-edit-modulo-principal campo-edit-icone-principal">
                            <label for="editModuloIcone" class="form-label">Ícone (Tabler Icons)</label>
                            <input type="text" class="form-control" id="editModuloIcone" name="icone" placeholder="Ex: ti ti-wallet, ti ti-box">
                            <small class="text-muted">Veja: <a href="https://tabler-icons.io/" target="_blank">tabler-icons.io</a></small>
                        </div>

                        <!-- Campo Categoria - Apenas para módulos principais -->
                        <div class="col-md-6 mb-3 campo-edit-modulo-principal campo-edit-categoria-principal">
                            <label for="editModuloCategoria" class="form-label">Categoria do Menu</label>
                            <select class="form-select" id="editModuloCategoria" name="categoria">
                                <option value="">Nenhuma (Outros)</option>
                                @foreach($categorias as $categoria)
                                    <option value="{{ $categoria->nome }}">{{ $categoria->nome }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Categoria em que o módulo aparecerá no menu</small>
                        </div>

                        <!-- Campo Rota - Para submódulos E módulos principais sem submódulos -->
                        <div class="col-md-12 mb-3 campo-edit-rota-modulo" style="display: none;">
                            <label for="editModuloRota" class="form-label">Rota</label>
                            <input type="text" class="form-control" id="editModuloRota" placeholder="/admin/clientes">
                            <small class="text-muted">URL da página (Ex: /admin/clientes, /admin/produtos/listar)</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="editModuloOrdem" class="form-label">Ordem de Exibição</label>
                            <input type="number" class="form-control" id="editModuloOrdem" name="ordem" value="0" min="0" placeholder="0">
                            <small class="text-muted">Ordem dentro da categoria (0 = primeiro)</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="editModuloAtivo" class="form-label">Status</label>
                            <select class="form-select" id="editModuloAtivo" name="ativo">
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-check me-1"></i>
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
