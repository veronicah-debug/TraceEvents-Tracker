// main.js - small front-end helpers for every page.
// Data and login live in PHP + MySQL, so nothing is stored in the browser here.

document.addEventListener('DOMContentLoaded', function () {

  // 1. Highlight the nav link of the page you are on
  var current = location.pathname.split('/').pop() || 'index.php';
  document.querySelectorAll('nav a').forEach(function (link) {
    if (link.getAttribute('href') === current) {
      link.classList.add('active');
    }
  });

  // 2. Ask before approving an event (management page)
  document.querySelectorAll('form[action="process-approve.php"]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!confirm('Approve this event?')) {
        e.preventDefault();      // cancel: the form is not sent
      }
    });
  });

  // 3. Hide the red login error after 5 seconds
  var err = document.getElementById('loginError');
  if (err) {
    setTimeout(function () { err.style.display = 'none'; }, 5000);
  }

  // 4. Stop a form being sent twice by a double click
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function () {
      var btn = form.querySelector('button[type="submit"]');
      if (btn) {
        setTimeout(function () { btn.disabled = true; }, 0);
      }
    });
  });

});