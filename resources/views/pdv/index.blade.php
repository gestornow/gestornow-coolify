@extends('layouts.layoutMaster')

@section('title', 'PDV - Ponto de Venda')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('page-style')
<style>
    .pdv-container {
        height: calc(100vh - 180px);
        min-height: 600px;
    }

    .pdv-carrinho {
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .pdv-itens {
        flex: 1;
        overflow-y: auto;
        max-height: calc(100vh - 450px);
    }

    .pdv-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px;
        border-bottom: 1px solid #eee;
    }

    .pdv-item:hover {
        background-color: #f5f5f5;
    }

    .pdv-item-info {
        flex: 1;
    }

    .pdv-item-nome {
        font-weight: 600;
        font-size: 0.95rem;
    }

    .pdv-item-codigo {
        font-size: 0.8rem;
        color: #888;
    }

    .pdv-item-preco {
        font-size: 0.9rem;
    }

    .pdv-item-quantidade {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .pdv-item-quantidade input {
        width: 60px;
        text-align: center;
    }

    .pdv-item-subtotal {
        font-weight: 600;
        min-width: 100px;
        text-align: right;
    }

    .pdv-totais {
        background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
        border-radius: 8px;
        padding: 15px;
        margin-top: auto;
    }

    .pdv-total-linha {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
    }

    .pdv-total-final {
        font-size: 1.5rem;
        font-weight: 700;
        color: #28a745;
        border-top: 2px solid #ddd;
        padding-top: 10px;
        margin-top: 10px;
    }

    .pdv-busca-container {
        position: relative;
    }

    .pdv-resultado-busca {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 0 0 8px 8px;
        max-height: 300px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
    }

    .pdv-resultado-item {
        padding: 10px 15px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #eee;
    }

    .pdv-resultado-item:hover {
        background-color: #f0f0f0;
    }

    .pdv-resultado-item.sem-estoque {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .carrinho-vazio {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 200px;
        color: #999;
    }

    .btn-finalizar {
        font-size: 1.2rem;
        padding: 15px;
    }

    @media (max-width: 991.98px) {
        .pdv-container {
            height: auto;
        }

        .pdv-itens {
            max-height: 300px;
        }
    }

    html.dark-style .pdv-resultado-busca {
        background: #2b3046;
        border-color: #444;
    }

    html.dark-style .pdv-resultado-item:hover {
        background-color: #363b54;
    }

    html.dark-style .pdv-item:hover {
        background-color: #363b54;
    }

    html.dark-style .pdv-totais {
        background: linear-gradient(135deg, #2b3046 0%, #25293c 100%);
    }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row pdv-container">
        <!-- Lado Esquerdo - Busca e Produtos -->
        <div class="col-lg-7 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="ti ti-shopping-cart me-2"></i>
                        PDV - Ponto de Venda
                    </h5>
                    <div class="d-flex gap-2">
                        @pode('financeiro.formas-pagamento')
                            <a href="{{ route('formas-pagamento.index') }}" class="btn btn-outline-primary btn-sm">
                                <i class="ti ti-credit-card me-1"></i> Formas Pagamento
                            </a>
                        @endpode
                        @pode('produtos-venda.gerenciar')
                            <a href="{{ route('produtos-venda.index') }}" class="btn btn-secondary btn-sm">
                                <i class="ti ti-arrow-left me-1"></i> Voltar
                            </a>
                        @endpode
                    </div>
                </div>
                <div class="card-body">
                    <!-- Campo de Busca -->
                    <div class="pdv-busca-container mb-4">
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">
                                <i class="ti ti-barcode"></i>
                            </span>
                            <input type="text" class="form-control" id="campoBusca" placeholder="Digite o código de barras ou nome do produto..." autofocus>
                            <button class="btn btn-primary" type="button" id="btnBuscar">
                                <i class="ti ti-search"></i>
                            </button>
                        </div>
                        <div class="pdv-resultado-busca" id="resultadoBusca"></div>
                        <small class="text-muted">Use o leitor de código de barras ou digite para buscar</small>
                    </div>

                    <!-- Último Produto Adicionado -->
                    <div class="alert alert-success d-none" id="alertProdutoAdicionado">
                        <i class="ti ti-check me-2"></i>
                        <span id="msgProdutoAdicionado"></span>
                    </div>

                    <!-- Instruções -->
                    <div class="text-center py-4" id="instrucoesPDV">
                        <i class="ti ti-scan ti-xl text-muted mb-3" style="font-size: 4rem;"></i>
                        <h5 class="text-muted">Leia o código de barras ou busque o produto</h5>
                        <p class="text-muted">Os produtos serão adicionados automaticamente ao carrinho</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lado Direito - Carrinho -->
        <div class="col-lg-5 mb-4">
            <div class="card h-100 pdv-carrinho">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ti ti-receipt me-2"></i>
                        Carrinho
                        <span class="badge bg-primary ms-2" id="qtdItens">0</span>
                    </h5>
                </div>
                <div class="card-body d-flex flex-column">
                    <!-- Lista de Itens -->
                    <div class="pdv-itens" id="listaItens">
                        <div class="carrinho-vazio" id="carrinhoVazio">
                            <i class="ti ti-shopping-cart-off" style="font-size: 3rem;"></i>
                            <p class="mt-2">Carrinho vazio</p>
                        </div>
                    </div>

                    <!-- Totais -->
                    <div class="pdv-totais">
                        <div class="pdv-total-linha">
                            <span>Subtotal:</span>
                            <span id="subtotal">R$ 0,00</span>
                        </div>
                        <div class="pdv-total-linha">
                            <span>Desconto:</span>
                            <div class="d-flex gap-2">
                                <div class="input-group input-group-sm" style="width: 80px;">
                                    <input type="text" class="form-control text-center" id="descontoPct" value="0" maxlength="3">
                                    <span class="input-group-text">%</span>
                                </div>
                                <div class="input-group input-group-sm" style="width: 120px;">
                                    <span class="input-group-text">R$</span>
                                    <input type="text" class="form-control money-mask" id="desconto" value="0,00">
                                </div>
                            </div>
                        </div>
                        <div class="pdv-total-linha">
                            <span>Acréscimo:</span>
                            <div class="input-group input-group-sm" style="width: 120px;">
                                <span class="input-group-text">R$</span>
                                <input type="text" class="form-control money-mask" id="acrescimo" value="0,00">
                            </div>
                        </div>
                        <div class="pdv-total-linha pdv-total-final">
                            <span>TOTAL:</span>
                            <span id="totalFinal">R$ 0,00</span>
                        </div>
                    </div>

                    <!-- Botão Finalizar -->
                    <button class="btn btn-success btn-finalizar w-100 mt-3" id="btnFinalizar" disabled>
                        <i class="ti ti-check me-2"></i>
                        FINALIZAR VENDA
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Finalizar Venda -->
<div class="modal fade" id="modalFinalizarVenda" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="ti ti-cash me-2"></i>
                    Finalizar Venda
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="text-muted mb-2">Total da Venda</h6>
                                <h2 class="text-success mb-0" id="modalTotal">R$ 0,00</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="text-muted mb-2">Quantidade de Itens</h6>
                                <h2 class="text-primary mb-0" id="modalQtdItens">0</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="formaPagamento" class="form-label">Forma de Pagamento</label>
                        <select class="form-select" id="formaPagamento">
                            @foreach($formasPagamento as $forma)
                                <option value="{{ $forma->id_forma_pagamento }}">{{ $forma->nome }}</option>
                            @endforeach
                            @if($formasPagamento->isEmpty())
                                <option value="">Dinheiro</option>
                            @endif
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="valorRecebido" class="form-label">Valor Recebido</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" class="form-control money-mask" id="valorRecebido" value="0,00">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-primary">
                            <div class="card-body text-center py-2">
                                <h6 class="text-white mb-1">Troco</h6>
                                <h4 class="text-white mb-0" id="trocoCalculado">R$ 0,00</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="observacoes" class="form-label">Observações</label>
                        <textarea class="form-control" id="observacoes" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success btn-lg" id="btnConfirmarVenda">
                    <i class="ti ti-check me-2"></i>
                    Confirmar Venda
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cupom -->
<div class="modal fade" id="modalCupom" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Venda Finalizada!</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="cupomConteudo">
                <!-- Conteúdo do cupom será carregado aqui -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="btnNovaVenda">
                    <i class="ti ti-plus me-1"></i> Nova Venda
                </button>
                <button type="button" class="btn btn-primary" id="btnImprimirCupom">
                    <i class="ti ti-printer me-1"></i> Imprimir Cupom
                </button>
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
    let carrinho = [];
    let timeoutBusca = null;

    // Máscara de dinheiro
    $('.money-mask').mask('#.##0,00', {reverse: true});

    // Função para formatar moeda
    function formatarMoeda(valor) {
        return 'R$ ' + parseFloat(valor || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    // Função para converter string monetária para número
    function converterParaNumero(valor) {
        if (!valor) return 0;
        return parseFloat(valor.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
    }

    // Buscar produtos
    function buscarProdutos(termo) {
        if (termo.length < 1) {
            $('#resultadoBusca').hide();
            return;
        }

        $.ajax({
            url: '{{ route("pdv.buscar-produto") }}',
            method: 'GET',
            data: { termo: termo },
            success: function(response) {
                if (response.success && response.produtos.length > 0) {
                    let html = '';
                    response.produtos.forEach(function(produto) {
                        const semEstoque = (produto.quantidade || 0) <= 0;
                        html += `
                            <div class="pdv-resultado-item ${semEstoque ? 'sem-estoque' : ''}" 
                                 data-produto='${JSON.stringify(produto)}'
                                 ${semEstoque ? '' : 'onclick="adicionarAoCarrinho(this)"'}>
                                <div>
                                    <strong>${produto.nome}</strong><br>
                                    <small class="text-muted">Código: ${produto.codigo || '-'}</small>
                                </div>
                                <div class="text-end">
                                    <strong>${formatarMoeda(produto.preco_venda)}</strong><br>
                                    <small class="${semEstoque ? 'text-danger' : 'text-success'}">
                                        Estoque: ${produto.quantidade || 0}
                                    </small>
                                </div>
                            </div>
                        `;
                    });
                    $('#resultadoBusca').html(html).show();
                } else {
                    $('#resultadoBusca').html('<div class="p-3 text-center text-muted">Nenhum produto encontrado</div>').show();
                }
            }
        });
    }

    // Buscar por código de barras (Enter)
    function buscarPorCodigo(codigo) {
        $.ajax({
            url: '{{ route("pdv.buscar-codigo") }}',
            method: 'GET',
            data: { codigo: codigo },
            success: function(response) {
                if (response.success) {
                    adicionarProdutoCarrinho(response.produto);
                    $('#campoBusca').val('').focus();
                    $('#resultadoBusca').hide();
                }
            },
            error: function(xhr) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Produto não encontrado',
                    text: xhr.responseJSON?.message || 'Código não encontrado',
                    timer: 2000,
                    showConfirmButton: false
                });
                $('#campoBusca').val('').focus();
            }
        });
    }

    // Evento de digitação na busca
    $('#campoBusca').on('input', function() {
        const termo = $(this).val();
        clearTimeout(timeoutBusca);
        timeoutBusca = setTimeout(function() {
            buscarProdutos(termo);
        }, 300);
    });

    // Evento Enter para código de barras
    $('#campoBusca').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            const codigo = $(this).val().trim();
            if (codigo) {
                buscarPorCodigo(codigo);
            }
        }
    });

    // Fechar resultado ao clicar fora
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.pdv-busca-container').length) {
            $('#resultadoBusca').hide();
        }
    });

    // Adicionar ao carrinho via clique
    window.adicionarAoCarrinho = function(elemento) {
        const produto = JSON.parse($(elemento).attr('data-produto'));
        adicionarProdutoCarrinho(produto);
        $('#campoBusca').val('').focus();
        $('#resultadoBusca').hide();
    };

    // Função principal para adicionar produto ao carrinho
    function adicionarProdutoCarrinho(produto) {
        // Verificar se já existe no carrinho
        const existente = carrinho.find(item => item.id_produto_venda === produto.id_produto_venda);
        
        if (existente) {
            // Verificar estoque
            if (existente.quantidade + 1 > produto.quantidade) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Estoque insuficiente',
                    text: `Disponível apenas ${produto.quantidade} unidade(s)`,
                    timer: 2000,
                    showConfirmButton: false
                });
                return;
            }
            existente.quantidade++;
            existente.subtotal = existente.quantidade * existente.preco_unitario;
        } else {
            carrinho.push({
                id_produto_venda: produto.id_produto_venda,
                nome: produto.nome,
                codigo: produto.codigo,
                preco_unitario: parseFloat(produto.preco_venda) || 0,
                quantidade: 1,
                subtotal: parseFloat(produto.preco_venda) || 0,
                estoque: produto.quantidade || 0
            });
        }

        atualizarCarrinho();

        // Mostrar feedback
        $('#msgProdutoAdicionado').text(`${produto.nome} adicionado ao carrinho`);
        $('#alertProdutoAdicionado').removeClass('d-none');
        setTimeout(() => $('#alertProdutoAdicionado').addClass('d-none'), 2000);
    }

    // Atualizar exibição do carrinho
    function atualizarCarrinho() {
        if (carrinho.length === 0) {
            $('#listaItens').html(`
                <div class="carrinho-vazio" id="carrinhoVazio">
                    <i class="ti ti-shopping-cart-off" style="font-size: 3rem;"></i>
                    <p class="mt-2">Carrinho vazio</p>
                </div>
            `);
            $('#btnFinalizar').prop('disabled', true);
            $('#qtdItens').text('0');
        } else {
            let html = '';
            carrinho.forEach((item, index) => {
                html += `
                    <div class="pdv-item" data-index="${index}">
                        <div class="pdv-item-info">
                            <div class="pdv-item-nome">${item.nome}</div>
                            <div class="pdv-item-codigo">Código: ${item.codigo || '-'}</div>
                            <div class="pdv-item-preco">${formatarMoeda(item.preco_unitario)}</div>
                        </div>
                        <div class="pdv-item-quantidade">
                            <button class="btn btn-sm btn-outline-secondary btn-diminuir" data-index="${index}">
                                <i class="ti ti-minus"></i>
                            </button>
                            <input type="number" class="form-control form-control-sm quantidade-input" 
                                   value="${item.quantidade}" min="1" max="${item.estoque}" data-index="${index}">
                            <button class="btn btn-sm btn-outline-secondary btn-aumentar" data-index="${index}">
                                <i class="ti ti-plus"></i>
                            </button>
                        </div>
                        <div class="pdv-item-subtotal">${formatarMoeda(item.subtotal)}</div>
                        <button class="btn btn-sm btn-outline-danger ms-2 btn-remover" data-index="${index}">
                            <i class="ti ti-trash"></i>
                        </button>
                    </div>
                `;
            });
            $('#listaItens').html(html);
            $('#btnFinalizar').prop('disabled', false);
            $('#qtdItens').text(carrinho.length);
        }

        calcularTotais();
    }

    // Calcular totais
    function calcularTotais() {
        let subtotal = carrinho.reduce((acc, item) => acc + item.subtotal, 0);
        let desconto = converterParaNumero($('#desconto').val());
        let acrescimo = converterParaNumero($('#acrescimo').val());
        let total = subtotal - desconto + acrescimo;

        if (total < 0) total = 0;

        $('#subtotal').text(formatarMoeda(subtotal));
        $('#totalFinal').text(formatarMoeda(total));
        
        // Atualizar porcentagem de desconto se foi alterado em R$
        if (subtotal > 0 && !window.atualizandoPct) {
            let pct = (desconto / subtotal * 100).toFixed(0);
            $('#descontoPct').val(pct);
        }
    }

    // Atualizar desconto por porcentagem
    $('#descontoPct').on('input', function() {
        let pct = parseInt($(this).val()) || 0;
        if (pct < 0) pct = 0;
        if (pct > 100) pct = 100;
        
        let subtotal = carrinho.reduce((acc, item) => acc + item.subtotal, 0);
        let descontoValor = subtotal * (pct / 100);
        
        window.atualizandoPct = true;
        $('#desconto').val(descontoValor.toFixed(2).replace('.', ','));
        window.atualizandoPct = false;
        
        calcularTotais();
    });

    // Eventos de quantidade
    $(document).on('click', '.btn-diminuir', function() {
        const index = $(this).data('index');
        if (carrinho[index].quantidade > 1) {
            carrinho[index].quantidade--;
            carrinho[index].subtotal = carrinho[index].quantidade * carrinho[index].preco_unitario;
            atualizarCarrinho();
        }
    });

    $(document).on('click', '.btn-aumentar', function() {
        const index = $(this).data('index');
        if (carrinho[index].quantidade < carrinho[index].estoque) {
            carrinho[index].quantidade++;
            carrinho[index].subtotal = carrinho[index].quantidade * carrinho[index].preco_unitario;
            atualizarCarrinho();
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Estoque máximo',
                text: `Disponível apenas ${carrinho[index].estoque} unidade(s)`,
                timer: 2000,
                showConfirmButton: false
            });
        }
    });

    $(document).on('change', '.quantidade-input', function() {
        const index = $(this).data('index');
        let novaQtd = parseInt($(this).val()) || 1;
        
        if (novaQtd < 1) novaQtd = 1;
        if (novaQtd > carrinho[index].estoque) {
            novaQtd = carrinho[index].estoque;
            Swal.fire({
                icon: 'warning',
                title: 'Estoque insuficiente',
                text: `Disponível apenas ${carrinho[index].estoque} unidade(s)`,
                timer: 2000,
                showConfirmButton: false
            });
        }

        carrinho[index].quantidade = novaQtd;
        carrinho[index].subtotal = novaQtd * carrinho[index].preco_unitario;
        atualizarCarrinho();
    });

    // Remover item
    $(document).on('click', '.btn-remover', function() {
        const index = $(this).data('index');
        carrinho.splice(index, 1);
        atualizarCarrinho();
    });

    // Atualizar totais ao mudar desconto/acréscimo
    $('#desconto, #acrescimo').on('input', function() {
        calcularTotais();
    });

    // Abrir modal finalizar
    $('#btnFinalizar').on('click', function() {
        if (carrinho.length === 0) return;

        let subtotal = carrinho.reduce((acc, item) => acc + item.subtotal, 0);
        let desconto = converterParaNumero($('#desconto').val());
        let acrescimo = converterParaNumero($('#acrescimo').val());
        let total = subtotal - desconto + acrescimo;

        $('#modalTotal').text(formatarMoeda(total));
        $('#modalQtdItens').text(carrinho.length);
        $('#valorRecebido').val(total.toFixed(2).replace('.', ','));
        $('#trocoCalculado').text('R$ 0,00');
        
        // Reinicializar máscara
        $('#valorRecebido').mask('#.##0,00', {reverse: true});
        
        $('#modalFinalizarVenda').modal('show');
    });

    // Calcular troco
    $('#valorRecebido').on('input', function() {
        let subtotal = carrinho.reduce((acc, item) => acc + item.subtotal, 0);
        let desconto = converterParaNumero($('#desconto').val());
        let acrescimo = converterParaNumero($('#acrescimo').val());
        let total = subtotal - desconto + acrescimo;
        let valorRecebido = converterParaNumero($(this).val());
        let troco = valorRecebido - total;

        if (troco < 0) troco = 0;
        $('#trocoCalculado').text(formatarMoeda(troco));
    });

    // Confirmar venda
    $('#btnConfirmarVenda').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-2"></i> Processando...');

        let desconto = converterParaNumero($('#desconto').val());
        let acrescimo = converterParaNumero($('#acrescimo').val());
        let valorRecebido = converterParaNumero($('#valorRecebido').val());

        $.ajax({
            url: '{{ route("pdv.finalizar") }}',
            method: 'POST',
            data: {
                itens: carrinho,
                desconto: desconto,
                acrescimo: acrescimo,
                id_forma_pagamento: $('#formaPagamento').val(),
                valor_recebido: valorRecebido,
                observacoes: $('#observacoes').val(),
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    $('#modalFinalizarVenda').modal('hide');
                    
                    // Carregar e mostrar cupom
                    carregarCupom(response.venda.id_venda);
                }
            },
            error: function(xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: xhr.responseJSON?.message || 'Erro ao finalizar venda'
                });
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="ti ti-check me-2"></i> Confirmar Venda');
            }
        });
    });

    // Carregar cupom
    function carregarCupom(idVenda) {
        $.ajax({
            url: '{{ url("pdv/cupom-dados") }}/' + idVenda,
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    renderizarCupom(response.venda, response.empresa);
                    $('#modalCupom').modal('show');
                }
            }
        });
    }

    // Renderizar cupom
    function renderizarCupom(venda, empresa) {
        let itensHtml = '';
        venda.itens.forEach(function(item) {
            itensHtml += `
                <tr>
                    <td style="text-align:left;">${item.nome_produto}</td>
                    <td style="text-align:center;">${item.quantidade}</td>
                    <td style="text-align:right;">${formatarMoeda(item.preco_unitario)}</td>
                    <td style="text-align:right;">${formatarMoeda(item.subtotal)}</td>
                </tr>
            `;
        });

        const dataVenda = new Date(venda.data_venda);
        const dataFormatada = dataVenda.toLocaleDateString('pt-BR') + ' ' + dataVenda.toLocaleTimeString('pt-BR');

        const cupomHtml = `
            <div id="cupomImpressao" style="font-family: 'Courier New', monospace; font-size: 12px; max-width: 300px; margin: 0 auto;">
                <div style="text-align: center; border-bottom: 1px dashed #000; padding-bottom: 10px; margin-bottom: 10px;">
                    <strong style="font-size: 14px;">${empresa?.nome_empresa || 'EMPRESA'}</strong><br>
                    ${empresa?.endereco || ''}<br>
                    ${empresa?.telefone || ''}<br>
                    CNPJ: ${empresa?.cnpj || '-'}
                </div>
                
                <div style="text-align: center; margin-bottom: 10px;">
                    <strong>CUPOM NÃO FISCAL</strong><br>
                    Venda #${venda.numero_venda}<br>
                    ${dataFormatada}
                </div>

                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                    <thead>
                        <tr style="border-bottom: 1px dashed #000;">
                            <th style="text-align:left;">Produto</th>
                            <th style="text-align:center;">Qtd</th>
                            <th style="text-align:right;">Unit</th>
                            <th style="text-align:right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itensHtml}
                    </tbody>
                </table>

                <div style="border-top: 1px dashed #000; margin-top: 10px; padding-top: 10px;">
                    <div style="display: flex; justify-content: space-between;">
                        <span>Subtotal:</span>
                        <span>${formatarMoeda(venda.subtotal)}</span>
                    </div>
                    ${venda.desconto > 0 ? `
                    <div style="display: flex; justify-content: space-between;">
                        <span>Desconto:</span>
                        <span>-${formatarMoeda(venda.desconto)}</span>
                    </div>
                    ` : ''}
                    ${venda.acrescimo > 0 ? `
                    <div style="display: flex; justify-content: space-between;">
                        <span>Acréscimo:</span>
                        <span>+${formatarMoeda(venda.acrescimo)}</span>
                    </div>
                    ` : ''}
                    <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 14px; margin-top: 5px; border-top: 1px dashed #000; padding-top: 5px;">
                        <span>TOTAL:</span>
                        <span>${formatarMoeda(venda.total)}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-top: 5px;">
                        <span>Forma Pgto:</span>
                        <span>${venda.forma_pagamento?.nome || 'Dinheiro'}</span>
                    </div>
                    ${venda.valor_recebido > 0 ? `
                    <div style="display: flex; justify-content: space-between;">
                        <span>Valor Recebido:</span>
                        <span>${formatarMoeda(venda.valor_recebido)}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Troco:</span>
                        <span>${formatarMoeda(venda.troco)}</span>
                    </div>
                    ` : ''}
                </div>

                <div style="text-align: center; margin-top: 15px; border-top: 1px dashed #000; padding-top: 10px;">
                    <small>Obrigado pela preferência!</small><br>
                    <small>Volte Sempre!</small>
                </div>
            </div>
        `;

        $('#cupomConteudo').html(cupomHtml);
    }

    // Imprimir cupom
    $('#btnImprimirCupom').on('click', function() {
        const conteudo = document.getElementById('cupomImpressao').innerHTML;
        const janela = window.open('', '_blank', 'width=350,height=600');
        janela.document.write(`
            <html>
            <head>
                <title>Cupom</title>
                <style>
                    body { font-family: 'Courier New', monospace; font-size: 12px; margin: 10px; }
                    @media print {
                        body { margin: 0; }
                    }
                </style>
            </head>
            <body onload="window.print(); window.close();">
                ${conteudo}
            </body>
            </html>
        `);
        janela.document.close();
    });

    // Nova venda
    $('#btnNovaVenda, #modalCupom').on('hidden.bs.modal', function() {
        carrinho = [];
        $('#desconto').val('0,00');
        $('#acrescimo').val('0,00');
        $('#observacoes').val('');
        atualizarCarrinho();
        $('#campoBusca').focus();
    });

    // Atalhos de teclado
    $(document).on('keydown', function(e) {
        // F2 - Finalizar
        if (e.key === 'F2' && carrinho.length > 0) {
            e.preventDefault();
            $('#btnFinalizar').click();
        }
        // ESC - Focar na busca
        if (e.key === 'Escape') {
            $('#campoBusca').focus();
        }
    });
});
</script>
@endsection
