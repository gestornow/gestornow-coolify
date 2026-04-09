/**
 * Money Helpers - Funções para manipulação de valores monetários
 * Formato brasileiro: 1.234,56
 */

/**
 * Converte string formatada em valor float
 * @param {string} value - Valor formatado (ex: "1.234,56")
 * @returns {number} - Valor numérico (ex: 1234.56)
 */
function parseMoneyToFloat(value) {
    if (!value || value === '') return 0;
    
    // Remove pontos de milhares e substitui vírgula por ponto
    return parseFloat(
        value.toString()
            .replace(/\./g, '')
            .replace(',', '.')
    ) || 0;
}

/**
 * Converte valor float em string formatada
 * @param {number} value - Valor numérico (ex: 1234.56)
 * @param {boolean} includeSymbol - Se true, adiciona "R$ " antes do valor
 * @returns {string} - Valor formatado (ex: "1.234,56" ou "R$ 1.234,56")
 */
function formatFloatToMoney(value, includeSymbol = false) {
    if (value === null || value === undefined || value === '') return includeSymbol ? 'R$ 0,00' : '0,00';
    
    const numValue = typeof value === 'string' ? parseMoneyToFloat(value) : parseFloat(value);
    
    if (isNaN(numValue)) return includeSymbol ? 'R$ 0,00' : '0,00';
    
    const formatted = numValue.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    
    return includeSymbol ? `R$ ${formatted}` : formatted;
}

/**
 * Formata campo de input enquanto o usuário digita
 * @param {Event} event - Evento de input
 */
function formatMoneyInput(event) {
    const input = event.target;
    let value = input.value.replace(/\D/g, ''); // Remove tudo que não é dígito
    
    if (value === '') {
        input.value = '';
        return;
    }
    
    // Remove zeros à esquerda
    value = value.replace(/^0+/, '');
    
    if (value === '') {
        input.value = '0,00';
        return;
    }
    
    // Garante que temos pelo menos 3 dígitos (para os centavos)
    value = value.padStart(3, '0');
    
    // Separa centavos
    const cents = value.slice(-2);
    const reais = value.slice(0, -2);
    
    // Adiciona pontos de milhares
    const formattedReais = reais.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    
    input.value = `${formattedReais},${cents}`;
}

/**
 * Aplica máscara monetária em um campo
 * @param {HTMLElement} element - Elemento input
 */
function applyMoneyMask(element) {
    element.addEventListener('input', formatMoneyInput);
    
    // Formata valor inicial se existir
    if (element.value) {
        const numValue = parseMoneyToFloat(element.value);
        element.value = formatFloatToMoney(numValue);
    }
}

/**
 * Aplica máscara monetária em todos os campos com classe específica
 * @param {string} className - Nome da classe (padrão: 'mask-money')
 */
function applyMoneyMaskToAll(className = 'mask-money') {
    document.querySelectorAll(`.${className}`).forEach(element => {
        applyMoneyMask(element);
    });
}

/**
 * Remove formatação monetária de um valor
 * @param {string} value - Valor formatado (ex: "R$ 1.234,56" ou "1.234,56")
 * @returns {string} - Valor sem formatação (ex: "1234.56")
 */
function stripMoneyFormat(value) {
    if (!value) return '0';
    
    return value.toString()
        .replace(/R\$\s?/g, '')
        .replace(/\./g, '')
        .replace(',', '.');
}

/**
 * Valida se um valor monetário é válido
 * @param {string|number} value - Valor a ser validado
 * @returns {boolean} - True se válido
 */
function isValidMoney(value) {
    if (value === null || value === undefined || value === '') return false;
    
    const numValue = typeof value === 'string' ? parseMoneyToFloat(value) : parseFloat(value);
    
    return !isNaN(numValue) && numValue >= 0;
}

/**
 * Soma valores monetários formatados
 * @param {...string} values - Valores formatados para somar
 * @returns {string} - Resultado formatado
 */
function sumMoney(...values) {
    const total = values.reduce((acc, value) => {
        return acc + parseMoneyToFloat(value);
    }, 0);
    
    return formatFloatToMoney(total);
}

/**
 * Subtrai valores monetários formatados
 * @param {string} value1 - Valor base
 * @param {string} value2 - Valor a subtrair
 * @returns {string} - Resultado formatado
 */
function subtractMoney(value1, value2) {
    const result = parseMoneyToFloat(value1) - parseMoneyToFloat(value2);
    return formatFloatToMoney(Math.max(0, result)); // Não permite negativos
}

// Expor funções globalmente
window.parseMoneyToFloat = parseMoneyToFloat;
window.formatFloatToMoney = formatFloatToMoney;
window.formatMoneyInput = formatMoneyInput;
window.applyMoneyMask = applyMoneyMask;
window.applyMoneyMaskToAll = applyMoneyMaskToAll;
window.stripMoneyFormat = stripMoneyFormat;
window.isValidMoney = isValidMoney;
window.sumMoney = sumMoney;
window.subtractMoney = subtractMoney;
