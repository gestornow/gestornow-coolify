$(function(){
    const $form = $('#formAuthentication');
    const $cnpj = $form.find('input.mask-cnpj');
    const $submit = $form.find('button[type="submit"]');
    
    // (captura inicial; será recalculado dentro de updateState também)
    let $serverError = $cnpj.siblings('.invalid-feedback').not('.cnpj-live-feedback');
    if($serverError.length){
        $cnpj.data('server-original', (function(v){return (v||'').replace(/\D+/g,'');})($cnpj.val()));
    }

    function onlyDigits(v){ return (v||'').replace(/\D+/g,''); }
    function allEqual(d){ return /^([0-9])\1+$/.test(d); }
    function validateCNPJ(v){
        const d = onlyDigits(v);
        if(d.length!==14 || allEqual(d)) return false;
        const calc = len=>{ let sum=0,pos=len-7; for(let i=len;i>=1;i--){ sum+=parseInt(d[len-i])*pos--; if(pos<2) pos=9; } let r=sum%11; return (r<2)?0:11-r; };
        if(calc(12)!==parseInt(d[12])) return false; if(calc(13)!==parseInt(d[13])) return false; return true; }
    function maskCNPJ(v){
        const d = onlyDigits(v).slice(0,14);
        const s1=d.slice(0,2),s2=d.slice(2,5),s3=d.slice(5,8),s4=d.slice(8,12),s5=d.slice(12,14);
        let out=s1; if(s2) out+='.'+s2; if(s3) out+='.'+s3; if(s4) out+='/'+s4; if(s5) out+='-'+s5; return out;
    }
    function updateState(){
        // Reavaliar sempre o erro de backend (caso seja re-renderizado ou removido)
        $serverError = $cnpj.siblings('.invalid-feedback').not('.cnpj-live-feedback');
        const val = $cnpj.val();
        const digits = onlyDigits(val);
        const structuralValid = digits.length===14 && validateCNPJ(val);
        
        // Gerenciar feedback dinâmico
        const existing = $cnpj.siblings('.cnpj-live-feedback');
        function setFeedback(msg){
            if(existing.length) existing.remove();
            if(msg){
                // Não remover possíveis mensagens server-side (sem a classe específica)
                $('<div class="invalid-feedback cnpj-live-feedback d-block" style="margin-top:4px;">'+msg+'</div>')
                    .insertAfter($cnpj);
            }
        }
        
        // Caso vazio
        if(!val){
            $cnpj.removeClass('is-invalid is-valid');
            existing.remove();
            if($serverError && $serverError.length) $serverError.addClass('d-none');
            // NÃO desabilitar botão se CNPJ estiver vazio - deixar validação server-side
            return;
        }

        // Backend duplicidade tem prioridade e permanece mesmo se estruturalmente válido
        if($serverError && $serverError.length){
            // Se ainda sem referência original, armazena agora
            if(!$cnpj.data('server-original')){
                $cnpj.data('server-original', digits);
            }
            const original = $cnpj.data('server-original');
            const sameValue = original && original === digits;
            if(sameValue && !$serverError.hasClass('d-none')){
                // Mantém erro do backend visível e classe inválida
                $serverError.removeClass('d-none');
                $cnpj.addClass('is-invalid').removeClass('is-valid');
                existing.remove();
                return; // Não prossegue para validação local
            } else if(!sameValue){
                // Valor mudou -> oculta erro backend só visualmente
                $serverError.addClass('d-none');
            }
        }

        if(structuralValid){
            $cnpj.addClass('is-valid').removeClass('is-invalid');
            existing.remove();
        } else {
            $cnpj.addClass('is-invalid').removeClass('is-valid');
            if(digits.length < 14) setFeedback('CNPJ incompleto.'); 
            else setFeedback('CNPJ inválido.');
        }
        // NÃO desabilitar o botão aqui - deixar a validação acontecer no servidor
    }
    if($cnpj.length){
        $cnpj.on('input', function(){
            const caret = this.selectionStart;
            const masked = maskCNPJ($cnpj.val());
            $cnpj.val(masked);
            try { this.setSelectionRange(caret, caret); } catch(e){}
            updateState();
        });
        $cnpj.on('blur', updateState);
        updateState();
    }

    // Handler de submit do formulário
    $form.on('submit', function(e){
        // Verificar se todos os campos obrigatórios estão preenchidos
        let formValido = true;
        const camposObrigatorios = $form.find('input[required]');
        
        camposObrigatorios.each(function(){
            const valor = $(this).val().trim();
            if(!valor){
                formValido = false;
            }
        });
        
        if(!formValido){
            e.preventDefault();
            return false;
        }
        
        // Desabilitar botão e mostrar loading APENAS uma vez
        if(!$submit.data('submitting')){
            $submit.data('submitting', true);
            $submit.prop('disabled', true);
            const originalText = $submit.html();
            $submit.data('originalText', originalText);
            $submit.html('<div class="d-flex align-items-center"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Enviando...</div>');
        }
        
        return true;
    });
});