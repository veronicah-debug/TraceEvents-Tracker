// js/main.js
document.addEventListener('DOMContentLoaded', () => {
  const navToggle = document.getElementById('navToggle');
  const navLinks = document.getElementById('navLinks');

  if (navToggle && navLinks) {
    navToggle.addEventListener('click', () => {
      navLinks.classList.toggle('open');
    });
  }

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const msg = form.getAttribute('data-confirm') || 'Confirm this action?';
      if (!window.confirm(msg)) {
        event.preventDefault();
      }
    });
  });
});