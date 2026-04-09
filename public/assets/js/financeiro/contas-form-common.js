/**
 * Contas Form Common - Funções compartilhadas entre Contas a Pagar e Contas a Receber
 * Gerenciamento de parcelamento, recorrência e validações
 */

(function(window) {
    'use strict';

    // ============================================
    // Namespace
    // ============================================
    window.ContasFormCommon = window.ContasFormCommon || {};

    // ============================================
    // Configuração e Estado
    // ============================================
    let contadorParcelas = 0;
    let config = {
        formId: 'formConta',
        tipoLancamento: 'unico',
        btnColor: 'primary'
    };

    // ============================================
    // Inicialização
    // ============================================
    window.ContasFormCommon.init = function(userConfig = {}) {
        config = { ...config, ...userConfig };
        
        initializeSelect2();
        initializeFormSubmit();
        initializeStatusToggle();
        initializeLancamentoToggle();
        initializeEventListeners();
    };

    // ============================================
    // Select2
    // ============================================
    function initializeSelect2() {
        if ($('.select2').length && typeof $.fn.select2 === 'function') {
            $('.select2').select2({
                placeholder: 'Selecione...',
                allowClear: true
            });
        }
    }

    // ============================================
    // Submit do Formulário - Conversão de Valores
    // ============================================
    function initializeFormSubmit() {
        $(`#${config.formId}`).on('submit', function(e) {
            // Converter campos monetários de formato BR para decimal
            const camposMonetarios = ['valor_total', 'juros', 'multa', 'desconto', 'valor_pago'];
            
            camposMonetarios.forEach(campo => {
                const input = document.getElementById(campo);
                if (input && input.value) {
                    const valorDecimal = parseMoneyToFloat(input.value);
                    input.value = valorDecimal.toFixed(2);
                }
            });

            // Converter valores de parcelas customizadas se existirem
            $('.valor-parcela-custom').each(function() {
                const valorDecimal = parseMoneyToFloat($(this).val());
                $(this).val(valorDecimal.toFixed(2));
            });
        });
    }

    // ============================================
    // Toggle de Campos de Pagamento/Recebimento
    // ============================================
    function initializeStatusToggle() {
        function togglePaymentFields() {
            const status = $('#status').val();
            if (status === 'pago') {
                $('#div_forma_pagamento, #div_data_pagamento, #div_valor_pago').show();
                $('#data_pagamento, #id_forma_pagamento').prop('required', true);
            } else {
                $('#div_forma_pagamento, #div_data_pagamento, #div_valor_pago').hide();
                $('#data_pagamento, #id_forma_pagamento').prop('required', false);
            }
        }

        $('#status').on('change', togglePaymentFields);
        togglePaymentFields(); // Chamada inicial
    }

    // ============================================
    // Toggle de Tipo de Lançamento
    // ============================================
    function initializeLancamentoToggle() {
        function toggleLancamentoFields() {
            const tipo = $('#tipo_lancamento').val();
            
            // Esconder todos os campos especiais
            $('#div_total_parcelas, #div_intervalo_parcelas, #div_intervalo_custom, #div_num_parcelas_customizadas, #div_intervalo_parcelas_custom, #div_btn_gerar_parcelas, #div_parcelas_customizadas, #div_tipo_recorrencia, #div_quantidade_recorrencias').hide();
            $('#info_parcelamento, #info_recorrencia').hide();
            
            // Controlar obrigatoriedade do campo data_vencimento
            if (tipo === 'parcelado_customizado') {
                $('#data_vencimento').prop('required', false);
                $('#data_vencimento').closest('.col-md-3').find('label').html('Data Vencimento <small class="text-muted">(Opcional - use como base)</small>');
            } else {
                $('#data_vencimento').prop('required', true);
                $('#data_vencimento').closest('.col-md-3').find('label').html('Data Vencimento <span class="text-danger">*</span>');
            }
            
            if (tipo === 'parcelado') {
                $('#div_total_parcelas, #div_intervalo_parcelas').show();
                $('#info_parcelamento').show();
                // Verificar se intervalo é personalizado
                if ($('#intervalo_parcelas').val() === 'custom') {
                    $('#div_intervalo_custom').show();
                }
                calcularInfoParcelamento();
            } else if (tipo === 'parcelado_customizado') {
                $('#div_num_parcelas_customizadas, #div_intervalo_parcelas_custom, #div_btn_gerar_parcelas').show();
            } else if (tipo === 'recorrente') {
                $('#div_tipo_recorrencia, #div_quantidade_recorrencias').show();
                $('#info_recorrencia').show();
                calcularInfoRecorrencia();
            }
        }

        $('#tipo_lancamento').on('change', toggleLancamentoFields);
        toggleLancamentoFields(); // Chamada inicial
        
        // Toggle do intervalo personalizado
        $('#intervalo_parcelas').on('change', function() {
            if ($(this).val() === 'custom') {
                $('#div_intervalo_custom').show();
            } else {
                $('#div_intervalo_custom').hide();
            }
            calcularInfoParcelamento();
        });
    }

    // ============================================
    // Event Listeners
    // ============================================
    function initializeEventListeners() {
        $('#valor_total, #total_parcelas, #intervalo_parcelas, #intervalo_custom').on('input change', function() {
            if ($('#tipo_lancamento').val() === 'parcelado') {
                calcularInfoParcelamento();
            }
            if ($('#tipo_lancamento').val() === 'parcelado_customizado') {
                atualizarTotalCustom();
            }
        });

        $('#valor_total, #quantidade_recorrencias, #tipo_recorrencia').on('change', function() {
            if ($('#tipo_lancamento').val() === 'recorrente') {
                calcularInfoRecorrencia();
            }
        });
    }

    // ============================================
    // Cálculo de Informações de Parcelamento
    // ============================================
    function calcularInfoParcelamento() {
        const valorTotal = parseMoneyToFloat($('#valor_total').val());
        const numParcelas = parseInt($('#total_parcelas').val()) || 2;
        const valorParcela = valorTotal / numParcelas;
        
        // Pegar intervalo selecionado
        let intervalo = $('#intervalo_parcelas').val();
        let textoIntervalo = '';
        
        if (intervalo === 'custom') {
            intervalo = parseInt($('#intervalo_custom').val()) || 30;
            textoIntervalo = `a cada ${intervalo} dias`;
        } else {
            intervalo = parseInt(intervalo);
            const intervalos = {
                7: 'semanais',
                15: 'quinzenais',
                30: 'mensais',
                60: 'bimestrais',
                90: 'trimestrais'
            };
            textoIntervalo = intervalos[intervalo] || 'mensais';
        }
        
        $('#info_num_parcelas').text(numParcelas);
        $('#info_valor_parcela').text(valorParcela.toLocaleString('pt-BR', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        }));
        
        // Atualizar mensagem para incluir o intervalo
        const mensagem = `Serão criadas ${numParcelas} contas com vencimentos ${textoIntervalo} consecutivos. O valor de cada parcela será de aproximadamente R$ ${valorParcela.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}.`;
        $('#info_parcelamento div').html(`<strong>Parcelamento:</strong> ${mensagem}`);
    }

    // ============================================
    // Cálculo de Informações de Recorrência
    // ============================================
    function calcularInfoRecorrencia() {
        const valorTotal = parseMoneyToFloat($('#valor_total').val());
        const numRecorrencias = parseInt($('#quantidade_recorrencias').val()) || 12;
        const tipoRecorrencia = $('#tipo_recorrencia option:selected').text();
        
        $('#info_num_recorrencias').text(numRecorrencias);
        $('#info_periodicidade').text(tipoRecorrencia.toLowerCase());
        $('#info_valor_recorrente').text(valorTotal.toLocaleString('pt-BR', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        }));
    }

    // ============================================
    // Gerar Parcelas Customizadas
    // ============================================
    window.ContasFormCommon.gerarParcelasCustomizadas = function(descricaoPadrao = 'Conta') {
        const numParcelas = parseInt($('#num_parcelas_customizadas').val());
        const valorTotal = parseMoneyToFloat($('#valor_total').val());
        const descricaoPrincipal = $('#descricao').val() || descricaoPadrao;
        
        if (!numParcelas || numParcelas < 2) {
            Swal.fire({
                icon: 'warning',
                title: 'Atenção!',
                text: 'Por favor, informe um número válido de parcelas (mínimo 2)',
                confirmButtonText: 'OK',
                customClass: {
                    confirmButton: `btn btn-${config.btnColor}`
                },
                buttonsStyling: false
            });
            return;
        }
        
        if (valorTotal <= 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Atenção!',
                text: 'Por favor, informe o valor total da conta primeiro',
                confirmButtonText: 'OK',
                customClass: {
                    confirmButton: `btn btn-${config.btnColor}`
                },
                buttonsStyling: false
            });
            $('#valor_total').focus();
            return;
        }
        
        // Limpar parcelas anteriores
        $('#lista_parcelas_customizadas').empty();
        contadorParcelas = 0;
        
        const valorParcela = (valorTotal / numParcelas).toFixed(2);
        const dataBase = new Date($('#data_vencimento').val() || new Date());
        
        // Obter intervalo entre parcelas (em dias)
        let intervaloDias = 30; // Padrão mensal
        const intervaloSelecionado = $('#intervalo_parcelas_custom').val();
        if (intervaloSelecionado) {
            intervaloDias = parseInt(intervaloSelecionado);
        }
        
        // Gerar as parcelas
        for (let i = 0; i < numParcelas; i++) {
            contadorParcelas++;
            
            // Calcular data sugerida baseada no intervalo em dias
            const dataParcela = new Date(dataBase);
            dataParcela.setDate(dataParcela.getDate() + (intervaloDias * i));
            const dataFormatada = dataParcela.toISOString().split('T')[0];
            
            // Ajustar valor da última parcela para compensar arredondamentos
            const valorFinal = (i === numParcelas - 1) 
                ? (valorTotal - (valorParcela * (numParcelas - 1))).toFixed(2)
                : valorParcela;
            
            const valorFinalFormatado = formatFloatToMoney(valorFinal);
            
            const html = `
                <div class="row mb-2 parcela-custom-item align-items-end" data-parcela="${contadorParcelas}" data-index="${i}">
                    <div class="col-md-1">
                        <label class="form-label">Nº</label>
                        <input type="text" class="form-control form-control-sm text-center fw-bold" value="${i + 1}/${numParcelas}" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Descrição</label>
                        <input type="text" 
                            class="form-control form-control-sm" 
                            name="parcelas_custom[${contadorParcelas}][descricao]" 
                            value="${descricaoPrincipal} - Parcela ${i + 1} de ${numParcelas}"
                            required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Data Vencimento <span class="text-danger">*</span></label>
                        <input type="date" 
                            class="form-control form-control-sm" 
                            name="parcelas_custom[${contadorParcelas}][data_vencimento]" 
                            value="${dataFormatada}"
                            required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Valor (R$)</label>
                        <input type="text" 
                            class="form-control form-control-sm mask-money valor-parcela-custom" 
                            name="parcelas_custom[${contadorParcelas}][valor]" 
                            value="${valorFinalFormatado}"
                            data-parcela-index="${i}"
                            onblur="ContasFormCommon.redistribuirValorParcelas(${i})">
                    </div>
                    <div class="col-md-1">
                        <button type="button" 
                            class="btn btn-sm btn-outline-info w-100" 
                            title="Editar valor - Os outros valores se ajustarão automaticamente"
                            onclick="Swal.fire({icon: 'info', title: 'Dica', text: 'Digite o valor desejado para esta parcela. Os valores das outras parcelas serão ajustados automaticamente.', confirmButtonText: 'Entendi', customClass: {confirmButton: 'btn btn-${config.btnColor}'}, buttonsStyling: false});">
                            <i class="ti ti-edit"></i>
                        </button>
                    </div>
                </div>
            `;
            
            $('#lista_parcelas_customizadas').append(html);
        }
        
        $('#div_parcelas_customizadas').slideDown();
        $('#total_parcelas_count').text(numParcelas);
        atualizarTotalCustom();
        
        // Reaplicar máscaras nos novos campos
        if (window.utils && typeof window.utils.attachMasks === 'function') {
            setTimeout(() => window.utils.attachMasks(), 100);
        }
        
        // Scroll suave até as parcelas
        $('html, body').animate({
            scrollTop: $('#div_parcelas_customizadas').offset().top - 100
        }, 500);
    };

    // ============================================
    // Atualizar Total Customizado
    // ============================================
    function atualizarTotalCustom() {
        const valorOriginal = parseMoneyToFloat($('#valor_total').val());
        let totalParcelas = 0;
        const numParcelas = $('#lista_parcelas_customizadas .parcela-custom-item').length;
        
        $('.valor-parcela-custom').each(function() {
            totalParcelas += parseMoneyToFloat($(this).val());
        });
        
        $('#total_parcelas_custom').text(totalParcelas.toLocaleString('pt-BR', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        }));
        $('#valor_original').text(valorOriginal.toLocaleString('pt-BR', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        }));
        $('#total_parcelas_count').text(numParcelas);
        
        // Validar se os valores batem
        const diff = Math.abs(totalParcelas - valorOriginal);
        const alertClass = config.btnColor === 'success' ? 'alert-success' : 'alert-primary';
        if (diff > 0.01 && valorOriginal > 0) {
            $('#total_parcelas_custom').parent().parent().removeClass(alertClass).addClass('alert-warning');
        } else {
            $('#total_parcelas_custom').parent().parent().removeClass('alert-warning').addClass(alertClass);
        }
    }

    // ============================================
    // Redistribuir Valores Entre Parcelas
    // ============================================
    window.ContasFormCommon.redistribuirValorParcelas = function(indexEditado) {
        const valorTotal = parseMoneyToFloat($('#valor_total').val());
        const parcelas = $('.valor-parcela-custom');
        const numParcelas = parcelas.length;
        
        if (numParcelas < 2) return;
        
        // Pegar o valor editado
        const valorEditado = parseMoneyToFloat(parcelas.eq(indexEditado).val());
        
        // Calcular quanto sobrou para distribuir
        const valorRestante = valorTotal - valorEditado;
        const parcelasRestantes = numParcelas - 1;
        
        if (parcelasRestantes <= 0) return;
        
        // Distribuir igualmente entre as outras parcelas
        const valorPorParcela = valorRestante / parcelasRestantes;
        let somaDistribuida = 0;
        
        parcelas.each(function(index) {
            if (index !== indexEditado) {
                if (index === numParcelas - 1 && indexEditado !== numParcelas - 1) {
                    // Última parcela ajusta os centavos
                    const valorFinal = valorRestante - somaDistribuida;
                    $(this).val(formatFloatToMoney(valorFinal));
                } else {
                    $(this).val(formatFloatToMoney(valorPorParcela));
                    somaDistribuida += valorPorParcela;
                }
            }
        });
        
        // Atualizar o total
        atualizarTotalCustom();
        
        // Reaplicar máscaras
        if (window.utils && typeof window.utils.attachMasks === 'function') {
            setTimeout(() => window.utils.attachMasks(), 100);
        }
        
        // Visual feedback
        parcelas.eq(indexEditado).parent().parent().addClass('bg-light');
        setTimeout(function() {
            parcelas.eq(indexEditado).parent().parent().removeClass('bg-light');
        }, 1000);
    };

})(window);
