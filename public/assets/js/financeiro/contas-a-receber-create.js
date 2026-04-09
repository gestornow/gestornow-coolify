/**
 * Contas a Receber - Create Form
 * Formulário de criação de contas a receber
 */

$(document).ready(function() {
    'use strict';

    // Inicializar funcionalidades comuns
    ContasFormCommon.init({
        formId: 'formContaAReceber',
        tipoLancamento: 'unico',
        btnColor: 'success' // Verde para contas a receber
    });

    // Expor função para uso no onclick do HTML
    window.gerarParcelasCustomizadas = function() {
        ContasFormCommon.gerarParcelasCustomizadas('Conta a Receber');
    };

    window.redistribuirValorParcelas = function(index) {
        ContasFormCommon.redistribuirValorParcelas(index);
    };

    window.atualizarTotalCustom = function() {
        // Esta função é chamada internamente pelo ContasFormCommon
        // mas mantemos aqui para compatibilidade
    };
});
