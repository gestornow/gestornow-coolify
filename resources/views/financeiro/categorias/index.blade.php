@extends('layouts.layoutMaster')

@section('title', 'Categorias')

@php
    $podeGerenciarCategorias = \Perm::pode(auth()->user(), 'financeiro.categorias');
@endphp

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Categorias Financeiras</h5>
                    @if($podeGerenciarCategorias)
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCategoria">
                            <i class="ti ti-plus me-1"></i> Nova Categoria
                        </button>
                    @endif
                </div>
            </div>

            <!-- Tabs para Tipo de Categoria -->
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-pills mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#despesa" aria-controls="despesa" aria-selected="true" onclick="carregarCategorias('despesa')">
                                <i class="ti ti-credit-card me-1"></i> Despesas
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#receita" aria-controls="receita" aria-selected="false" onclick="carregarCategorias('receita')">
                                <i class="ti ti-cash me-1"></i> Receitas
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content p-0">
                        <!-- Tab Despesa -->
                        <div class="tab-pane fade show active" id="despesa" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover" id="table-despesa">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>Descrição</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-despesa">
                                        <tr>
                                            <td colspan="3" class="text-center">
                                                <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                    <span class="visually-hidden">Carregando...</span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Tab Receita -->
                        <div class="tab-pane fade" id="receita" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover" id="table-receita">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>Descrição</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-receita">
                                        <tr>
                                            <td colspan="3" class="text-center">
                                                <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                    <span class="visually-hidden">Carregando...</span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nova/Editar Categoria -->
