/**
 * Sistema de Autenticação Segura - Versão Simplificada
 * Funcionalidades básicas de login e monitoramento de sessão
 */

class SecureAuth {
    constructor() {
        this.sessionToken = localStorage.getItem('session_token');
        this.sessionExpires = localStorage.getItem('session_expires');
        this.userId = localStorage.getItem('user_id');
        this.isActive = true;
        this.warningShown = false;
        
        this.init();
    }
    
    init() {
        if (this.sessionToken && this.sessionExpires && this.userId) {
            // Verificar se a sessão ainda é válida
            if (this.isSessionExpired()) {
                this.handleSessionExpired();
                return;
            }
            
            // Iniciar monitoramento básico
            this.startSessionMonitoring();
            this.startActivityTracking();
        }
        
        // Inicializar validações de login
        this.initLoginValidation();
    }
    
    /**
     * Verificar se a sessão expirou
     */
    isSessionExpired() {
        const now = Math.floor(Date.now() / 1000);
        const expires = parseInt(this.sessionExpires);
        return now >= expires;
    }
    
    /**
     * Monitoramento da sessão
     */
    startSessionMonitoring() {
        setInterval(() => {
            if (!this.isActive) return;
            
            const now = Math.floor(Date.now() / 1000);
            const expires = parseInt(this.sessionExpires);
            const timeLeft = expires - now;
            
            // Avisar 5 minutos antes de expirar
            if (timeLeft <= 300 && timeLeft > 0 && !this.warningShown) {
                this.showSessionWarning(timeLeft);
                this.warningShown = true;
            }
            
            // Sessão expirada
            if (timeLeft <= 0) {
                this.handleSessionExpired();
            }
        }, 30000); // Verificar a cada 30 segundos
    }
    
    /**
     * Rastreamento básico de atividade do usuário
     */
    startActivityTracking() {
        const events = ['mousedown', 'keydown', 'scroll', 'touchstart'];
        
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.updateLastActivity();
            }, true);
        });
    }
    
    /**
     * Atualizar última atividade
     */
    updateLastActivity() {
        localStorage.setItem('last_activity', Math.floor(Date.now() / 1000));
    }
    
    /**
     * Mostrar aviso de expiração da sessão
     */
    showSessionWarning(timeLeft) {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        
        const message = `Sua sessão expirará em ${minutes}:${seconds.toString().padStart(2, '0')}. Deseja renovar?`;
        
        if (confirm(message)) {
            this.renewSession();
        }
    }
    
    /**
     * Renovar sessão
     */
    async renewSession() {
        try {
            const response = await fetch('/auth/renovar-sessao', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Authorization': `Bearer ${this.sessionToken}`
                },
                body: JSON.stringify({
                    session_token: this.sessionToken,
                    user_id: this.userId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Atualizar dados da sessão
                this.sessionToken = data.session_token;
                this.sessionExpires = data.expires_at;
                
                localStorage.setItem('session_token', this.sessionToken);
                localStorage.setItem('session_expires', this.sessionExpires);
                
                this.warningShown = false;
                this.showNotification('Sessão renovada com sucesso!', 'success');
            } else {
                this.handleSessionExpired();
            }
        } catch (error) {
            console.error('Erro ao renovar sessão:', error);
            this.handleSessionExpired();
        }
    }
    
    /**
     * Lidar com sessão expirada
     */
    handleSessionExpired() {
        this.isActive = false;
        this.clearSessionData();
        
        alert('Faça login para continuar.');
        window.location.href = '/login';
    }
    
    /**
     * Limpar dados da sessão
     */
    clearSessionData() {
        localStorage.removeItem('session_token');
        localStorage.removeItem('session_expires');
        localStorage.removeItem('user_id');
        localStorage.removeItem('last_activity');
    }
    
    /**
     * Logout seguro
     */
    async logout() {
        try {
            await fetch('/auth/logout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Authorization': `Bearer ${this.sessionToken}`
                },
                body: JSON.stringify({
                    session_token: this.sessionToken,
                    user_id: this.userId
                })
            });
        } catch (error) {
            console.error('Erro durante logout:', error);
        } finally {
            this.clearSessionData();
            window.location.href = '/login';
        }
    }
    
    /**
     * Validação de formulários de login
     */
    initLoginValidation() {
        const loginForms = document.querySelectorAll('form[action*="login"], #formAuthentication');
        
        loginForms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateLoginForm(form)) {
                    e.preventDefault();
                    return false;
                }
                
                // Desabilitar botão de submit para evitar duplo clique
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Entrando...';
                    
                    // Reabilitar após 3 segundos em caso de erro
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Entrar';
                    }, 3000);
                }
            });
        });
    }
    
    /**
     * Validar formulário de login
     */
    validateLoginForm(formElement) {
        const loginInput = formElement.querySelector('input[name="login"], #login');
        const passwordInput = formElement.querySelector('input[name="senha"], #senha');
        
        if (!loginInput || !passwordInput) {
            return false;
        }
        
        // Limpar erros anteriores
        this.clearFieldErrors(formElement);
        
        let isValid = true;
        
        // Validar login
        const login = loginInput.value.trim();
        if (login.length < 3) {
            this.showFieldError(loginInput, 'Login deve ter pelo menos 3 caracteres');
            isValid = false;
        }
        
        // Validar senha
        const password = passwordInput.value;
        if (password.length < 6) {
            this.showFieldError(passwordInput, 'Senha deve ter pelo menos 6 caracteres');
            isValid = false;
        }
        
        return isValid;
    }
    
    /**
     * Mostrar erro no campo
     */
    showFieldError(field, message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback d-block';
        errorDiv.textContent = message;
        
        field.classList.add('is-invalid');
        field.parentNode.appendChild(errorDiv);
        
        // Remover erro quando o usuário começar a digitar
        field.addEventListener('input', () => {
            field.classList.remove('is-invalid');
            if (errorDiv.parentNode) {
                errorDiv.parentNode.removeChild(errorDiv);
            }
        }, { once: true });
    }
    
    /**
     * Limpar erros de campos
     */
    clearFieldErrors(form) {
        const errorMessages = form.querySelectorAll('.invalid-feedback');
        const invalidFields = form.querySelectorAll('.is-invalid');
        
        errorMessages.forEach(error => error.remove());
        invalidFields.forEach(field => field.classList.remove('is-invalid'));
    }
    
    /**
     * Mostrar notificação
     */
    showNotification(message, type = 'info') {
        // Verificar se Bootstrap está disponível
        if (typeof bootstrap !== 'undefined') {
            // Usar toast do Bootstrap se disponível
            const toastHtml = `
                <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'info'} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            
            let toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
                document.body.appendChild(toastContainer);
            }
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            const toastElement = toastContainer.lastElementChild;
            const toast = new bootstrap.Toast(toastElement);
            toast.show();
            
        } else {
            // Fallback para alert simples
            alert(message);
        }
    }
}

// Inicializar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    window.secureAuth = new SecureAuth();
    
    // Interceptar botões de logout
    const logoutButtons = document.querySelectorAll('[data-action="logout"], .btn-logout, #logout-btn, a[href*="logout"]');
    logoutButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Tem certeza que deseja sair?')) {
                window.secureAuth.logout();
            }
        });
    });
});