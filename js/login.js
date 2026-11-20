var HOME = { student: 'events.html', organizer: 'organizer.html', management: 'management.html' };
var form = document.getElementById('loginForm');

if (Auth.user()) { location.href = HOME[Auth.user().role]; }   // already logged in

form.addEventListener('submit', function (e) {
  e.preventDefault();
  var role = form.querySelector('input[name="role"]:checked').value;
  var user = document.getElementById('lgUser').value.trim();
  var pass = document.getElementById('lgPass').value;

  if (!user || !pass) {
    document.getElementById('lgError').textContent = 'Enter your username and password.';
    return;
  }
  Auth.login(role, user);          // demo: any details work
  location.href = HOME[role];
});