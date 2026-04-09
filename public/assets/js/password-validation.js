/**
 * password-validation.js
 * Validação de força de senha, correspondência e toggle de visibilidade
 * Funciona em qualquer formulário com inputs de senha
 */

class PasswordValidator {
  constructor(senhaSelector, confirmacaoSelector, formSelector = 'form') {
    this.form = document.querySelector(formSelector);
    this.senhaInput = document.querySelector(senhaSelector);
    this.confirmInput = document.querySelector(confirmacaoSelector);
    
    if (!this.form || !this.senhaInput || !this.confirmInput) {
      console.warn('PasswordValidator: Não encontrou todos os elementos necessários', {
        form: formSelector,
        senha: senhaSelector,
        confirmacao: confirmacaoSelector
      });
      return;
    }

    // Encontrar ou criar containers para feedback
    this.setupFeedbackContainers();
    
    this.init();
  }

  setupFeedbackContainers() {
    // Container para força da senha - localizar ou criar próximo ao input de senha
    const senhaParent = this.senhaInput.closest('.form-password-toggle') || this.senhaInput.closest('.input-group');
    if (senhaParent) {
      let strengthContainer = senhaParent.querySelector('.password-strength-feedback');
      if (!strengthContainer) {
        strengthContainer = document.createElement('div');
        strengthContainer.className = 'password-strength-feedback';
        senhaParent.insertAdjacentElement('afterend', strengthContainer);
      }
      this.strengthContainer = strengthContainer;
    }

    // Container para match - localizar ou criar próximo ao input de confirmação
    const confirmParent = this.confirmInput.closest('.form-password-toggle') || this.confirmInput.closest('.input-group');
    if (confirmParent) {
      let matchContainer = confirmParent.querySelector('.password-match-feedback');
      if (!matchContainer) {
        matchContainer = document.createElement('div');
        matchContainer.className = 'password-match-feedback';
        confirmParent.insertAdjacentElement('afterend', matchContainer);
      }
      this.matchContainer = matchContainer;
    }
  }

  init() {
    // Inicializar toggle de senha
    this.initPasswordToggle();
    
    // Adicionar listeners
    this.senhaInput.addEventListener('input', () => this.validateAll());
    this.confirmInput.addEventListener('input', () => this.validateAll());
    
    // Validação inicial
    this.validateAll();
  }

