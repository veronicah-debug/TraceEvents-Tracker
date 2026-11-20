// auth.js - include on EVERY page: <script src="js/auth.js" defer></script>
// 1) Handles login (role is detected from the account, not chosen by the user)
// 2) Builds the nav bar depending on who is logged in

// DEMO accounts. Replace with a fetch() to your backend (PHP/XAMPP) later;
// the server should return the role so it can't be faked in the browser.
const USERS = [
  { id: "123456",              password: "student123", role: "student",    name: "Student One" },
  { id: "student@gmail.com",   password: "student123", role: "student",    name: "Student One" },
  { id: "organizer",           password: "org123",     role: "organizer",  name: "Club Organizer" },
  { id: "organizer@gmail.com", password: "org123",     role: "organizer",  name: "Club Organizer" },
  { id: "admin",               password: "admin123",   role: "management", name: "Management" },
  { id: "admin@gmail.com",     password: "admin123",   role: "management", name: "Management" },
];

// Where each role lands after login. Change file names to match yours.
const HOME_FOR_ROLE = {
  student:    "my-tickets.html",
  organizer:  "organizer.html",
  management: "management.html",
};

function getSession() {
  try { return JSON.parse(localStorage.getItem("session")); } catch (e) { return null; }
}

function logout() {
  localStorage.removeItem("session");
  window.location.href = "index.html";
}

function handleLogin(e) {
  e.preventDefault();
  const id = document.getElementById("loginId").value.trim().toLowerCase();
  const pw = document.getElementById("loginPassword").value;
  const err = document.getElementById("loginError");

  const user = USERS.find(u => u.id.toLowerCase() === id && u.password === pw);
  if (!user) {
    err.textContent = "Incorrect details. Check your admission number / email and password.";
    return;
  }
  localStorage.setItem("session", JSON.stringify({ id: user.id, name: user.name, role: user.role }));
  window.location.href = HOME_FOR_ROLE[user.role];
}

// Optional: call on protected pages, e.g. requireRole("organizer")
function requireRole(role) {
  const s = getSession();
  if (!s || s.role !== role) window.location.href = "index.html";
}

// Build the nav: Home, About, Events, Contact + role link + Log out
function buildNav() {
  const nav = document.getElementById("navLinks");
  if (!nav) return;
  const s = getSession();
  let links = `
    <li><a href="index.html">Home</a></li>
    <li><a href="about.html">About</a></li>
    <li><a href="events.html">Events</a></li>
    <li><a href="contact.html">Contact</a></li>`;
  if (s) {
    const label = { student: "My Tickets", organizer: "Organizer", management: "Management" }[s.role];
    links += `<li><a href="${HOME_FOR_ROLE[s.role]}">${label}</a></li>
              <li><a href="#" onclick="logout();return false;">Log out</a></li>`;
  }
  nav.innerHTML = links;
}

document.addEventListener("DOMContentLoaded", () => {
  buildNav();
  const form = document.getElementById("loginForm");
  if (form) {
    const s = getSession();
    if (s) { // already logged in: skip the form
      window.location.href = HOME_FOR_ROLE[s.role];
      return;
    }
    form.addEventListener("submit", handleLogin);
  }
});