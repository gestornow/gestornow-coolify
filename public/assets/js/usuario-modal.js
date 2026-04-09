(function(){
    // Modal-specific behaviors: CEP lookup, form validation, auto-open on server errors
    document.addEventListener('DOMContentLoaded', function(){
        const modalEl = document.getElementById('userModal');
        if (!modalEl) return;

        // If modal has data-open-on-errors attribute, open it
        const openOnErrors = modalEl.getAttribute('data-open-on-errors');
        if (openOnErrors && openOnErrors === '1'){
            const m = new bootstrap.Modal(modalEl);
            m.show();
        }

        // Apply masks to elements inside modal
        if (window.utils && typeof window.utils.initMasks === 'function'){
            window.utils.initMasks(modalEl);
        }

        // Initialize percent masks (Cleave) for .mask-percent if Cleave available
        function initPercentMasks(root){
            if (typeof Cleave === 'undefined') return;
            root.querySelectorAll('.mask-percent').forEach(function(el){
                if (el.__cleave) return;
                el.__cleave = new Cleave(el, {numeral:true, numeralDecimalMark:',', delimiter:'.', numeralDecimalScale:2, numeralIntegerScale:3});
            });
        }
        initPercentMasks(modalEl);

        // CEP auto-fill
        const cep = modalEl.querySelector('#cep');
        if (cep){
            cep.addEventListener('blur', async function(){
                const val = cep.value || '';
                const cepHelp = modalEl.querySelector('#cepHelp');
                try{
                    const data = await window.utils.lookupCEP(val);
                    const endereco = modalEl.querySelector('#endereco');
                    const bairro = modalEl.querySelector('#bairro');
                    const cidade = modalEl.querySelector('#cidade');
                    const estado = modalEl.querySelector('#estado');
                    if (endereco) endereco.value = data.endereco;
                    if (bairro) bairro.value = data.bairro;
                    if (cidade) cidade.value = data.cidade;
                    if (estado) estado.value = data.estado;
                    if (cepHelp) { cepHelp.classList.add('d-none'); cepHelp.textContent = ''; }
                } catch(err){
                    console.warn('CEP lookup failed', err.message);
                    if (cepHelp){ cepHelp.classList.remove('d-none'); cepHelp.textContent = 'CEP inválido'; }
                }
            });
        }

        // Form submit validation (minimal)
        const form = modalEl.querySelector('#userCreateForm');
        if (form){
            // Additional field validations
            const cpf = modalEl.querySelector('#cpf');
            const cpfHelp = modalEl.querySelector('#cpfHelp');
            if (cpf){
                cpf.addEventListener('blur', function(){
                    const v = cpf.value || '';
                    if (v.trim() === '') { if (cpfHelp){ cpfHelp.classList.add('d-none'); cpfHelp.textContent=''; } return; }
                    if (!window.utils.validateCPF(v)){
                        if (cpfHelp){ cpfHelp.classList.remove('d-none'); cpfHelp.textContent = 'CPF inválido'; }
                        cpf.classList.add('is-invalid');
                    } else {
                        if (cpfHelp){ cpfHelp.classList.add('d-none'); cpfHelp.textContent=''; }
                        cpf.classList.remove('is-invalid');
                    }
                });
            }

            const com = modalEl.querySelector('#comissao');
            const comHelp = modalEl.querySelector('#comissaoHelp');
            if (com){
                com.addEventListener('blur', function(){
                    // parse number: remove thousand separators and replace comma
                    let raw = com.value || '';
                    raw = raw.replace(/\./g,'').replace(',', '.');
                    const num = parseFloat(raw);
                    if (!isNaN(num) && num > 100){
                        if (comHelp){ comHelp.classList.remove('d-none'); comHelp.textContent = 'Valor não pode ser maior que 100'; }
                        com.classList.add('is-invalid');
                    } else {
                        if (comHelp){ comHelp.classList.add('d-none'); comHelp.textContent = ''; }
                        com.classList.remove('is-invalid');
                    }
                });
            }

            form.addEventListener('submit', function(e){
                const login = form.querySelector('#login');
                const nome = form.querySelector('#nome');
                let ok = true;
                if (!login || !login.value.trim()){
                    ok = false; login.classList.add('is-invalid');
                }
                if (!nome || !nome.value.trim()){
                    ok = false; nome.classList.add('is-invalid');
                }

                // enforce CPF validity if provided
                if (cpf && cpf.value.trim() && !window.utils.validateCPF(cpf.value)){
                    ok = false; if (cpfHelp){ cpfHelp.classList.remove('d-none'); cpfHelp.textContent = 'CPF inválido'; } cpf.classList.add('is-invalid');
                }

                // enforce CEP validity if provided
                const cepEl = modalEl.querySelector('#cep');
                const cepHelpEl = modalEl.querySelector('#cepHelp');
                if (cepEl && cepEl.value.trim()){
                    const cleaned = (cepEl.value || '').replace(/\D/g,'');
                    if (cleaned.length !== 8){ ok = false; if (cepHelpEl){ cepHelpEl.classList.remove('d-none'); cepHelpEl.textContent = 'CEP inválido'; } cepEl.classList.add('is-invalid'); }
                }

                // enforce commission <= 100
                if (com && com.value.trim()){
                    let raw = com.value.replace(/\./g,'').replace(',', '.');
                    const num = parseFloat(raw);
                    if (!isNaN(num) && num > 100){ ok = false; if (comHelp){ comHelp.classList.remove('d-none'); comHelp.textContent = 'Valor não pode ser maior que 100'; } com.classList.add('is-invalid'); }
                }

                if (!ok){ e.preventDefault(); return false; }
                // everything fine, let server handle creation
            });
        }

    });
})();
