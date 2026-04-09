// pages-criar-senha.js
// Responsável por validação em tempo real da confirmação de senha na tela Criar Senha
// Incluindo validação de força de senha e feedback visual

(function(){
  document.addEventListener('DOMContentLoaded', function(){
    const form = document.querySelector('form[action*="criar.senha"], form[action$="criar-senha"], form');
    if(!form) return;
    const pwd = form.querySelector('#senha');
    const pwd2 = form.querySelector('#senha_confirmation');
    const submitBtn = form.querySelector('button[type="submit"]');
    const checkbox = form.querySelector('#aceita_termos');
    if(!pwd || !pwd2 || !submitBtn) return;

    // Inicializar toggle de senha
    initPasswordToggle();
    
    // Validação CNPJ (se existir)
    const cnpjInput = form.querySelector('input.cnpj');
    if(cnpjInput) {
      function onlyDigits(v){ return (v||'').replace(/\D+/g,''); }
      function allEqual(d){ return /^([0-9])\1+$/.test(d); }
      function validateCNPJ(cnpj){
        const d = onlyDigits(cnpj);
        if(d.length !== 14 || allEqual(d)) return false;
        const calc = len => { let sum=0, pos=len-7; for(let i=len;i>=1;i--){ sum += parseInt(d[len-i]) * pos--; if(pos<2) pos=9; } let r=sum%11; return (r<2)?0:11-r; };
        if(calc(12) !== parseInt(d[12])) return false;
        if(calc(13) !== parseInt(d[13])) return false;
        return true;
      }
      function updateCNPJ(){
        const digits = onlyDigits(cnpjInput.value);
        const ok = digits.length === 14 && validateCNPJ(cnpjInput.value);
        validateAll(); // Revalida tudo quando CNPJ muda
      }
      cnpjInput.addEventListener('input', updateCNPJ);
      cnpjInput.addEventListener('blur', updateCNPJ);
    }

    /**
     * Inicializa o toggle de visibilidade das senhas
     */
    function initPasswordToggle() {
      document.querySelectorAll('.password-toggle').forEach(toggle => {
        // Remover qualquer listener anterior
        toggle.removeEventListener('click', togglePassword);
        
        // Adicionar novo listener
        toggle.addEventListener('click', togglePassword);
        toggle.addEventListener('mousedown', function(e) {
          e.preventDefault(); // Previne foco no input
        });
        
        function togglePassword(e) {
          e.preventDefault();
          e.stopPropagation();
          
          const input = this.parentElement.querySelector('input[type="password"], input[type="text"]');
          const icon = this.querySelector('i');
          
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
        }
      });
    }

    /**
     * Verifica a força da senha
     */
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
     * Atualiza o indicador visual da força da senha
     */
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
          <i class="ti ti-shield"></i> Força: ${labels[strength.level]}
          ${strength.feedback.length > 0 ? `<br><small>Adicione: ${strength.feedback.join(', ')}</small>` : ''}
        </div>
      ` : '';
    }

    /**
     * Atualiza feedback de confirmação de senha
     */
    function updatePasswordMatch(senha, confirmacao) {
      const feedback = document.getElementById('confirmFeedback');
      if (!feedback) return;
      
      if (confirmacao.length > 0) {
        const matches = senha === confirmacao;
        
        if (matches) {
          feedback.innerHTML = '<div style="color: #198754;"><i class="ti ti-check"></i> Senhas conferem</div>';
        } else {
          feedback.innerHTML = '<div style="color: #dc3545;"><i class="ti ti-x"></i> Senhas não conferem</div>';
        }
      } else {
        feedback.innerHTML = '';
      }
    }

    function ensureFeedbackContainer(input){
      let wrap = input.closest('.input-group');
      if(!wrap) wrap = input.parentElement;
      let fb = wrap.querySelector('.invalid-feedback._auto-confirm');
      if(!fb){
        fb = document.createElement('div');
        fb.className = 'invalid-feedback _auto-confirm';
        wrap.appendChild(fb);
      }
      return fb;
    }

    function clearAutoFeedback(input){
      const wrap = input.closest('.input-group') || input.parentElement;
      const fb = wrap.querySelector('.invalid-feedback._auto-confirm');
      if(fb) fb.remove();
    }

    function validateAll(){
      const v1 = pwd.value;
      const v2 = pwd2.value;
      
      // Reset states e remover feedback antigos
      [pwd, pwd2].forEach(i=>{ i.classList.remove('is-valid','is-invalid'); clearAutoFeedback(i); });

      let block = false;

      // Validação de força da senha
      const passwordStrength = checkPasswordStrength(v1);
      updatePasswordStrength(passwordStrength);

      // Validação da senha (apenas força, sem mensagem adicional)
      if(v1 && passwordStrength.score < 3){
        pwd.classList.add('is-invalid');
        block = true;
      } else if(v1 && passwordStrength.score >= 3){
        pwd.classList.add('is-valid');
      }

      // Atualizar feedback de confirmação
      updatePasswordMatch(v1, v2);

      // Confirmação de senha (apenas se o usuário começou a digitar)
      if(v2){
        if(v1 !== v2){
          pwd2.classList.add('is-invalid');
          block = true;
        } else if(passwordStrength.score >= 3 && v1 === v2){
          pwd2.classList.add('is-valid');
        }
      }

      // Verificar termos de uso
      const termsAccepted = checkbox ? checkbox.checked : true;
      if (!termsAccepted) {
        block = true;
      }

      // Atualizar botão
      submitBtn.disabled = block;
      
      if (block) {
        submitBtn.classList.add('btn-secondary');
        submitBtn.classList.remove('btn-primary');
      } else {
        submitBtn.classList.add('btn-primary');
        submitBtn.classList.remove('btn-secondary');
      }
    }

    pwd.addEventListener('input', validateAll);
    pwd2.addEventListener('input', validateAll);
    
    // Verificar termos
    if (checkbox) {
      checkbox.addEventListener('change', validateAll);
    }
    
    // Primeira avaliação (caso browser recupere valores)
    validateAll();
  });
})();
