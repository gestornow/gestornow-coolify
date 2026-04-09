/**
 * Reset Password Flow JavaScript
 * Funcionalidades para o processo de redefinição de senha
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Inicializar funcionalidades baseadas na página atual
    const currentPage = document.body.getAttribute('data-page') || 
                       window.location.pathname.split('/').pop();
    
    switch(currentPage) {
        case 'esqueci-senha':
            initForgotPasswordPage();
            break;
        case 'codigo-redefinicao':
            initResetCodePage();
            break;
        case 'nova-senha':
            initNewPasswordPage();
            break;
    }
});

/**
 * Página de solicitar código
 */
function initForgotPasswordPage() {
    const form = document.getElementById('formAuthentication');
    const emailInput = document.getElementById('email');
    const submitBtn = form?.querySelector('button[type="submit"]');

    if (!form || !emailInput || !submitBtn) return;

    // Validação em tempo real do email
    emailInput.addEventListener('input', function() {
        const email = this.value.trim();
        const isValid = validateEmail(email);
        
        updateFieldValidation(this, isValid);
        submitBtn.disabled = !isValid;
    });

    // Animação de envio
    form.addEventListener('submit', function() {
        if (!submitBtn.disabled) {
            showLoadingState(submitBtn, 'Enviando...');
        }
    });
}

/**
 * Página de inserir código
 */
function initResetCodePage() {
    const codeInput = document.getElementById('code');
    const form = document.getElementById('formAuthentication');
    const submitBtn = form?.querySelector('button[type="submit"]');

    if (!codeInput || !form || !submitBtn) return;

    // Formatação automática do código
    codeInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 6) value = value.substring(0, 6);
        
        e.target.value = value;
        
        // Auto-submit quando completar 6 dígitos
        if (value.length === 6) {
            validateCode(value);
        }
        
        // Habilitar/desabilitar botão
        submitBtn.disabled = value.length !== 6;
    });

    // Colar código
    codeInput.addEventListener('paste', function(e) {
        e.preventDefault();
        const paste = (e.clipboardData || window.clipboardData).getData('text');
        const code = paste.replace(/\D/g, '').substring(0, 6);
        this.value = code;
        
        if (code.length === 6) {
            submitBtn.disabled = false;
            setTimeout(() => validateCode(code), 100);
        }
    });

    // Focus automático
    codeInput.focus();
}

/**
 * Página de nova senha
 */
function initNewPasswordPage() {
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('password_confirmation');
    const form = document.getElementById('formAuthentication');
    const submitBtn = form?.querySelector('button[type="submit"]');

    if (!passwordInput || !confirmInput || !submitBtn) return;

    // Validação em tempo real
    function validatePasswords() {
        const password = passwordInput.value;
        const confirm = confirmInput.value;
        
        const passwordStrength = checkPasswordStrength(password);
        const passwordsMatch = password === confirm && password.length > 0;
        const isValid = passwordStrength.score >= 3 && passwordsMatch;
        
        updatePasswordStrength(passwordStrength);
        updatePasswordMatch(passwordsMatch, confirm.length > 0);
        
        submitBtn.disabled = !isValid;
        
        return isValid;
    }

    passwordInput.addEventListener('input', validatePasswords);
    confirmInput.addEventListener('input', validatePasswords);

    // Toggle de visibilidade
    initPasswordToggle();
}

/**
 * Utilitários de validação
 */
function validateEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

function checkPasswordStrength(password) {
    let score = 0;
    const feedback = [];
    
    if (password.length >= 8) score += 1;
    else feedback.push('pelo menos 8 caracteres');
    
    if (/[a-z]/.test(password)) score += 1;
    else feedback.push('letras minúsculas');
    
    if (/[A-Z]/.test(password)) score += 1;
    else feedback.push('letras maiúsculas');
    
    if (/[0-9]/.test(password)) score += 1;
    else feedback.push('números');
    
    if (/[^A-Za-z0-9]/.test(password)) score += 1;
    else feedback.push('símbolos');
    
    let level = 'weak';
    if (score >= 4) level = 'strong';
    else if (score >= 3) level = 'medium';
    
    return { score, feedback, level };
}

