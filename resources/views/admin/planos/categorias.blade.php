@extends('layouts.layoutMaster')

@section('title', 'Gerenciar Categorias de Menu')

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
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-danger me-2',
            cancelButton: 'btn btn-secondary'
        },
        buttonsStyling: false
    });

    // ========================================
    // GERENCIAMENTO DE CATEGORIAS - FUNÇÕES AUXILIARES
    // ========================================
    
    // Mostrar mensagem de erro
    function mostrarErro(mensagem) {
        Swal.fire({
            title: 'Erro!',
            text: mensagem,
            icon: 'error'
        });
    }

    // Limpar formulário de categoria
    window.limparFormularioCategoria = function() {
        $('#categoria_nome').val('');
        $('#categoria_ordem').val('0');
        $('#categoriaFormMsg').html('');
    };

    // ========================================
    // GERENCIAMENTO DE CATEGORIAS - CARREGAMENTO E RENDERIZAÇÃO
    // ========================================
    
    // Função para carregar categorias via AJAX
    function carregarCategorias() {
        $('#categoriasLoader').show();
        $('#categoriasContainer').hide();
        $('#categoriasEmpty').hide();
        
        $.ajax({
            url: '{{ route("admin.categorias.list") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    renderizarCategorias(response.categorias);
                    atualizarEstatisticas(response.categorias);
                } else {
                    mostrarErro('Erro ao carregar categorias');
                }
            },
            error: function(xhr) {
                $('#categoriasLoader').hide();
                mostrarErro('Erro ao carregar categorias');
            },
            complete: function() {
                $('#categoriasLoader').hide();
            }
        });
    }

    // Atualizar cards estatísticos
    function atualizarEstatisticas(categorias) {
        const total = categorias.length;
        const ativas = categorias.filter(c => c.ativo).length;
        const inativas = total - ativas;
        
        $('#totalCategorias').text(total);
        $('#categoriasAtivas').text(ativas);
        $('#categoriasInativas').text(inativas);
    }

    // Função para renderizar categorias na tabela
    function renderizarCategorias(categorias) {
        const tbody = $('#categoriasTableBody');
        tbody.empty();

        // Aplicar filtros
        const busca = $('#filtro_busca').val().toLowerCase();
        const status = $('#filtro_status').val();

        let categoriasFiltradas = categorias.filter(function(categoria) {
            // Filtro de busca
            if (busca && !categoria.nome.toLowerCase().includes(busca)) {
                return false;
            }
            
            // Filtro de status
            if (status !== '' && categoria.ativo != status) {
                return false;
            }
            
            return true;
        });

        if (categoriasFiltradas.length === 0) {
            $('#categoriasEmpty').show();
            $('#resultadoCount').text(0);
            return;
        }

        // Ordenar por ordem
        categoriasFiltradas.sort(function(a, b) {
            const ordemA = parseInt(a.ordem) || 0;
            const ordemB = parseInt(b.ordem) || 0;
            return ordemA !== ordemB ? ordemA - ordemB : a.nome.localeCompare(b.nome, 'pt-BR');
        });
        
        categoriasFiltradas.forEach(function(categoria) {
            const statusBadge = categoria.ativo 
                ? '<span class="badge bg-label-success">Ativo</span>' 
                : '<span class="badge bg-label-secondary">Inativo</span>';
            const ordemBadge = `<span class="badge bg-label-secondary">#${categoria.ordem || 0}</span>`;

            const tr = `
                <tr id="categoria-${categoria.id_categoria}">
                    <td>
                        <strong>${categoria.nome}</strong>
                    </td>
                    <td>${statusBadge}</td>
                    <td>${ordemBadge}</td>
                    <td class="text-center">
                        <div class="dropdown">
                            <button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item btn-edit-categoria" href="javascript:void(0);" 
                                   data-id="${categoria.id_categoria}" data-nome="${categoria.nome}">
                                    <i class="ti ti-edit me-1"></i> Editar
                                </a>
                                <a class="dropdown-item text-danger btn-delete-categoria" href="javascript:void(0);" 
                                   data-id="${categoria.id_categoria}" data-nome="${categoria.nome}">
                                    <i class="ti ti-trash me-1"></i> Excluir
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(tr);
        });

        $('#categoriasContainer').show();
        $('#resultadoCount').text(categoriasFiltradas.length);
    }

    // ========================================
    // GERENCIAMENTO DE CATEGORIAS - INTERAÇÕES
    // ========================================
    
    // Filtro de busca (debounce)
    let buscaTimeout;
    $('#filtro_busca').on('input', function() {
        clearTimeout(buscaTimeout);
        buscaTimeout = setTimeout(function() {
            $.ajax({
                url: '{{ route("admin.categorias.list") }}',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        renderizarCategorias(response.categorias);
                    }
                }
            });
        }, 300);
    });

    // Filtro de status
    $('#filtro_status').on('change', function() {
        $.ajax({
            url: '{{ route("admin.categorias.list") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    renderizarCategorias(response.categorias);
                }
            }
        });
    });

    // Limpar filtros
    $('#btnLimparFiltros').on('click', function() {
        $('#filtro_busca').val('');
        $('#filtro_status').val('');
        carregarCategorias();
    });

    // Abrir modal de nova categoria
    $('#btnNovaCategoria').on('click', function() {
        limparFormularioCategoria();
        $('#categoriaFormMsg').html('');
        $('#modalNovaCategoria').modal('show');
    });

    // ========================================
    // GERENCIAMENTO DE CATEGORIAS - CRUD
    // ========================================
    
    // Criar nova categoria
    $('#formNovaCategoria').on('submit', function(e) {
        e.preventDefault();
        $('#categoriaFormMsg').html('');
        
        const nomeInput = $('#categoria_nome');
        const ordemInput = $('#categoria_ordem');
        
        console.log('Nome input encontrado:', nomeInput.length);
        console.log('Ordem input encontrado:', ordemInput.length);
        
        const dados = {
            _token: '{{ csrf_token() }}',
            nome: nomeInput.val() ? nomeInput.val().trim() : '',
            ordem: ordemInput.val() || 0
        };
        
        console.log('Dados a enviar:', dados);

        if (!dados.nome) {
            $('#categoriaFormMsg').html(`
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="ti ti-alert-circle me-1"></i>
                    O nome da categoria é obrigatório!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `);
            return;
        }

        const btnSubmit = $(this).find('button[type="submit"]');
        btnSubmit.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i> Salvando...');

        $.ajax({
            url: '{{ route("admin.categorias.store") }}',
            method: 'POST',
            data: dados,
            success: function(response) {
                if (response.success) {
                    $('#modalNovaCategoria').modal('hide');
                    
                    Swal.fire({
                        title: 'Sucesso!',
                        text: 'Categoria criada com sucesso!',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });

                    limparFormularioCategoria();
                    carregarCategorias();
                } else {
                    $('#categoriaFormMsg').html(`
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="ti ti-alert-circle me-1"></i>
                            ${response.message || 'Erro ao criar categoria'}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON && xhr.responseJSON.message 
                    ? xhr.responseJSON.message 
                    : 'Erro ao criar categoria';
                    
                $('#categoriaFormMsg').html(`
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="ti ti-alert-circle me-1"></i>
                        ${errorMsg}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `);
            },
            complete: function() {
                btnSubmit.prop('disabled', false).html('<i class="ti ti-device-floppy me-1"></i> Salvar Categoria');
            }
        });
    });

    // Abrir modal de edição de categoria
    $(document).on('click', '.btn-edit-categoria', function() {
        const id = $(this).data('id');
        
        $.ajax({
            url: `/admin/categorias-menu/${id}`,
            method: 'GET',
            success: function(response) {
                $('#editCategoriaId').val(id);
                $('#editCategoriaNome').val(response.nome || '');
                $('#editCategoriaOrdem').val(response.ordem || 0);
                $('#editCategoriaAtivo').val(response.ativo ? '1' : '0');
                
                $('#modalEditarCategoria').modal('show');
            },
            error: function(xhr) {
                mostrarErro('Erro ao carregar dados da categoria');
            }
        });
    });

    // Atualizar categoria
    $('#formEditarCategoria').on('submit', function(e) {
        e.preventDefault();
        $('#editCategoriaFormMsg').html('');
        
        const id = $('#editCategoriaId').val();
        const dados = {
            _token: '{{ csrf_token() }}',
            _method: 'PUT',
            nome: $('#editCategoriaNome').val().trim(),
            ordem: $('#editCategoriaOrdem').val() || 0,
            ativo: $('#editCategoriaAtivo').val()
        };

        const btnSubmit = $(this).find('button[type="submit"]');
        btnSubmit.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i> Salvando...');

        $.ajax({
            url: `/admin/categorias-menu/${id}`,
            method: 'POST',
            data: dados,
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    });

                    $('#modalEditarCategoria').modal('hide');
                    carregarCategorias();
                } else {
                    $('#editCategoriaFormMsg').html(`
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="ti ti-alert-circle me-1"></i>
                            ${response.message || 'Erro ao atualizar categoria'}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON && xhr.responseJSON.message 
                    ? xhr.responseJSON.message 
                    : 'Erro ao atualizar categoria';
                    
                $('#editCategoriaFormMsg').html(`
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="ti ti-alert-circle me-1"></i>
                        ${errorMsg}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `);
            },
            complete: function() {
                btnSubmit.prop('disabled', false).html('<i class="ti ti-check me-1"></i> Salvar');
            }
        });
    });

    // Excluir categoria
    $(document).on('click', '.btn-delete-categoria', function() {
        const id = $(this).data('id');
        const nome = $(this).data('nome');

        swalWithBootstrapButtons.fire({
            title: 'Tem certeza?',
            html: `Deseja realmente excluir a categoria <strong>"${nome}"</strong>?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/admin/categorias-menu/${id}`,
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        _method: 'DELETE'
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                title: 'Excluída!',
                                text: 'Categoria excluída com sucesso.',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                            
                            $(`#categoria-${id}`).fadeOut(300, function() {
                                $(this).remove();
                                if ($('#categoriasTableBody tr').length === 0) {
                                    $('#categoriasContainer').hide();
                                    $('#categoriasEmpty').show();
                                }
                            });
                        } else {
                            mostrarErro(response && response.message ? response.message : 'Erro ao excluir categoria');
                        }
                    },
                    error: function(xhr) {
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            mostrarErro(xhr.responseJSON.message);
                        } else {
                            mostrarErro('Erro ao excluir categoria');
                        }
                    }
                });
            }
        });
    });

    // Carregar categorias quando a página carrega
    carregarCategorias();
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
                <div class="col-md-4 col-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-primary">
                                    <i class="ti ti-category ti-28"></i>
                                </span>
                            </div>
                            <div>
                                <p class="mb-0 text-muted small">Total de Categorias</p>
                                <h4 class="mb-0" id="totalCategorias">0</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-success">
                                    <i class="ti ti-check ti-28"></i>
                                </span>
                            </div>
                            <div>
                                <p class="mb-0 text-muted small">Categorias Ativas</p>
                                <h4 class="mb-0" id="categoriasAtivas">0</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-warning">
                                    <i class="ti ti-x ti-28"></i>
                                </span>
                            </div>
                            <div>
                                <p class="mb-0 text-muted small">Categorias Inativas</p>
                                <h4 class="mb-0" id="categoriasInativas">0</h4>
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
                                <input type="text" class="form-control" id="filtro_busca" placeholder="Nome da categoria...">
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

            <!-- Lista de Categorias -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="ti ti-list me-1"></i>
                        Lista de Categorias
                    </h5>
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.planos.index') }}" class="btn btn-secondary">
                            <i class="ti ti-arrow-left me-1"></i>
                            Voltar
                        </a>
                        <button type="button" class="btn btn-primary" id="btnNovaCategoria">
                            <i class="ti ti-plus me-1"></i>
                            Nova Categoria
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3"><span id="resultadoCount">0</span> resultado(s)</p>
                    
                    <div id="categoriasLoader" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>

                    <div id="categoriasContainer" style="display: none;">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50%">CATEGORIA</th>
                                        <th width="15%">STATUS</th>
                                        <th width="15%">ORDEM</th>
                                        <th width="20%" class="text-center">AÇÕES</th>
                                    </tr>
                                </thead>
                                <tbody id="categoriasTableBody">
                                    <!-- Categorias serão carregadas aqui via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="categoriasEmpty" style="display: none;" class="text-center py-5">
                        <i class="ti ti-category-off" style="font-size: 48px;" class="text-muted mb-3"></i>
                        <p class="text-muted">Nenhuma categoria encontrada</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nova Categoria -->
