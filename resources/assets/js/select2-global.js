// Initialize Select2 for all select elements with class 'select2'
$(document).ready(function() {
  // Initialize Select2
  if (typeof $.fn.select2 !== 'undefined') {
    initializeSelect2();
  }
  
  // Re-initialize Select2 when new elements are added dynamically
  $(document).on('DOMNodeInserted', function(e) {
    if ($(e.target).is('select.select2') || $(e.target).find('select.select2').length) {
      setTimeout(function() {
        initializeSelect2();
      }, 100);
    }
  });
});

// Function to initialize Select2
function initializeSelect2() {
  $('select.select2').not('.select2-hidden-accessible').select2({
    width: '100%',
    placeholder: 'Pesquisar...',
    allowClear: true,
    language: 'pt-BR'
  });
}

// Global function to reinitialize Select2 (can be called from anywhere)
window.reinitializeSelect2 = function() {
  if (typeof $.fn.select2 !== 'undefined') {
    initializeSelect2();
  }
};