  /**
   * Inicializa o toggle de visibilidade das senhas
   */
  initPasswordToggle() {
    // Apenas para este formulário
    this.form.querySelectorAll('.password-toggle, .password-toggle-user').forEach(toggle => {
      toggle.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        const input = toggle.parentElement.querySelector('input[type="password"], input[type="text"]');
        const icon = toggle.querySelector('i');
        
        if (!input || !icon) return;
        
        if (input.type === 'password') {
          input.type = 'text';
          icon.classList.remove('ti-eye-off');
          icon.classList.add('ti-eye');
        } else {
          input.type = 'password';
          icon.classList.remove('ti-eye');
          icon.classList.add('ti-eye-off');
        }
      });

      toggle.addEventListener('mousedown', (e) => {
        e.preventDefault();
      });
    });
  }

  /**
   * Verifica a força da senha
   */
  checkPasswordStrength(password) {
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
   * Atualiza o indicador visual da força da senha
   */
  updatePasswordStrength(strength) {
    if (!this.strengthContainer) return;
    
    // LIMPAR COMPLETAMENTE
    this.strengthContainer.textContent = '';
    this.strengthContainer.className = 'password-strength-feedback';
    
    if (strength.score > 0) {
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

      const div = document.createElement('div');
      div.style.color = colors[strength.level];
      div.style.fontSize = '0.875rem';
      div.style.marginTop = '2px';
      
      const icon = document.createElement('i');
      icon.className = 'ti ti-shield';
      
      const span = document.createElement('span');
      span.innerHTML = ` Força: <strong>${labels[strength.level]}</strong>`;
      
      div.appendChild(icon);
      div.appendChild(span);
      this.strengthContainer.appendChild(div);
      
      if (strength.feedback.length > 0) {
        const small = document.createElement('small');
        small.style.display = 'block';
        small.style.marginTop = '2px';
        small.style.fontSize = '0.75rem';
        small.textContent = `Adicione: ${strength.feedback.join(', ')}`;
        this.strengthContainer.appendChild(small);
      }
    }
  }

  /**
   * Atualiza feedback de confirmação de senha
   */
  updatePasswordMatch(senha, confirmacao) {
    if (!this.matchContainer) return;
    
    // LIMPAR COMPLETAMENTE
    this.matchContainer.textContent = '';
    this.matchContainer.className = 'password-match-feedback';
    
    if (confirmacao.length > 0) {
      const matches = senha === confirmacao;
      
      const div = document.createElement('div');
      div.style.fontSize = '0.875rem';
      div.style.marginTop = '2px';
      div.style.display = 'flex';
      div.style.alignItems = 'center';
      div.style.gap = '6px';
      
      const icon = document.createElement('i');
      icon.className = matches ? 'ti ti-check' : 'ti ti-x';
      icon.style.color = matches ? '#198754' : '#dc3545';
      
      const span = document.createElement('span');
      span.style.color = matches ? '#198754' : '#dc3545';
      span.textContent = matches ? 'Senhas conferem' : 'Senhas não conferem';
      
      div.appendChild(icon);
      div.appendChild(span);
      this.matchContainer.appendChild(div);
      
      if (matches) {
        this.matchContainer.classList.add('valid');
      } else {
        this.matchContainer.classList.add('invalid');
      }
    }
  }

  /**
   * Valida tudo e atualiza UI
   */
  validateAll() {
    const senha = this.senhaInput.value;
    const confirmacao = this.confirmInput.value;
    
    // Reset states
    this.senhaInput.classList.remove('is-valid', 'is-invalid');
    this.confirmInput.classList.remove('is-valid', 'is-invalid');
    
    let isValid = true;
    
    // Validação de força da senha
    const passwordStrength = this.checkPasswordStrength(senha);
    this.updatePasswordStrength(passwordStrength);
    
    // Estado da senha (apenas força, sem mensagem adicional)
    if (senha) {
      if (passwordStrength.score < 3) {
        this.senhaInput.classList.add('is-invalid');
        isValid = false;
      } else {
        this.senhaInput.classList.add('is-valid');
      }
    }
    
    // Atualizar feedback de confirmação
    this.updatePasswordMatch(senha, confirmacao);
    
    // Estado da confirmação
    if (confirmacao) {
      if (senha !== confirmacao) {
        this.confirmInput.classList.add('is-invalid');
        isValid = false;
      } else if (passwordStrength.score >= 3) {
        this.confirmInput.classList.add('is-valid');
      }
    }
    
    // Se há senhas mas confirmação vazia
    if (senha && !confirmacao) {
      isValid = false;
    }
    
    return isValid;
  }
}

// Auto-init ao carregar documento
document.addEventListener('DOMContentLoaded', function() {
  try {
    // Detectar modais e formulários de senha
    
    // Modal de criar usuário
    const userCreateForm = document.querySelector('#userCreateForm');
    if (userCreateForm) {
      const novaSenhaInput = document.querySelector('#senhaUser');
      const confirmarSenhaInput = document.querySelector('#senhaConfirmUser');
      
      // Só inicializa se ambos os inputs existem
      if (novaSenhaInput && confirmarSenhaInput) {
        try {
          new PasswordValidator('#senhaUser', '#senhaConfirmUser', '#userCreateForm');
        } catch (err) {
          console.error('Erro ao inicializar PasswordValidator para userCreateForm:', err);
        }
      }
    }
    
    // Modal de alterar senha (com IDs diferentes)
    const senhaForm = document.querySelector('#senhaForm');
    if (senhaForm) {
      const novaSenhaInput = document.querySelector('#novaSenha');
      const confirmarSenhaInput = document.querySelector('#confirmarSenha');
      
      // Só inicializa se ambos os inputs existem
      if (novaSenhaInput && confirmarSenhaInput) {
        try {
          new PasswordValidator('#novaSenha', '#confirmarSenha', '#senhaForm');
        } catch (err) {
          console.error('Erro ao inicializar PasswordValidator para senhaForm:', err);
        }
      }
    }
  } catch (err) {
    console.error('Erro geral no password-validation.js:', err);
  }
});
