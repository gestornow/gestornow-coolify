<style>
     .gn-logo-wrapper {display:flex;align-items:center;gap:.6rem; justify-content:center; margin-bottom:15px;}
     .gn-logo-wrapper .gn-logo-img {display:block;max-height:45px;width:auto;}
     .gn-logo-wrapper .gn-logo-text {font-size:22px;line-height:1.1;font-weight:600;color:#2196f3;}
     /* Override framework overflow that estava cortando */
     .app-brand-logo {overflow:visible !important;}
     /* Caso esteja dentro de menu colapsado e queira mostrar só o ícone */
     .layout-menu-collapsed .gn-logo-text {display:none;}
</style>
<div class="gn-logo-wrapper">
     <img src="{{ asset('assets/img/gestor_now_transparent.png') }}" 
                alt="GestorNow Logo" 
                class="app-brand-logo gn-logo-img"/>
</div>

{{-- Macro específica para o menu lateral com logo e texto --}}
@php
$menuLogo = '<style>
     .gn-menu-logo-wrapper {
          display: flex;
          align-items: center;
          gap: 0.8rem;
          justify-content: flex-start;
          padding: 0 1rem;
     }
     .gn-menu-logo-wrapper .gn-menu-logo-img {
          display: block;
          max-height: 35px;
          width: auto;
     }
     .gn-menu-logo-wrapper .gn-menu-logo-text {
          font-size: 20px;
          line-height: 1.1;
          font-weight: 600;
          color: #5f5b6e;
          margin: 0;
     }
     .layout-menu-collapsed .gn-menu-logo-text {
          display: none;
     }
</style>
<div class="gn-menu-logo-wrapper">
     <img src="' . asset('assets/img/gestor_now_transparent2.png') . '" 
          alt="GestorNow Logo" 
          class="app-brand-logo gn-menu-logo-img"/>
     <span class="gn-menu-logo-text">GestorNow</span>
</div>';
@endphp