<div class="modal fade" id="modalCategoria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCategoriaLabel">Nova Categoria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formCategoria">
                <div class="modal-body">
                    <input type="hidden" id="categoria_id" name="id_categoria_contas">
                    
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nome" name="nome" required>
                    </div>

                    <div class="mb-3">
                        <label for="tipo" class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select class="form-select" id="tipo" name="tipo" required>
                            <option value="despesa">Despesas</option>
                            <option value="receita">Receitas</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
    const podeGerenciarCategorias = @json($podeGerenciarCategorias);
    let tipoAtual = 'despesa';

    document.addEventListener('DOMContentLoaded', function() {
        carregarCategorias('despesa');
        
        // Submit do formulário
        document.getElementById('formCategoria').addEventListener('submit', function(e) {
            e.preventDefault();
            salvarCategoria();
        });

        // Resetar formulário ao fechar o modal
        const modalCategoria = document.getElementById('modalCategoria');
        modalCategoria.addEventListener('hidden.bs.modal', function () {
            document.getElementById('formCategoria').reset();
            document.getElementById('categoria_id').value = '';
            document.getElementById('modalCategoriaLabel').textContent = 'Nova Categoria';
        });
    });

    function carregarCategorias(tipo) {
        tipoAtual = tipo;
        const tbody = document.getElementById(`tbody-${tipo}`);
        
        tbody.innerHTML = `
            <tr>
                <td colspan="3" class="text-center">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </td>
            </tr>
        `;

        // Chama a API list ao invés do index
        fetch(`/financeiro/categorias/${tipo}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderizarCategorias(data.data, tipo);
            } else {
                mostrarErro('Erro ao carregar categorias');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarErro('Erro ao carregar categorias');
        });
    }

    function renderizarCategorias(categorias, tipo) {
        const tbody = document.getElementById(`tbody-${tipo}`);
        
        if (categorias.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center text-muted">
                        <i class="ti ti-folder-x mb-2" style="font-size: 3rem;"></i>
                        <p>Nenhuma categoria cadastrada</p>
                        <small>Clique em "Nova Categoria" para adicionar</small>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = categorias.map(cat => `
            <tr>
                <td><strong>${cat.nome}</strong></td>
                <td>${cat.descricao || '-'}</td>
                <td>
                    ${podeGerenciarCategorias ? `
                    <button class="btn btn-sm btn-icon btn-label-primary" onclick="editarCategoria(${cat.id_categoria_contas})" title="Editar">
                        <i class="ti ti-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-icon btn-label-danger" onclick="deletarCategoria(${cat.id_categoria_contas}, '${cat.nome}')" title="Excluir">
                        <i class="ti ti-trash"></i>
                    </button>
                    ` : '<span class="text-muted">Sem ações</span>'}
                </td>
            </tr>
        `).join('');
    }

    function salvarCategoria() {
        const form = document.getElementById('formCategoria');
        const formData = new FormData(form);
        const categoriaId = formData.get('id_categoria_contas');
        
        const data = {
            nome: formData.get('nome'),
            tipo: formData.get('tipo'),
            descricao: formData.get('descricao')
        };

        console.log('Dados sendo enviados:', data);

        const url = categoriaId ? `/financeiro/categoria/${categoriaId}` : '/financeiro/categorias';
        const method = categoriaId ? 'PUT' : 'POST';

        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalCategoria')).hide();
                form.reset();
                
                // Ativar a aba correspondente ao tipo e carregar os dados
                const tipo = data.tipo;
                const tabButton = document.querySelector(`[data-bs-target="#${tipo}"]`);
                if (tabButton) {
                    const tab = new bootstrap.Tab(tabButton);
                    tab.show();
                }
                carregarCategorias(tipo);
                
                mostrarSucesso(result.message || 'Categoria salva com sucesso!');
            } else {
                console.error('Erro detalhado:', result);
                const errorMsg = result.error ? `${result.message}\n\nDetalhes: ${result.error}` : result.message;
                mostrarErro(errorMsg || 'Erro ao salvar categoria');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarErro('Erro ao salvar categoria');
        });
    }

    function editarCategoria(id) {
        fetch(`/financeiro/categoria/${id}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const categoria = data.data;
                
                // Preencher o formulário
                document.getElementById('categoria_id').value = categoria.id_categoria_contas;
                document.getElementById('nome').value = categoria.nome;
                document.getElementById('tipo').value = categoria.tipo;
                document.getElementById('descricao').value = categoria.descricao || '';
                
                // Atualizar título do modal
                document.getElementById('modalCategoriaLabel').textContent = 'Editar Categoria';
                
                // Abrir o modal
                new bootstrap.Modal(document.getElementById('modalCategoria')).show();
            } else {
                mostrarErro('Erro ao carregar categoria');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarErro('Erro ao carregar categoria');
        });
    }

    function deletarCategoria(id, nome) {
        Swal.fire({
            title: 'Confirmar exclusão',
            text: `Deseja realmente excluir a categoria "${nome}"?`,
            icon: 'warning',
            showCancelButton: true,
            showDenyButton: false,
            showCloseButton: false,
            confirmButtonColor: '#696cff',
            cancelButtonColor: '#8592a3',
            confirmButtonText: '<i class="ti ti-check me-1"></i>Sim, excluir!',
            cancelButtonText: '<i class="ti ti-x me-1"></i>Cancelar',
            customClass: {
                confirmButton: 'btn btn-primary me-3',
                cancelButton: 'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`/financeiro/categoria/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        carregarCategorias(tipoAtual);
                        mostrarSucesso(data.message || 'Categoria excluída com sucesso!');
                    } else {
                        mostrarErro(data.message || 'Erro ao excluir categoria');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    mostrarErro('Erro ao excluir categoria');
                });
            }
        });
    }

    function mostrarSucesso(mensagem) {
        Swal.fire({
            icon: 'success',
            title: 'Sucesso!',
            text: mensagem,
            showConfirmButton: true,
            showCancelButton: false,
            showDenyButton: false,
            confirmButtonText: 'OK',
            timer: 2000,
            timerProgressBar: true,
            customClass: {
                confirmButton: 'btn btn-primary'
            },
            buttonsStyling: false
        });
    }

    function mostrarErro(mensagem) {
        Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: mensagem,
            showConfirmButton: true,
            showCancelButton: false,
            showDenyButton: false,
            confirmButtonText: 'OK',
            customClass: {
                confirmButton: 'btn btn-primary'
            },
            buttonsStyling: false
        });
    }
</script>
@endsection
