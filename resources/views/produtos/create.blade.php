@extends('layouts.layoutMaster')

@section('title', 'Novo Produto')

@section('page-style')
<style>
    .produto-tabs-nav {
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        gap: .4rem;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        padding-bottom: .2rem;
    }

    .produto-tabs-nav .nav-item {
        flex: 0 0 auto;
    }

    .produto-tabs-nav .nav-link {
        white-space: nowrap;
    }

    .produto-photo-card {
        background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
        border: 1px solid #667eea30;
    }

    @media (max-width: 767.98px) {
        .produto-tab-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .produto-tab-actions .btn {
            width: 100%;
        }
    }

    html.dark-style .produto-photo-card {
        background: linear-gradient(135deg, #2b3046 0%, #25293c 100%);
        border-color: #444b6e;
    }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 pt-1">
    <div class="row">
        <div class="col-md-12">
            <!-- Card das Tabs -->
            <div class="card mb-4">
                <div class="card-body py-3">
                    <ul class="nav nav-pills produto-tabs-nav" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" href="javascript:void(0);" data-bs-toggle="pill" data-bs-target="#tab-dados-gerais">
                                <i class="ti ti-file-description me-1"></i> Dados Gerais
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="javascript:void(0);" data-bs-toggle="pill" data-bs-target="#tab-patrimonios">
                                <i class="ti ti-building-warehouse me-1"></i> Patrimônios
                                <span class="badge bg-label-primary ms-1">0</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="javascript:void(0);" data-bs-toggle="pill" data-bs-target="#tab-precos">
                                <i class="ti ti-currency-dollar me-1"></i> Tabela de Preços
                                <span class="badge bg-label-success ms-1">0</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="javascript:void(0);" data-bs-toggle="pill" data-bs-target="#tab-manutencoes">
                                <i class="ti ti-tool me-1"></i> Manutenções
                                <span class="badge bg-label-warning ms-1">0</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="javascript:void(0);" data-bs-toggle="pill" data-bs-target="#tab-estoque">
                                <i class="ti ti-packages me-1"></i> Estoque
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="javascript:void(0);" data-bs-toggle="pill" data-bs-target="#tab-anexos">
                                <i class="ti ti-paperclip me-1"></i> Anexos
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Card do Conteúdo -->
            <div class="card">
                <div class="card-body">
                    <div class="tab-content">
                        <!-- Tab: Dados Gerais -->
                        <div class="tab-pane fade show active" id="tab-dados-gerais" role="tabpanel">
                                @if($errors->any())
                                    <div class="alert alert-danger">
                                        <ul class="mb-0">
                                            @foreach($errors->all() as $err)
                                                <li>{{ $err }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <form id="produtoCreateForm" action="{{ route('produtos.store') }}" method="POST">
                                    @csrf
                                    
                                    <div class="row g-3">
                                        <!-- Card de Foto do Produto -->
                                        <div class="col-12">
                                            <div class="card produto-photo-card">
                                                <div class="card-body">
                                                    <h6 class="card-title mb-3">
                                                        <i class="ti ti-camera me-2"></i>
                                                        Foto do Produto
                                                    </h6>
                                                    <div class="row align-items-center">
                                                        <div class="col-md-2 text-center">
                                                            <div id="fotoPreview" class="mb-3 mb-md-0">
                                                                <div class="avatar avatar-xl">
                                                                    <span class="avatar-initial rounded bg-label-primary fs-1">
                                                                        <i class="ti ti-package"></i>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-10">
                                                            <div class="mb-0">
                                                                <label for="fotoUpload" class="form-label small">Selecionar Foto</label>
                                                                <input type="file" class="form-control" id="fotoUpload" accept="image/jpeg,image/jpg,image/png,image/webp">
                                                                <small class="form-text text-muted">
                                                                    Formatos: JPG, PNG, WEBP. Tamanho máximo: 10MB
                                                                </small>
                                                            </div>
                                                            <div class="mt-2">
                                                                <button type="button" class="btn btn-sm btn-outline-danger d-none" id="btnRemoverFotoTemp">
                                                                    <i class="ti ti-x me-1"></i> Remover
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label small">Nome <span class="text-danger">*</span></label>
                                            <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}" required>
                                            @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small">Código</label>
                                            <input type="text" name="codigo" class="form-control" value="{{ old('codigo') }}">
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small">Número de Série</label>
                                            <input type="text" name="numero_serie" class="form-control" value="{{ old('numero_serie') }}">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label small">Descrição</label>
                                            <textarea name="descricao" class="form-control" rows="3">{{ old('descricao') }}</textarea>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label small">Detalhes</label>
                                            <textarea name="detalhes" class="form-control" rows="3">{{ old('detalhes') }}</textarea>
                                        </div>

                                        <!-- Separador Preços -->
                                        <div class="col-12">
                                            <hr class="my-2">
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small">Preço de Custo</label>
                                            <div class="input-group">
                                                <span class="input-group-text">R$</span>
                                                <input type="text" name="preco_custo" class="form-control mask-money" value="{{ old('preco_custo', '0,00') }}">
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small">Preço de Venda</label>
                                            <div class="input-group">
                                                <span class="input-group-text">R$</span>
                                                <input type="text" name="preco_venda" class="form-control mask-money" value="{{ old('preco_venda', '0,00') }}">
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small">Preço de Locação</label>
                                            <div class="input-group">
                                                <span class="input-group-text">R$</span>
                                                <input type="text" name="preco_locacao" class="form-control mask-money" value="{{ old('preco_locacao', '0,00') }}">
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small">Status</label>
                                            <select name="status" class="form-select">
                                                <option value="ativo" {{ old('status', 'ativo') === 'ativo' ? 'selected' : '' }}>Ativo</option>
                                                <option value="inativo" {{ old('status') === 'inativo' ? 'selected' : '' }}>Inativo</option>
                                            </select>
                                        </div>

                                        <!-- Separador Dimensões -->
                                        <div class="col-12">
                                            <hr class="my-2">
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small">Altura (cm)</label>
                                            <input type="text" name="altura" class="form-control mask-decimal" value="{{ old('altura', '0,00') }}">
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small">Largura (cm)</label>
                                            <input type="text" name="largura" class="form-control mask-decimal" value="{{ old('largura', '0,00') }}">
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small">Profundidade (cm)</label>
                                            <input type="text" name="profundidade" class="form-control mask-decimal" value="{{ old('profundidade', '0,00') }}">
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small">Peso (kg)</label>
                                            <input type="text" name="peso" class="form-control mask-decimal" value="{{ old('peso', '0,00') }}">
                                        </div>

                                        <!-- Botões -->
                                        <div class="col-12 mt-4 d-flex gap-2 produto-tab-actions">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="ti ti-check me-1"></i> Salvar
                                            </button>
                                            <a href="{{ route('produtos.index') }}" class="btn btn-secondary">Cancelar</a>
                                        </div>
                                    </div>
                                </form>
                        </div>

                        <!-- Tab: Patrimônios -->
                        <div class="tab-pane fade" id="tab-patrimonios" role="tabpanel">
                            <div class="alert alert-info mb-0">
                                <i class="ti ti-info-circle me-2"></i>
                                <strong>Salve o produto primeiro</strong> para poder adicionar patrimônios.
                            </div>
                        </div>

                        <!-- Tab: Tabela de Preços -->
                        <div class="tab-pane fade" id="tab-precos" role="tabpanel">
                            <div class="alert alert-info mb-0">
                                <i class="ti ti-info-circle me-2"></i>
                                <strong>Salve o produto primeiro</strong> para poder adicionar tabelas de preços.
                            </div>
                        </div>

                        <!-- Tab: Manutenções -->
                        <div class="tab-pane fade" id="tab-manutencoes" role="tabpanel">
                            <div class="alert alert-info mb-0">
                                <i class="ti ti-info-circle me-2"></i>
                                <strong>Salve o produto primeiro</strong> para poder registrar manutenções.
                            </div>
                        </div>

                        <!-- Tab: Estoque -->
                        <div class="tab-pane fade" id="tab-estoque" role="tabpanel">
                            <div class="alert alert-info mb-0">
                                <i class="ti ti-info-circle me-2"></i>
                                <strong>Salve o produto primeiro</strong> para poder gerenciar o estoque.
                            </div>
                        </div>

                        <!-- Tab: Anexos -->
                        <div class="tab-pane fade" id="tab-anexos" role="tabpanel">
                            <div class="alert alert-info mb-0">
                                <i class="ti ti-info-circle me-2"></i>
                                <strong>Salve o produto primeiro</strong> para poder adicionar anexos.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
    // Máscaras
    $('.mask-money').mask('#.##0,00', {reverse: true});
    $('.mask-decimal').mask('#.##0,00', {reverse: true});

    function getCsrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').first().val();
    }

    function updateCsrfToken(token) {
        if (!token) {
            return;
        }

        $('meta[name="csrf-token"]').attr('content', token);
        $('input[name="_token"]').val(token);
    }

    function refreshCsrfToken(callback, onFail, tentativa = 1) {
        $.ajax({
            url: '{{ route("csrf-token") }}',
            method: 'GET',
            cache: false,
            timeout: 5000,
            success: function(response) {
                var token = response.token || response._csrf_token;
                updateCsrfToken(token);
                if (typeof callback === 'function') {
                    callback();
                }
            },
            error: function() {
                if (tentativa < 3) {
                    setTimeout(function() {
                        refreshCsrfToken(callback, onFail, tentativa + 1);
                    }, 300);
                    return;
                }

                if (typeof onFail === 'function') {
                    onFail();
                }
            }
        });
    }

    // Configuração da API
    const API_BASE_URL = 'https://api.gestornow.com';
    const API_PRODUTOS_FOTO = `${API_BASE_URL}/api/produtos/imagens`;

    function normalizarUrlFotoProduto(url, filename, idEmpresa) {
        if (!url && filename) {
            return `${API_BASE_URL}/uploads/produtos/imagens/${idEmpresa}/${filename}`;
        }

        if (!url) {
            return null;
        }

        let finalUrl = String(url).trim();
        if (!finalUrl.startsWith('http')) {
            finalUrl = API_BASE_URL + '/' + finalUrl.replace(/^\//, '');
        }

        finalUrl = finalUrl.replace('/api/produtos/imagens/', '/uploads/produtos/imagens/');
        finalUrl = finalUrl.replace('/produtos/imagens/', '/uploads/produtos/imagens/');

        return finalUrl;
    }
    
    // Armazenar foto temporariamente
    let fotoTemporaria = null;

    // Preview da foto ao selecionar
    $('#fotoUpload').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Validar tamanho (10MB)
            if (file.size > 10 * 1024 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'Arquivo muito grande',
                    text: 'A foto deve ter no máximo 10MB',
                    confirmButtonText: 'OK'
                });
                $(this).val('');
                fotoTemporaria = null;
                return;
            }

            // Validar tipo
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Formato inválido',
                    text: 'Use apenas JPG, PNG ou WEBP',
                    confirmButtonText: 'OK'
                });
                $(this).val('');
                fotoTemporaria = null;
                return;
            }

            // Armazenar foto temporariamente
            fotoTemporaria = file;

            // Preview
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#fotoPreview').html(`
                    <img src="${e.target.result}" alt="Preview" 
                         class="rounded" 
                         style="width: 80px; height: 80px; object-fit: cover;">
                `);
                $('#btnRemoverFotoTemp').removeClass('d-none');
            };
            reader.readAsDataURL(file);
        }
    });

    // Remover foto temporária
    $('#btnRemoverFotoTemp').on('click', function() {
        fotoTemporaria = null;
        $('#fotoUpload').val('');
        $('#fotoPreview').html(`
            <div class="avatar avatar-xl">
                <span class="avatar-initial rounded bg-label-primary fs-1">
                    <i class="ti ti-package"></i>
                </span>
            </div>
        `);
        $(this).addClass('d-none');
    });

    // Submit do formulário
    $('#produtoCreateForm').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        
        const nome = $form.find('input[name="nome"]').val();
        
        if (!nome) {
            Swal.fire({
                icon: 'warning',
                title: 'Atenção!',
                text: 'O nome do produto é obrigatório.',
                confirmButtonText: 'OK'
            });
            return false;
        }
        
        $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Salvando...');
        
        const enviarCriacaoProduto = function(tentativa) {
            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: $form.serialize(),
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json'
                },
                success: function(response) {
                    console.log('✓ Produto criado:', response);
                    
                    if (fotoTemporaria && response.id_produto) {
                        uploadFotoAposCriacao(response.id_produto, fotoTemporaria);
                    } else {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: 'Produto cadastrado com sucesso.',
                            confirmButtonText: 'OK',
                            timer: 2000,
                            timerProgressBar: true
                        }).then(() => {
                            window.location.href = '{{ route('produtos.index') }}';
                        });
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 419 && tentativa < 2) {
                        refreshCsrfToken(function() {
                            enviarCriacaoProduto(tentativa + 1);
                        });
                        return;
                    }

                    console.error('✗ Erro ao criar produto:', xhr);
                    $submitBtn.prop('disabled', false).html('<i class="ti ti-check me-1"></i> Salvar');
                    
                    let mensagem = 'Erro ao salvar produto.';
                    if (xhr.status === 401 || xhr.status === 419) {
                        mensagem = 'Faça login para continuar.';
                    } else if (xhr.responseJSON?.message) {
                        mensagem = xhr.responseJSON.message;
                    } else if (xhr.responseJSON?.errors) {
                        mensagem = Object.values(xhr.responseJSON.errors).flat().join('\n');
                    }
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: mensagem,
                        confirmButtonText: 'OK'
                    });
                }
            });
        };

        refreshCsrfToken(
            function() {
                enviarCriacaoProduto(1);
            },
            function() {
                $submitBtn.prop('disabled', false).html('<i class="ti ti-check me-1"></i> Salvar');
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Não foi possível renovar a sessão automaticamente. Atualize a página e tente novamente.',
                    confirmButtonText: 'OK'
                });
            }
        );
        
        return false;
    });

    // Função para upload da foto após criação do produto
    function uploadFotoAposCriacao(idProduto, file) {
        const idEmpresa = '{{ session('id_empresa') }}';
        
        // Preparar FormData
        const formData = new FormData();
        formData.append('file', file);
        formData.append('idEmpresa', idEmpresa);
        formData.append('idProduto', idProduto);
        formData.append('nomeImagemProduto', file.name.split('.')[0]);

        // Upload via AJAX para API Node.js
        $.ajax({
            url: API_PRODUTOS_FOTO,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('✓ Foto enviada:', response);
                
                // Pegar dados da resposta
                let fotoUrl = response.data?.file?.url;
                let filename = response.data?.file?.filename;

                fotoUrl = normalizarUrlFotoProduto(fotoUrl, filename, idEmpresa);
                
                // Atualizar foto_url no banco via Laravel
                $.ajax({
                    url: `/produtos/${idProduto}`,
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'Accept': 'application/json'
                    },
                    data: {
                        foto_url: fotoUrl,
                        foto_filename: filename
                    },
                    complete: function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: 'Produto cadastrado com sucesso.',
                            confirmButtonText: 'OK',
                            timer: 2000,
                            timerProgressBar: true
                        }).then(() => {
                            window.location.href = '{{ route('produtos.index') }}';
                        });
                    }
                });
            },
            error: function(xhr) {
                console.error('✗ Erro ao enviar foto:', xhr);
                // Mesmo com erro na foto, produto foi criado
                Swal.fire({
                    icon: 'warning',
                    title: 'Produto salvo!',
                    text: 'O produto foi criado, mas houve um erro ao enviar a foto.',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = '{{ route('produtos.index') }}';
                });
            }
        });
    }
});
</script>

@if(session('error'))
<script>
$(document).ready(function() {
    Swal.fire({
        icon: 'error',
        title: 'Erro!',
        text: '{{ session('error') }}',
        confirmButtonText: 'OK'
    });
});
</script>
@endif
@endsection
