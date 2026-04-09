// usuario-modal.js - Validação automática de formulário
(function(){
    'use strict';

    // --- Ensure window.utils helpers exist and provide masks ---
    if (!window.utils) window.utils = {};

    if (typeof window.utils.onlyDigits !== 'function') {
        window.utils.onlyDigits = function(v){ return (v||'').replace(/\D+/g,''); };
    }

    if (typeof window.utils.validateCPF !== 'function') {
        window.utils.validateCPF = function(v){
            try{
                if (window.GN && window.GN.validate && typeof window.GN.validate.cpf === 'function'){
                    return window.GN.validate.cpf(v);
                }
            }catch(e){}
            return window.utils.onlyDigits(v).length === 11;
        };
    }

    if (typeof window.utils.lookupCEP !== 'function') {
        window.utils.lookupCEP = async function(cep){
            const digits = window.utils.onlyDigits(cep);
            if (digits.length !== 8) throw new Error('CEP inválido');
            const url = 'https://viacep.com.br/ws/' + digits + '/json/';
            const res = await fetch(url, { cache: 'no-store' });
            if (!res.ok) throw new Error('CEP lookup failed');
            const data = await res.json();
            if (data.erro) throw new Error('CEP não encontrado');
            return {
                endereco: data.logradouro || '',
                bairro: data.bairro || '',
                localidade: data.localidade || '',
                uf: data.uf || ''
            };
        };
    }

    // Small mask implementations attached to classes used in templates
    function attachMasks() {
        function onlyDigits(v){ return window.utils.onlyDigits(v); }
        function setCaret(el, pos){ try{ el.setSelectionRange(pos,pos); }catch(e){} }

        function formatPercent(v){ const d = onlyDigits(v); if(!d) return ''; let num = parseFloat(d)/100; if(num>100) num=100; return num.toFixed(2).replace('.',','); }
        function formatCPF(v){ const d = onlyDigits(v).slice(0,11); let out = d; out = out.replace(/(\d{3})(\d)/,'$1.$2'); out = out.replace(/(\d{3})\.(\d{3})(\d)/,'$1.$2.$3'); out = out.replace(/(\d{3})\.(\d{3})\.(\d{3})(\d)/,'$1.$2.$3-$4'); return out; }
        function formatCNPJ(v){ const d = onlyDigits(v).slice(0,14); let out = d; out = out.replace(/(\d{2})(\d)/,'$1.$2'); out = out.replace(/(\d{2})\.(\d{3})(\d)/,'$1.$2.$3'); out = out.replace(/(\d{2})\.(\d{3})\.(\d{3})(\d)/,'$1.$2.$3/$4'); out = out.replace(/(\d{2})\.(\d{3})\.(\d{3})\/(\d{4})(\d)/,'$1.$2.$3/$4-$5'); return out; }
        function formatCEP(v){ const d = onlyDigits(v).slice(0,8); return d.replace(/(\d{5})(\d)/,'$1-$2').replace(/-$/,''); }
        function formatPhone(v){ const d = onlyDigits(v).slice(0,11); if(d.length<=10) return d.replace(/(\d{2})(\d{4})(\d{0,4})/,'($1) $2-$3').replace(/-$/,'').trim(); return d.replace(/(\d{2})(\d{5})(\d{4})/,'($1) $2-$3'); }
        function formatMoney(v){ 
            const d = onlyDigits(v); 
            if(!d || d === '0') return '0,00'; 
            // Remove zeros à esquerda
            const num = d.replace(/^0+/, '') || '0';
            // Garantir pelo menos 2 dígitos para os centavos
            const paddedNum = num.padStart(3, '0');
            const cents = paddedNum.slice(-2);
            const intPart = paddedNum.slice(0, -2);
            return intPart.replace(/\B(?=(\d{3})+(?!\d))/g,'.') + ',' + cents;
        }

        function bind(selector, formatter){ document.querySelectorAll(selector).forEach(el=>{
            // initial
            el.value = formatter(el.value);
            el.addEventListener('input', function(){ const start = el.selectionStart; const oldLen = el.value.length; el.value = formatter(el.value); const newLen = el.value.length; setCaret(el, Math.max(0, start + (newLen-oldLen))); });
            el.addEventListener('blur', function(){ el.value = formatter(el.value); });
        }); }

        bind('.mask-percent, .porcentagem', formatPercent);
        bind('.mask-cpf, input.cpf', formatCPF);
        bind('.mask-cnpj, input.cnpj', formatCNPJ);
        bind('.mask-cep, input.cep', formatCEP);
        bind('.mask-phone, input.telefone', formatPhone);
        bind('.mask-money, .mask-moeda', formatMoney);
    }

    // Espera o DOM carregar
    document.addEventListener('DOMContentLoaded', function(){
        // attach masks early so fields are formatted even in modal
        try{ attachMasks(); }catch(e){ console.warn('attachMasks failed', e); }

        const cepGlobalInput = document.getElementById('cep');
        if (cepGlobalInput && !cepGlobalInput.dataset.cepLookupBound) {
            cepGlobalInput.dataset.cepLookupBound = '1';
            cepGlobalInput.addEventListener('blur', async function(){
                if (!this.value.trim()) {
                    return;
                }

                const digits = window.utils.onlyDigits(this.value);
                if (digits.length !== 8) {
                    return;
                }

                try {
                    this.disabled = true;
                    const data = await window.utils.lookupCEP(this.value);
                    const endereco = document.getElementById('endereco');
                    const bairro = document.getElementById('bairro');

                    if (endereco && data.endereco) endereco.value = data.endereco;
                    if (bairro && data.bairro) bairro.value = data.bairro;
                } catch (err) {
                    // Falha de CEP nao deve bloquear preenchimento manual.
                } finally {
                    this.disabled = false;
                }
            });
        }
        
    const modal = document.getElementById('userModal');
    const form = document.getElementById('userCreateForm');
        
        if (!modal || !form) return;

        // Abre modal automaticamente se houver erros do servidor
        if (modal.dataset.openOnErrors === '1') {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }

        // VALIDAÇÃO NO SUBMIT DO FORMULÁRIO
        form.addEventListener('submit', function(e){
            e.preventDefault();
            
            // Remove todas as mensagens de erro anteriores
            clearAllErrors();
            
            let hasErrors = false;
            const errors = [];

            // 1. Validar campos obrigatórios
            const login = document.getElementById('login');
            const nome = document.getElementById('nome');
            
            if (!login.value.trim()) {
                showError(login, 'O campo Login é obrigatório');
                hasErrors = true;
                errors.push('Login');
            }
            
            if (!nome.value.trim()) {
                showError(nome, 'O campo Nome é obrigatório');
                hasErrors = true;
                errors.push('Nome');
            }

            // 2. Validar CPF (se preenchido)
            const cpf = document.getElementById('cpf');
            if (cpf && cpf.value.trim()) {
                if (!window.utils.validateCPF(cpf.value)) {
                    showError(cpf, 'CPF inválido', 'cpfHelp');
                    hasErrors = true;
                    errors.push('CPF');
                }
            }

            // 3. Validar CEP (se preenchido)
            const cep = document.getElementById('cep');
            if (cep && cep.value.trim()) {
                const cepDigits = window.utils.onlyDigits(cep.value);
                if (cepDigits.length !== 8) {
                    showError(cep, 'CEP deve conter 8 dígitos', 'cepHelp');
                    hasErrors = true;
                    errors.push('CEP');
                }
            }

            // 4. Validar Comissão (se preenchido)
            const comissao = document.getElementById('comissao');
            if (comissao && comissao.value.trim()) {
                let valor = comissao.value.replace(/\./g, '').replace(',', '.');
                const num = parseFloat(valor);
                
                if (isNaN(num)) {
                    showError(comissao, 'Comissão deve ser um número válido', 'comissaoHelp');
                    hasErrors = true;
                    errors.push('Comissão');
                } else if (num < 0) {
                    showError(comissao, 'Comissão não pode ser negativa', 'comissaoHelp');
                    hasErrors = true;
                    errors.push('Comissão');
                } else if (num > 100) {
                    showError(comissao, 'Comissão não pode ser maior que 100%', 'comissaoHelp');
                    hasErrors = true;
                    errors.push('Comissão');
                }
            }

            // 5. Validar Telefone (se preenchido) - apenas checar se tem dígitos suficientes
            const telefone = document.getElementById('telefone');
            if (telefone && telefone.value.trim()) {
                const digits = window.utils.onlyDigits(telefone.value);
                if (digits.length < 10) {
                    showError(telefone, 'Telefone deve ter no mínimo 10 dígitos');
                    hasErrors = true;
                    errors.push('Telefone');
                }
            }

            // Se houver erros, mostra alerta e não submete
            if (hasErrors) {
                showValidationAlert(errors);
                
                // Foca no primeiro campo com erro
                const firstError = form.querySelector('.is-invalid');
                if (firstError) {
                    firstError.focus();
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                
                return false;
            }

            // Se passou em todas as validações, submete o formulário
            form.submit();
        });

        // VALIDAÇÃO EM TEMPO REAL (ao sair do campo)
        
        // CPF
        const cpfInput = document.getElementById('cpf');
        if (cpfInput) {
            cpfInput.addEventListener('blur', function(){
                if (!this.value.trim()) {
                    clearError(this, 'cpfHelp');
                    return;
                }
                if (!window.utils.validateCPF(this.value)) {
                    showError(this, 'CPF inválido', 'cpfHelp');
                } else {
                    clearError(this, 'cpfHelp');
                }
            });
        }

        // CEP com lookup automático
        const cepInput = document.getElementById('cep');
        if (cepInput && !cepInput.dataset.cepLookupBound) {
            cepInput.dataset.cepLookupBound = '1';
            cepInput.addEventListener('blur', async function(){
                if (!this.value.trim()) {
                    clearError(this, 'cepHelp');
                    return;
                }
                
                const digits = window.utils.onlyDigits(this.value);
                if (digits.length !== 8) {
                    showError(this, 'CEP deve conter 8 dígitos', 'cepHelp');
                    return;
                }

                try {
                    // Mostra loading
                    this.disabled = true;
                    const data = await window.utils.lookupCEP(this.value);
                    
                    // Preenche os campos automaticamente
                    const endereco = document.getElementById('endereco');
                    const bairro = document.getElementById('bairro');
                    
                    if (endereco && data.endereco) endereco.value = data.endereco;
                    if (bairro && data.bairro) bairro.value = data.bairro;
                    
                    clearError(this, 'cepHelp');
                    
                    // Feedback visual de sucesso
                    this.classList.add('is-valid');
                    setTimeout(() => this.classList.remove('is-valid'), 2000);
                    
                } catch (err) {
                    showError(this, 'CEP não encontrado ou inválido', 'cepHelp');
                } finally {
                    this.disabled = false;
                }
            });
        }

        // Comissão
        const comissaoInput = document.getElementById('comissao');
        if (comissaoInput) {
            comissaoInput.addEventListener('blur', function(){
                if (!this.value.trim()) {
                    clearError(this, 'comissaoHelp');
                    return;
                }
                
                let valor = this.value.replace(/\./g, '').replace(',', '.');
                const num = parseFloat(valor);
                
                if (isNaN(num)) {
                    showError(this, 'Valor inválido', 'comissaoHelp');
                } else if (num < 0) {
                    showError(this, 'Valor não pode ser negativo', 'comissaoHelp');
                } else if (num > 100) {
                    showError(this, 'Valor não pode ser maior que 100%', 'comissaoHelp');
                } else {
                    clearError(this, 'comissaoHelp');
                }
            });
        }

        // Telefone
        const telefoneInput = document.getElementById('telefone');
        if (telefoneInput) {
            telefoneInput.addEventListener('blur', function(){
                if (!this.value.trim()) {
                    clearError(this);
                    return;
                }
                const digits = window.utils.onlyDigits(this.value);
                if (digits.length < 10) {
                    showError(this, 'Telefone incompleto (mínimo 10 dígitos)');
                } else {
                    clearError(this);
                }
            });
        }

        // Campos obrigatórios
        const loginInput = document.getElementById('login');
        const nomeInput = document.getElementById('nome');
        
        if (loginInput) {
            loginInput.addEventListener('blur', function(){
                if (!this.value.trim()) {
                    showError(this, 'Campo obrigatório');
                } else {
                    clearError(this);
                }
            });
        }
        
        if (nomeInput) {
            nomeInput.addEventListener('blur', function(){
                if (!this.value.trim()) {
                    showError(this, 'Campo obrigatório');
                } else {
                    clearError(this);
                }
            });
        }

        // FUNÇÕES AUXILIARES
        
        function showError(input, message, helpId) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            
            // Se tem um elemento de ajuda específico
            if (helpId) {
                const help = document.getElementById(helpId);
                if (help) {
                    help.textContent = message;
                    help.classList.remove('d-none');
                }
            } else {
                // Procura ou cria feedback
                let feedback = input.parentElement.querySelector('.invalid-feedback');
                if (!feedback) {
                    feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    input.parentElement.appendChild(feedback);
                }
                feedback.textContent = message;
                feedback.style.display = 'block';
            }
        }

        function clearError(input, helpId) {
            input.classList.remove('is-invalid');
            
            if (helpId) {
                const help = document.getElementById(helpId);
                if (help) {
                    help.textContent = '';
                    help.classList.add('d-none');
                }
            } else {
                const feedback = input.parentElement.querySelector('.invalid-feedback');
                if (feedback && !feedback.hasAttribute('data-server-error')) {
                    feedback.textContent = '';
                    feedback.style.display = 'none';
                }
            }
        }

        function clearAllErrors() {
            // Remove todas as classes is-invalid
            form.querySelectorAll('.is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
            });
            
            // Esconde todos os help texts dinâmicos
            form.querySelectorAll('small.text-danger').forEach(el => {
                if (!el.hasAttribute('data-server-error')) {
                    el.classList.add('d-none');
                    el.textContent = '';
                }
            });
            
            // Esconde feedbacks dinâmicos (não vindos do servidor)
            form.querySelectorAll('.invalid-feedback').forEach(el => {
                if (!el.hasAttribute('data-server-error')) {
                    el.style.display = 'none';
                    el.textContent = '';
                }
            });
        }

        function showValidationAlert(errors) {
            // Remove alertas anteriores
            const oldAlert = modal.querySelector('.validation-alert');
            if (oldAlert) oldAlert.remove();

            // Cria novo alerta
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show validation-alert';
            alert.innerHTML = `
                <i class="ti ti-alert-circle me-2"></i>
                <strong>Erro de validação!</strong> 
                Por favor, corrija os seguintes campos: ${errors.join(', ')}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            // Insere no início do modal-body
            const modalBody = modal.querySelector('.modal-body');
            modalBody.insertBefore(alert, modalBody.firstChild);

            // Auto-remove após 5 segundos
            setTimeout(() => {
                if (alert.parentElement) {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 150);
                }
            }, 5000);
        }

        // Limpa erros quando o modal é fechado
        modal.addEventListener('hidden.bs.modal', function(){
            form.reset();
            clearAllErrors();
            
            // Remove alertas de validação
            const alerts = modal.querySelectorAll('.validation-alert');
            alerts.forEach(alert => alert.remove());
        });
    });
})();