/**
 * Utilitários de UI
 */
function updateFieldValidation(field, isValid) {
    field.classList.remove('is-valid', 'is-invalid');
    
    if (field.value.length > 0) {
        field.classList.add(isValid ? 'is-valid' : 'is-invalid');
    }
}

function updatePasswordStrength(strength) {
    const indicator = document.getElementById('passwordStrength');
    if (!indicator) return;
    
    const colors = {
        weak: '#dc3545',
        medium: '#fd7e14', 
        strong: '#198754'
    };
    
    const labels = {
        weak: 'Fraca',
        medium: 'Média',
        strong: 'Forte'
    };
    
    indicator.innerHTML = strength.score > 0 ? `
        <div style="color: ${colors[strength.level]}">
            Força: ${labels[strength.level]}
            ${strength.feedback.length > 0 ? `<br><small>Adicione: ${strength.feedback.join(', ')}</small>` : ''}
        </div>
    ` : '';
}

function updatePasswordMatch(matches, showFeedback) {
    const feedback = document.getElementById('confirmFeedback');
    if (!feedback) return;
    
    if (showFeedback) {
        feedback.innerHTML = matches ? 
            '<small class="text-success">✓ Senhas conferem</small>' :
            '<small class="text-danger">✗ Senhas não conferem</small>';
    } else {
        feedback.innerHTML = '';
    }
}

function initPasswordToggle() {
    document.querySelectorAll('.password-toggle').forEach(toggle => {
        toggle.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'ti ti-eye';
            } else {
                input.type = 'password';
                icon.className = 'ti ti-eye-off';
            }
        });
    });
}

function showLoadingState(button, text) {
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = `
        <div class="d-flex align-items-center justify-content-center">
            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
            ${text}
        </div>
    `;
    
    // Restaurar após 3 segundos se não houver redirecionamento
    setTimeout(() => {
        if (!button.disabled) return;
        button.disabled = false;
        button.innerHTML = originalText;
    }, 3000);
}

/**
 * Validação de código via AJAX
 */
function validateCode(code) {
    const form = document.getElementById('formAuthentication');
    if (!form) return;
    
    showLoadingState(form.querySelector('button[type="submit"]'), 'Verificando...');
    
    // Submit automático após pequeno delay para feedback visual
    setTimeout(() => {
        form.submit();
    }, 500);
}

/**
 * Sistema de reenvio de código
 */
function initResendSystem() {
    const resendBtn = document.getElementById('resendBtn');
    const resendTimer = document.getElementById('resendTimer');
    
    if (!resendBtn || !resendTimer) return;
    
    let timeLeft = 60;
    
    function startTimer() {
        resendBtn.style.display = 'none';
        resendTimer.style.display = 'inline';
        
        const timer = setInterval(() => {
            timeLeft--;
            resendTimer.textContent = `Reenviar em ${timeLeft}s`;
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                resendBtn.style.display = 'inline';
                resendTimer.style.display = 'none';
                timeLeft = 60;
            }
        }, 1000);
    }
    
    resendBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        fetch('/reenviar-codigo', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Novo código enviado!', 'success');
                startTimer();
            } else {
                showToast(data.error || 'Erro ao reenviar código', 'error');
            }
        })
        .catch(() => {
            showToast('Erro de conexão', 'error');
        });
    });
    
    // Iniciar timer automaticamente
    startTimer();
}

/**
 * Sistema de notificações toast
 */
function showToast(message, type = 'info') {
    // Criar toast se não existir
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'primary'} border-0`;
    toast.setAttribute('role', 'alert');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    // Mostrar toast
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    // Remover após ocultar
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}