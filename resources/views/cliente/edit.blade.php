@extends('layouts.layoutMaster')

@section('title', 'Editar Cliente - ' . ($cliente->nome ?? 'Cliente'))

@push('head')
<meta name="id_cliente" content="{{ $cliente->id_clientes }}">
<meta name="id_empresa" content="{{ $cliente->id_empresa }}">
<meta name="foto-filename" content="{{ $cliente->foto ?? '' }}">
<meta name="cliente_nome" content="{{ $cliente->nome ?? '' }}">
@endpush

@section('content')
<div class="container-xxl flex-grow-1">
    <input type="hidden" id="clienteId" value="{{ $cliente->id_clientes }}">
    <input type="hidden" id="empresaId" value="{{ $cliente->id_empresa }}">
    <input type="hidden" id="fotoFilename" value="{{ $cliente->foto ?? '' }}">
    <input type="hidden" id="clienteNome" value="{{ $cliente->nome ?? '' }}">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Editar Cliente</h4>
            <p class="text-muted mb-0">{{ $cliente->nome ?? 'Cliente' }}</p>
        </div>
        <a href="{{ route('clientes.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>
            Voltar
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="clienteEditForm" action="{{ route('clientes.atualizar', $cliente->id_clientes) }}" method="POST">
        @csrf
        @method('PUT')
        <input type="hidden" name="id_empresa" value="{{ old('id_empresa', $cliente->id_empresa) }}">

        {{-- Arquivos (Foto + Anexos) --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Arquivos</h5>
            </div>
            <div class="card-body">
                <div class="row g-4 align-items-start">
                    <div class="col-12 col-lg-5">
                        <h6 class="mb-3"><i class="ti ti-camera me-2"></i>Foto do Cliente</h6>

                        <div class="d-flex flex-column flex-sm-row align-items-center align-items-sm-start gap-3">
                            <div id="fotoPreview" style="display: flex; justify-content: center; align-items: center;">
                                @if(!empty($cliente->foto_url))
                                    <div style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1); flex-shrink: 0;">
                                        <img src="{{ $cliente->foto_url }}" alt="Foto do Cliente" style="width: 100%; height: 100%; object-fit: cover; display: block;" onerror="this.onerror=null; this.style.display='none';">
                                    </div>
                                @else
                                    <div class="avatar avatar-xl">
                                        <span class="avatar-initial rounded-circle bg-label-primary fs-1">{{ strtoupper(substr($cliente->nome ?? 'C', 0, 1)) }}</span>
                                    </div>
                                @endif
                            </div>

                            <div class="flex-grow-1 w-100">
                                <div class="mb-2">
                                    <label for="fotoUpload" class="form-label small">Selecionar Nova Foto</label>
                                    <input type="file" class="form-control" id="fotoUpload" accept="image/jpeg,image/jpg,image/png,image/webp">
                                    <small class="form-text text-muted">Formatos: JPG, PNG, WEBP. Tamanho máximo: 10MB</small>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-sm btn-primary" id="btnUploadFoto" disabled>
                                        <i class="ti ti-upload me-1"></i> Upload Foto
                                    </button>
                                    @if(!empty($cliente->foto))
                                        <button type="button" class="btn btn-sm btn-danger" id="btnDeletarFoto">
                                            <i class="ti ti-trash me-1"></i> Remover Foto
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-7">
                        <h6 class="mb-3"><i class="ti ti-paperclip me-2"></i>Anexos (Documentos)</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="anexoUpload" class="form-label small">Adicionar Anexo</label>
                                <input type="file" class="form-control" id="anexoUpload" accept=".pdf,.doc,.docx">
                                <small class="form-text text-muted">Formatos: PDF, DOC, DOCX. Tamanho máximo: 20MB</small>
                            </div>
                            <div class="col-12">
                                <label for="nomeAnexo" class="form-label small">Nome do Documento</label>
                                <input type="text" class="form-control" id="nomeAnexo" placeholder="Ex: Contrato, Comprovante, etc">
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-primary" id="btnUploadAnexo" disabled>
                                    <i class="ti ti-upload me-1"></i> Upload Anexo
                                </button>
                                <button type="button" class="btn btn-sm btn-info" onclick="carregarAnexos()">
                                    <i class="ti ti-refresh me-1"></i> Atualizar Lista
                                </button>
                            </div>
                            <div class="col-12">
                                <div id="listaAnexos" class="mt-2">
                                    <p class="text-muted small mb-0">Clique em "Atualizar Lista" para carregar os anexos</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dados Básicos -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Dados Básicos</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="nome">Nome / Razão Social <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nome" name="nome" value="{{ old('nome', $cliente->nome) }}" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="razao_social">Nome Fantasia</label>
                        <input type="text" class="form-control" id="razao_social" name="razao_social" value="{{ old('razao_social', $cliente->razao_social) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="id_tipo_pessoa">Tipo de Pessoa <span class="text-danger">*</span></label>
                        <select class="form-select" id="id_tipo_pessoa" name="id_tipo_pessoa" required>
                            <option value="1" {{ old('id_tipo_pessoa', $cliente->id_tipo_pessoa) == 1 ? 'selected' : '' }}>Pessoa Física</option>
                            <option value="2" {{ old('id_tipo_pessoa', $cliente->id_tipo_pessoa) == 2 ? 'selected' : '' }}>Pessoa Jurídica</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="cpf_cnpj">CPF/CNPJ</label>
                        <input type="text" class="form-control" id="cpf_cnpj" name="cpf_cnpj" value="{{ old('cpf_cnpj', $cliente->cpf_cnpj) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="rg_ie">RG/IE</label>
                        <input type="text" class="form-control" id="rg_ie" name="rg_ie" value="{{ old('rg_ie', $cliente->rg_ie) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $cliente->email) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="telefone">Telefone</label>
                        <input type="text" class="form-control" id="telefone" name="telefone" value="{{ old('telefone', $cliente->telefone) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="data_nascimento">Data de Nascimento</label>
                        <input type="date" class="form-control" id="data_nascimento" name="data_nascimento" value="{{ old('data_nascimento', $cliente->data_nascimento ? $cliente->data_nascimento->format('Y-m-d') : '') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="ativo" {{ old('status', $cliente->status) == 'ativo' ? 'selected' : '' }}>Ativo</option>
                            <option value="inativo" {{ old('status', $cliente->status) == 'inativo' ? 'selected' : '' }}>Inativo</option>
                            <option value="bloqueado" {{ old('status', $cliente->status) == 'bloqueado' ? 'selected' : '' }}>Bloqueado</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Endereço -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Endereço</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="cep">CEP</label>
                        <input type="text" class="form-control" id="cep" name="cep" value="{{ old('cep', $cliente->cep) }}">
                    </div>

                    <div class="col-md-7">
                        <label class="form-label" for="endereco">Endereço</label>
                        <input type="text" class="form-control" id="endereco" name="endereco" value="{{ old('endereco', $cliente->endereco) }}">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label" for="numero">Número</label>
                        <input type="text" class="form-control" id="numero" name="numero" value="{{ old('numero', $cliente->numero) }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="complemento">Complemento</label>
                        <input type="text" class="form-control" id="complemento" name="complemento" value="{{ old('complemento', $cliente->complemento) }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="bairro">Bairro</label>
                        <input type="text" class="form-control" id="bairro" name="bairro" value="{{ old('bairro', $cliente->bairro) }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="uf">UF</label>
                        <select class="form-select" id="uf" name="uf">
                            <option value="">Selecione</option>
                            @foreach(($ufs ?? []) as $item)
                                <option value="{{ $item['uf'] }}" {{ old('uf', $cliente->uf) === $item['uf'] ? 'selected' : '' }}>{{ $item['uf'] }} - {{ $item['nome'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-9">
                        <label class="form-label" for="cidade">Cidade</label>
                        <select class="form-select" id="cidade" name="cidade" data-selected="{{ old('cidade', $cliente->cidade) }}">
                            <option value="">Selecione a UF primeiro</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Endereço de Entrega -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Endereço de Entrega</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnCopiarEndereco">
                    <i class="ti ti-copy me-1"></i>Copiar Endereço Principal
                </button>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="cep_entrega">CEP</label>
                        <input type="text" class="form-control" id="cep_entrega" name="cep_entrega" value="{{ old('cep_entrega', $cliente->cep_entrega) }}">
                    </div>

                    <div class="col-md-7">
                        <label class="form-label" for="endereco_entrega">Endereço</label>
                        <input type="text" class="form-control" id="endereco_entrega" name="endereco_entrega" value="{{ old('endereco_entrega', $cliente->endereco_entrega) }}">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label" for="numero_entrega">Número</label>
                        <input type="text" class="form-control" id="numero_entrega" name="numero_entrega" value="{{ old('numero_entrega', $cliente->numero_entrega) }}">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label" for="complemento_entrega">Complemento</label>
                        <input type="text" class="form-control" id="complemento_entrega" name="complemento_entrega" value="{{ old('complemento_entrega', $cliente->complemento_entrega) }}">
                    </div>
                </div>
            </div>
        </div>

        <!-- Botões de Ação -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-check me-1"></i>Salvar Alterações
                        </button>
                        <a href="{{ route('clientes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                    <div>
                        @pode('clientes.excluir')
                            <button type="submit" form="deleteClienteForm" class="btn btn-danger">
                                <i class="ti ti-trash me-1"></i> Deletar Cliente
                            </button>
                        @endpode
                    </div>
                </div>
            </div>
        </div>
    </form>

    <form id="deleteClienteForm" action="{{ route('clientes.deletar', $cliente->id_clientes) }}" method="POST" class="form-delete-cliente d-none">
        @csrf
        @method('DELETE')
    </form>
</div>
@endsection

@section('vendor-script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/utils.js')}}"></script>
<script src="{{asset('assets/js/clientes-upload.js')}}?v=20260226001"></script>
<script>
$(document).ready(function() {
    const cidadesEndpoint = "{{ route('clientes.localidades.cidades') }}";

    // Aplicar máscaras
    $('#telefone').mask('(00) 00000-0000');
    $('#cep').mask('00000-000');
    $('#cep_entrega').mask('00000-000');

    // Máscara dinâmica para CPF/CNPJ
    const cpfCnpjInput = $('#cpf_cnpj');
    const tipoPessoaSelect = $('#id_tipo_pessoa');

    function updateCpfCnpjMask() {
        cpfCnpjInput.unmask();
        if (tipoPessoaSelect.val() == '1') {
            cpfCnpjInput.mask('000.000.000-00');
        } else {
            cpfCnpjInput.mask('00.000.000/0000-00');
        }
    }

    tipoPessoaSelect.on('change', updateCpfCnpjMask);
    updateCpfCnpjMask();

    // Copiar endereço principal para entrega
    $('#btnCopiarEndereco').on('click', function() {
        $('#cep_entrega').val($('#cep').val());
        $('#endereco_entrega').val($('#endereco').val());
        $('#numero_entrega').val($('#numero').val());
        $('#complemento_entrega').val($('#complemento').val());

        Swal.fire({
            icon: 'success',
            title: 'Endereço copiado!',
            timer: 1500,
            showConfirmButton: false
        });
    });

    // Buscar CEP
    $('#cep, #cep_entrega').on('blur', function() {
        const cep = $(this).val().replace(/\D/g, '');
        const isEntrega = $(this).attr('id') === 'cep_entrega';
        const suffix = isEntrega ? '_entrega' : '';

        if (cep.length === 8) {
            $.ajax({
                url: `https://viacep.com.br/ws/${cep}/json/`,
                dataType: 'json',
                success: function(data) {
                    if (!data.erro) {
                        $(`#endereco${suffix}`).val(data.logradouro);
                        if (!isEntrega) {
                            $('#bairro').val(data.bairro);
                            if (data.uf) {
                                $('#uf').val(data.uf.toUpperCase());
                                carregarCidades(data.uf.toUpperCase(), data.localidade || '');
                            }
                        }
                        $(`#numero${suffix}`).focus();
                    }
                }
            });
        }
    });

    async function carregarCidades(uf, cidadeSelecionada = '') {
        const $cidade = $('#cidade');
        $cidade.html('<option value="">Carregando...</option>');

        if (!uf) {
            $cidade.html('<option value="">Selecione a UF primeiro</option>');
            return;
        }

        try {
            const response = await $.getJSON(cidadesEndpoint, { uf });
            const items = (response && response.items) ? response.items : [];

            if (!items.length) {
                $cidade.html('<option value="">Nenhuma cidade encontrada</option>');
                return;
            }

            const options = ['<option value="">Selecione a cidade</option>']
                .concat(items.map(item => {
                    const nome = item.nome || '';
                    const selected = cidadeSelecionada && cidadeSelecionada.toLowerCase() === nome.toLowerCase() ? ' selected' : '';
                    return `<option value="${nome}"${selected}>${nome}</option>`;
                }));

            $cidade.html(options.join(''));
        } catch (error) {
            $cidade.html('<option value="">Erro ao carregar cidades</option>');
        }
    }

    $('#uf').on('change', function() {
        carregarCidades($(this).val(), '');
    });

    const ufInicial = $('#uf').val();
    const cidadeInicial = $('#cidade').data('selected') || '';
    if (ufInicial) {
        carregarCidades(ufInicial, cidadeInicial);
    }

    // Confirmação de exclusão
    $('.form-delete-cliente').on('submit', function(e) {
        e.preventDefault();
        const $form = $(this);

        Swal.fire({
            title: 'Tem certeza?',
            text: 'Esta ação não pode ser desfeita!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, deletar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $form[0].submit();
            }
        });
    });
});
</script>

@if(session('success'))
<script>
$(document).ready(function() {
    Swal.fire({
        icon: 'success',
        title: 'Sucesso!',
        text: '{{ session('success') }}',
        confirmButtonText: 'OK',
        timer: 3000,
        timerProgressBar: true
    });
});
</script>
@endif

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
