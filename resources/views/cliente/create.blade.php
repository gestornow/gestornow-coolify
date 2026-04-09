@extends('layouts.layoutMaster')

@section('title', 'Cadastrar Novo Cliente')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <!-- Hidden fields para upload (serão preenchidos após criar o cliente) -->
    <input type="hidden" id="clienteId" value="">
    <input type="hidden" id="empresaId" value="{{ $filters['id_empresa'] ?? Auth::user()->id_empresa ?? '' }}">
    <input type="hidden" id="fotoFilename" value="">
    
    <div class="row">
        <div class="col-12">
            <!-- Cabeçalho -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Cadastrar Novo Cliente</h4>
                    <p class="text-muted mb-0">Preencha os dados do cliente</p>
                </div>
                <a href="{{ route('clientes.index') }}" class="btn btn-outline-secondary">
                    <i class="ti ti-arrow-left me-1"></i>
                    Voltar
                </a>
            </div>

            <!-- Formulário -->
            <div class="row">
                <div class="col-12">
                    <form id="clienteCreateForm" action="{{ route('clientes.store') }}" method="POST">
                        @csrf

                        {{-- Arquivos (padronizado com Edit) --}}
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Arquivos</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info mb-0">
                                    Salve o cliente primeiro para liberar o upload de foto e anexos.
                                </div>
                                <div class="row g-4 mt-1">
                                    <div class="col-12 col-lg-5">
                                        <h6 class="mb-3"><i class="ti ti-camera me-2"></i>Foto do Cliente</h6>
                                        <div class="d-flex flex-column flex-sm-row align-items-center align-items-sm-start gap-3">
                                            <div id="fotoPreview" style="display: flex; justify-content: center; align-items: center;">
                                                <div class="avatar avatar-xl">
                                                    <span class="avatar-initial rounded-circle bg-label-primary fs-1">CL</span>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 w-100">
                                                <div class="mb-2">
                                                    <label class="form-label small" for="fotoUpload">Selecionar Foto</label>
                                                    <input type="file" class="form-control" id="fotoUpload" disabled>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-primary" disabled>
                                                    <i class="ti ti-upload me-1"></i> Upload Foto
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 col-lg-7">
                                        <h6 class="mb-3"><i class="ti ti-paperclip me-2"></i>Anexos (Documentos)</h6>
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label small" for="anexoUpload">Adicionar Anexo</label>
                                                <input type="file" class="form-control" id="anexoUpload" disabled>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small" for="nomeAnexo">Nome do Documento</label>
                                                <input type="text" class="form-control" id="nomeAnexo" placeholder="Ex: Contrato, Comprovante, etc" disabled>
                                            </div>
                                            <div class="col-12 d-flex flex-wrap gap-2">
                                                <button type="button" class="btn btn-sm btn-primary" disabled>
                                                    <i class="ti ti-upload me-1"></i> Upload Anexo
                                                </button>
                                                <button type="button" class="btn btn-sm btn-info" disabled>
                                                    <i class="ti ti-refresh me-1"></i> Atualizar Lista
                                                </button>
                                            </div>
                                            <div class="col-12">
                                                <div id="listaAnexos" class="mt-2">
                                                    <p class="text-muted small mb-0">Uploads disponíveis após salvar.</p>
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
                                    <input type="hidden" name="id_empresa" value="{{ $filters['id_empresa'] ?? Auth::user()->id_empresa ?? '' }}">

                                    <div class="col-md-6">
                                        <label class="form-label" for="nome">Nome / Razão Social <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="nome" name="nome" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label" for="razao_social">Nome Fantasia</label>
                                        <input type="text" class="form-control" id="razao_social" name="razao_social">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label" for="id_tipo_pessoa">Tipo de Pessoa <span class="text-danger">*</span></label>
                                        <select class="form-select" id="id_tipo_pessoa" name="id_tipo_pessoa" required>
                                            <option value="1">Pessoa Física</option>
                                            <option value="2">Pessoa Jurídica</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4" id="campo-cpf">
                                        <label class="form-label" for="cpf_cnpj">CPF <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="cpf_cnpj" name="cpf_cnpj" data-mask="000.000.000-00" required>
                                    </div>

                                    <div class="col-md-4" id="campo-cnpj" style="display: none;">
                                        <label class="form-label" for="cnpj_input">CNPJ <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="cnpj_input" data-mask="00.000.000/0000-00">
                                            <button type="button" class="btn btn-outline-primary" id="btnConsultarCNPJ">
                                                <i class="ti ti-search me-1"></i>Consultar
                                            </button>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label" for="rg_ie">RG/IE</label>
                                        <input type="text" class="form-control" id="rg_ie" name="rg_ie">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label" for="email">Email</label>
                                        <input type="email" class="form-control" id="email" name="email">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label" for="telefone">Telefone</label>
                                        <input type="text" class="form-control" id="telefone" name="telefone" data-mask="(00) 00000-0000">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label" for="data_nascimento">Data de Nascimento</label>
                                        <input type="date" class="form-control" id="data_nascimento" name="data_nascimento">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label" for="status">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="ativo" selected>Ativo</option>
                                            <option value="inativo">Inativo</option>
                                            <option value="bloqueado">Bloqueado</option>
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
                                        <input type="text" class="form-control" id="cep" name="cep" data-mask="00000-000">
                                    </div>

                                    <div class="col-md-7">
                                        <label class="form-label" for="endereco">Endereço</label>
                                        <input type="text" class="form-control" id="endereco" name="endereco">
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label" for="numero">Número</label>
                                        <input type="text" class="form-control" id="numero" name="numero">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label" for="complemento">Complemento</label>
                                        <input type="text" class="form-control" id="complemento" name="complemento">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label" for="bairro">Bairro</label>
                                        <input type="text" class="form-control" id="bairro" name="bairro">
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label" for="uf">UF</label>
                                        <select class="form-select" id="uf" name="uf">
                                            <option value="">Selecione</option>
                                            @foreach(($ufs ?? []) as $item)
                                                <option value="{{ $item['uf'] }}" {{ old('uf') === $item['uf'] ? 'selected' : '' }}>{{ $item['uf'] }} - {{ $item['nome'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-9">
                                        <label class="form-label" for="cidade">Cidade</label>
                                        <select class="form-select" id="cidade" name="cidade" data-selected="{{ old('cidade') }}">
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
                                        <input type="text" class="form-control" id="cep_entrega" name="cep_entrega" data-mask="00000-000">
                                    </div>

                                    <div class="col-md-7">
                                        <label class="form-label" for="endereco_entrega">Endereço</label>
                                        <input type="text" class="form-control" id="endereco_entrega" name="endereco_entrega">
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label" for="numero_entrega">Número</label>
                                        <input type="text" class="form-control" id="numero_entrega" name="numero_entrega">
                                    </div>

                                    <div class="col-md-12">
                                        <label class="form-label" for="complemento_entrega">Complemento</label>
                                        <input type="text" class="form-control" id="complemento_entrega" name="complemento_entrega">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botões de Ação -->
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('clientes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti ti-check me-1"></i>Salvar Cliente
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
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
<script src="{{asset('assets/js/utils.js')}}"></script>
<script src="{{asset('assets/js/clientes-create.js')}}?v=20260330002"></script>
<script>
// Funcionalidade específica da página de criação
document.addEventListener('DOMContentLoaded', function() {
    const cidadesEndpoint = "{{ route('clientes.localidades.cidades') }}";

    // Aplicar máscaras
    $('#telefone').mask('(00) 00000-0000');
    $('#cep').mask('00000-000');
    $('#cep_entrega').mask('00000-000');
    
    // Máscara dinâmica para CPF/CNPJ
    const cpfCnpjInput = $('#cpf_cnpj');
    const tipoPessoaSelect = $('#id_tipo_pessoa');
    
    function updateCpfCnpjMask() {
        if (tipoPessoaSelect.val() == '1') {
            // Pessoa Física - CPF
            cpfCnpjInput.mask('000.000.000-00');
            cpfCnpjInput.attr('placeholder', '000.000.000-00');
        } else {
            // Pessoa Jurídica - CNPJ
            cpfCnpjInput.mask('00.000.000/0000-00');
            cpfCnpjInput.attr('placeholder', '00.000.000/0000-00');
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
            text: 'Endereço principal copiado para endereço de entrega.',
            timer: 1500,
            showConfirmButton: false
        });
    });
    
    function aplicarDadosCep(data, suffix) {
        if (!data || data.erro) {
            return;
        }

        $(`#endereco${suffix}`).val(data.logradouro || '');

        if (!suffix) {
            $('#bairro').val(data.bairro || '');

            if (data.uf) {
                $('#uf').val(String(data.uf).toUpperCase());
                carregarCidades(String(data.uf).toUpperCase(), data.localidade || '');
            }
        }

        $(`#numero${suffix}`).focus();
    }
    
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

    async function buscarCep(cep, suffix) {
        try {
            if (window.utils && typeof window.utils.lookupCEP === 'function') {
                const dataUtils = await window.utils.lookupCEP(cep);
                aplicarDadosCep({
                    logradouro: dataUtils.endereco,
                    bairro: dataUtils.bairro,
                    localidade: dataUtils.localidade,
                    uf: dataUtils.uf,
                }, suffix);
                return;
            }
        } catch (e) {
            // fallback para endpoint direto abaixo
        }

        $.ajax({
            url: `https://viacep.com.br/ws/${cep}/json/`,
            dataType: 'json',
            success: function(data) {
                aplicarDadosCep(data, suffix);
            }
        });
    }

    function bindBuscaCep($input, suffix) {
        const consultar = function() {
            const cep = $input.val().replace(/\D/g, '');

            if (cep.length !== 8) {
                return;
            }

            if ($input.data('ultimoCepConsultado') === cep) {
                return;
            }

            $input.data('ultimoCepConsultado', cep);
            buscarCep(cep, suffix);
        };

        $input.on('blur', consultar);
        $input.on('change', consultar);
        $input.on('keyup', function() {
            if ($(this).val().replace(/\D/g, '').length === 8) {
                consultar();
            }
        });
    }

    bindBuscaCep($('#cep'), '');
    bindBuscaCep($('#cep_entrega'), '_entrega');

    $('#uf').on('change', function() {
        carregarCidades($(this).val(), '');
    });

    const ufInicial = $('#uf').val();
    const cidadeInicial = $('#cidade').data('selected') || '';
    if (ufInicial) {
        carregarCidades(ufInicial, cidadeInicial);
    }
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

@if($errors->any())
<script>
$(document).ready(function() {
    Swal.fire({
        icon: 'error',
        title: 'Não foi possível salvar o cliente',
        html: '{!! collect($errors->all())->map(fn($error) => e($error))->implode("<br>") !!}',
        confirmButtonText: 'OK'
    });
});
</script>
@endif
@endsection
