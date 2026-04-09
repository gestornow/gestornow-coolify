@extends('layouts.layoutMaster')

@section('title', 'Relatório - Contas a Pagar')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}">
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">
                <i class="ti ti-file-report me-2"></i>
                Relatório - Contas a Pagar
            </h4>
        </div>

        <div class="card-body">
            <form id="formRelatorio" method="GET" action="{{ route('financeiro.relatorios.contas-pagar.gerar') }}" target="_blank">
                <div class="row g-3">
                    <!-- Período -->
                    <div class="col-12">
                        <h6 class="text-primary">
                            <i class="ti ti-calendar me-1"></i>
                            Período
                        </h6>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label" for="data_inicio">Data Início <span class="text-danger">*</span></label>
                        <input type="date" 
                            class="form-control" 
                            id="data_inicio" 
                            name="data_inicio" 
                            value="{{ date('Y-m-01') }}"
                            required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="data_fim">Data Fim <span class="text-danger">*</span></label>
                        <input type="date" 
                            class="form-control" 
                            id="data_fim" 
                            name="data_fim" 
                            value="{{ date('Y-m-t') }}"
                            required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="tipo_data">Tipo de Date</label>
                        <select class="form-select" id="tipo_data" name="tipo_data">
                            <option value="vencimento" selected>Data de Vencimento</option>
                            <option value="emissao">Data de Emissão</option>
                            <option value="pagamento">Data de Pagamento</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Todos</option>
                            <option value="pendente">Pendente</option>
                            <option value="pago">Pago</option>
                            <option value="vencido">Vencido</option>
                            <option value="parcelado">Parcelado</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>

                    <!-- Filtros Adicionais -->
                    <div class="col-12">
                        <h6 class="text-primary">
                            <i class="ti ti-filter me-1"></i>
                            Filtros Adicionais
                        </h6>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="id_fornecedor">Fornecedor</label>
                        <select class="form-select select2" id="id_fornecedor" name="id_fornecedor">
                            <option value="">Todos os Fornecedores</option>
                            @foreach($fornecedores as $fornecedor)
                                <option value="{{ $fornecedor->id_fornecedor }}">
                                    {{ $fornecedor->razao_social ?: $fornecedor->nome_fantasia }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="id_categoria_contas">Categoria</label>
                        <select class="form-select select2" id="id_categoria_contas" name="id_categoria_contas">
                            <option value="">Todas as Categorias</option>
                            @foreach($categorias as $categoria)
                                <option value="{{ $categoria->id_categoria_contas }}">{{ $categoria->nome }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="id_bancos">Banco</label>
                        <select class="form-select select2" id="id_bancos" name="id_bancos">
                            <option value="">Todos os Bancos</option>
                            @foreach($bancos as $banco)
                                <option value="{{ $banco->id_bancos }}">{{ $banco->nome_banco }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="id_forma_pagamento">Forma de Pagamento</label>
                        <select class="form-select select2" id="id_forma_pagamento" name="id_forma_pagamento">
                            <option value="">Todas as Formas</option>
                            @foreach($formasPagamento as $forma)
                                <option value="{{ $forma->id_forma_pagamento }}">{{ $forma->nome }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="id_usuario">Responsável</label>
                        <select class="form-select select2" id="id_usuario" name="id_usuario">
                            <option value="">Todos os Usuários</option>
                            @foreach($usuarios as $usuario)
                                <option value="{{ $usuario->id_usuario }}">{{ $usuario->nome }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="documento">Documento</label>
                        <input type="text" 
                            class="form-control" 
                            id="documento" 
                            name="documento" 
                            placeholder="Buscar por número de documento">
                    </div>

                    <!-- Opções de Agrupamento -->
                    <div class="col-12">
                        <h6 class="text-primary">
                            <i class="ti ti-layout-grid me-1"></i>
                            Agrupamento e Ordenação
                        </h6>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="agrupar_por">Agrupar Por</label>
                        <select class="form-select" id="agrupar_por" name="agrupar_por">
                            <option value="">Sem Agrupamento</option>
                            <option value="fornecedor">Fornecedor</option>
                            <option value="categoria">Categoria</option>
                            <option value="status">Status</option>
                            <option value="mes">Mês</option>
                            <option value="banco">Banco</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="ordenar_por">Ordenar Por</label>
                        <select class="form-select" id="ordenar_por" name="ordenar_por">
                            <option value="data_vencimento">Data de Vencimento</option>
                            <option value="data_emissao">Data de Emissão</option>
                            <option value="valor_total">Valor</option>
                            <option value="descricao">Descrição</option>
                            <option value="fornecedor">Fornecedor</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="ordem">Ordem</label>
                        <select class="form-select" id="ordem" name="ordem">
                            <option value="asc">Crescente</option>
                            <option value="desc">Decrescente</option>
                        </select>
                    </div>

                    <!-- Botões -->
                    <div class="col-12 mt-4">
                        <div class="d-flex gap-3 justify-content-end">
                            <button type="button" class="btn btn-outline-secondary" onclick="limparFiltros()">
                                <i class="ti ti-eraser me-1"></i>
                                Limpar Filtros
                            </button>
                            <button type="button" class="btn btn-success" onclick="gerarRelatorioExcel()">
                                <i class="ti ti-file-spreadsheet me-1"></i>
                                Exportar Excel
                            </button>
                            <button type="button" class="btn btn-danger" onclick="gerarRelatorioPDF()">
                                <i class="ti ti-file-type-pdf me-1"></i>
                                Gerar PDF
                            </button>
    
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<!-- PDFKit -->
<script src="https://cdn.jsdelivr.net/npm/pdfkit@0.13.0/js/pdfkit.standalone.js"></script>
<!-- ExcelJS -->
<script src="https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js"></script>
<!-- Scripts personalizados -->
<script src="{{asset('js/relatorios-pdf.js')}}"></script>
<script src="{{asset('js/relatorios-excel.js')}}"></script>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
    // Inicializar Select2
    $('.select2').select2({
        allowClear: true,
        placeholder: 'Selecione...'
    });
});

function limparFiltros() {
    document.getElementById('formRelatorio').reset();
    $('.select2').val(null).trigger('change');
}

async function gerarRelatorioPDF() {
    const form = document.getElementById('formRelatorio');
    const formData = new FormData(form);
    
    // Mostrar loading
    Swal.fire({
        title: 'Gerando PDF...',
        text: 'Por favor aguarde',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        // Buscar dados via AJAX
        const response = await fetch("{{ route('financeiro.relatorios.contas-pagar.pdf') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: new URLSearchParams(formData)
        });
        
        if (!response.ok) {
            throw new Error('Erro ao buscar dados');
        }
        
        const dados = await response.json();
        
        // Gerar PDF usando pdfkit
        await gerarPDFContasPagar(dados);
        
        Swal.close();
        
        Swal.fire({
            icon: 'success',
            title: 'PDF gerado!',
            text: 'O download iniciará automaticamente',
            timer: 2000
        });
        
    } catch (error) {
        Swal.close();
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: 'Erro ao gerar PDF: ' + error.message
        });
    }
}

async function gerarRelatorioExcel() {
    const form = document.getElementById('formRelatorio');
    const formData = new FormData(form);
    
    // Mostrar loading
    Swal.fire({
        title: 'Gerando Excel...',
        text: 'Por favor aguarde',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        // Buscar dados via AJAX
        const response = await fetch("{{ route('financeiro.relatorios.contas-pagar.excel') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: new URLSearchParams(formData)
        });
        
        if (!response.ok) {
            throw new Error('Erro ao buscar dados');
        }
        
        const dados = await response.json();
        
        // Gerar Excel usando exceljs
        await gerarExcelContasPagar(dados);
        
        Swal.close();
        
        Swal.fire({
            icon: 'success',
            title: 'Excel gerado!',
            text: 'O download iniciará automaticamente',
            timer: 2000
        });
        
    } catch (error) {
        Swal.close();
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: 'Erro ao gerar Excel: ' + error.message
        });
    }
}
</script>
@endsection
