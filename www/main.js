// RAG Application JavaScript

// Utility functions
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Form validation helpers
function validateEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}

function validatePassword(password) {
  return password.length >= 8;
}

// Auto-submit forms with debouncing
function setupAutoSubmit() {
  const forms = document.querySelectorAll('form[data-auto-submit]');
  
  forms.forEach(form => {
    const inputs = form.querySelectorAll('input, select');
    let timeout;
    
    inputs.forEach(input => {
      input.addEventListener('input', () => {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
          if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
          } else {
            form.submit();
          }
        }, 600);
      });
    });
  });
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  setupAutoSubmit();
  
  // Focus first input on auth pages
  if (document.body.classList.contains('auth')) {
    const firstInput = document.querySelector('input[type="email"], input[type="text"]');
    if (firstInput) {
      firstInput.focus();
    }
  }
  
  // Confirm delete actions
  const deleteButtons = document.querySelectorAll('[data-confirm]');
  deleteButtons.forEach(button => {
    button.addEventListener('click', function(e) {
      const message = this.getAttribute('data-confirm') || 'Are you sure?';
      if (!confirm(message)) {
        e.preventDefault();
      }
    });
  });
});
