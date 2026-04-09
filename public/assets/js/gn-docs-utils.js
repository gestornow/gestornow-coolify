/* Global GestorNow utils: Masking & Validation for CPF/CNPJ */
(function(window){
  const ONLY_DIGITS = /\D+/g;
  function onlyDigits(v){ return (v||'').replace(ONLY_DIGITS,''); }

  function maskCPF(v){
    const d = onlyDigits(v).slice(0,11);
    const p1 = d.slice(0,3);
    const p2 = d.slice(3,6);
    const p3 = d.slice(6,9);
    const p4 = d.slice(9,11);
    let out = p1; if(p2) out += '.'+p2; if(p3) out += '.'+p3; if(p4) out += '-'+p4; return out; 
  }

  function maskCNPJ(v){
    const d = onlyDigits(v).slice(0,14);
    const s1=d.slice(0,2), s2=d.slice(2,5), s3=d.slice(5,8), s4=d.slice(8,12), s5=d.slice(12,14);
    let out = s1; if(s2) out += '.'+s2; if(s3) out += '.'+s3; if(s4) out += '/'+s4; if(s5) out += '-'+s5; return out; }

  function allEqual(d){ return /^([0-9])\1+$/.test(d); }

  function validateCPF(cpf){
    const d = onlyDigits(cpf);
    if(d.length !== 11 || allEqual(d)) return false;
    let sum=0; for(let i=0;i<9;i++) sum += parseInt(d[i])*(10-i); let r = (sum*10)%11; if(r===10||r===11) r=0; if(r!==parseInt(d[9])) return false;
    sum=0; for(let i=0;i<10;i++) sum += parseInt(d[i])*(11-i); r=(sum*10)%11; if(r===10||r===11) r=0; return r===parseInt(d[10]); }

  function validateCNPJ(cnpj){
    const d = onlyDigits(cnpj);
    if(d.length!==14 || allEqual(d)) return false;
    const calc = (len)=>{ let sum=0, pos=len-7; for(let i=len;i>=1;i--){ sum += parseInt(d[len-i]) * pos--; if(pos<2) pos=9; } let r = sum % 11; return (r<2)?0:11-r; };
    const d1 = calc(12); if(d1!==parseInt(d[12])) return false; const d2 = calc(13); return d2===parseInt(d[13]); }

  function attachMaskAndValidation(input, type){
    if(!input) return; const isCNPJ = type==='cnpj'; const masker = isCNPJ?maskCNPJ:maskCPF; const validator = isCNPJ?validateCNPJ:validateCPF;
    input.addEventListener('input', e=>{ const caret = input.selectionStart; input.value = masker(input.value); input.setSelectionRange(caret, caret); });
    input.addEventListener('blur', ()=>{ const valid = validator(input.value); setValidityUI(input, valid, isCNPJ? 'CNPJ inválido' : 'CPF inválido'); });
  }

  function setValidityUI(el, ok, message){
    const existing = el.parentElement.querySelector('.invalid-feedback._auto');
    if(existing) existing.remove();
    el.classList.remove('is-invalid','is-valid');
    if(ok){ el.classList.add('is-valid'); }
    else { el.classList.add('is-invalid'); const div=document.createElement('div'); div.className='invalid-feedback _auto'; div.textContent=message; el.parentElement.appendChild(div);} }

  // expose globally
  window.GN = window.GN || {};
  window.GN.mask = { cpf: maskCPF, cnpj: maskCNPJ };
  window.GN.validate = { cpf: validateCPF, cnpj: validateCNPJ };
  window.GN.attachDocMask = function(selector, type){ const el=document.querySelector(selector); attachMaskAndValidation(el, type); };
  window.GN.attachAllDocMasks = function(ctx){
    const root = ctx || document;
    root.querySelectorAll('input.cnpj').forEach(el=>attachMaskAndValidation(el,'cnpj'));
    root.querySelectorAll('input.cpf').forEach(el=>attachMaskAndValidation(el,'cpf'));
  };

  // Auto attach on DOMContentLoaded
  document.addEventListener('DOMContentLoaded', function(){
    window.GN.attachAllDocMasks();
  });
})(window);
