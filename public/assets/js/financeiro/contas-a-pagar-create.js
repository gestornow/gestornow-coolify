/**
 * Contas a Pagar - Create Form
 * Formulário de criação de contas a pagar
 */

$(document).ready(function() {
    'use strict';

    // Inicializar funcionalidades comuns
    ContasFormCommon.init({
        formId: 'formContaAPagar',
        tipoLancamento: 'unico',
        btnColor: 'primary' // Azul para contas a pagar
    });

    // Expor função para uso no onclick do HTML
    window.gerarParcelasCustomizadas = function() {
        ContasFormCommon.gerarParcelasCustomizadas('Conta a Pagar');
    };

    window.redistribuirValorParcelas = function(index) {
        ContasFormCommon.redistribuirValorParcelas(index);
    };

    window.atualizarTotalCustom = function() {
        // Esta função é chamada internamente pelo ContasFormCommon
        // mas mantemos aqui para compatibilidade
    };
});