<div class="modal fade" id="modalNovaCategoria" tabindex="-1" aria-labelledby="modalNovaCategoriaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNovaCategoriaLabel">
                    <i class="ti ti-plus me-2"></i>
                    Nova Categoria
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formNovaCategoria">
                <div class="modal-body">
                    <div class="alert alert-info mb-4">
                        <i class="ti ti-info-circle me-1"></i>
                        <strong>Dica:</strong> Categorias organizam os módulos no menu lateral. Defina uma ordem para controlar a sequência de exibição.
                    </div>

                    <div id="categoriaFormMsg"></div>

                    @csrf
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="categoria_nome" class="form-label">Nome da Categoria <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="categoria_nome" name="nome" required placeholder="Ex: Cadastros, Financeiro">
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="categoria_ordem" class="form-label">Ordem de Exibição</label>
                            <input type="number" class="form-control" id="categoria_ordem" name="ordem" value="0" min="0" placeholder="0">
                            <small class="text-muted">Ordem de exibição no menu (0 = primeiro)</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i>
                        Salvar Categoria
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Editar Categoria -->
<div class="modal fade" id="modalEditarCategoria" tabindex="-1" aria-labelledby="modalEditarCategoriaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarCategoriaLabel">
                    <i class="ti ti-edit me-2"></i>
                    Editar Categoria
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formEditarCategoria">
                @csrf
                <input type="hidden" id="editCategoriaId" name="id">
                <div class="modal-body">
                    <div id="editCategoriaFormMsg"></div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="editCategoriaNome" class="form-label">Nome da Categoria <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editCategoriaNome" name="nome" required placeholder="Ex: Cadastros, Financeiro">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="editCategoriaOrdem" class="form-label">Ordem de Exibição</label>
                            <input type="number" class="form-control" id="editCategoriaOrdem" name="ordem" value="0" min="0" placeholder="0">
                            <small class="text-muted">Ordem de exibição no menu</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="editCategoriaAtivo" class="form-label">Status</label>
                            <select class="form-select" id="editCategoriaAtivo" name="ativo">
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
