// app.js: link this on EVERY page, before the page's own script.
var CLUB = 'Strathmore Tech Club';
var ME = { name: 'Amani Kariuki', adm: '165806' };

var Store = (function () {
  var KEY = 'tet-data-v1';

  function seed() {
    var events = [
      { id: 1, title: 'Intro to Web Development Workshop', club: CLUB, date: '2026-11-20', time: '14:00', venue: 'Computer Lab 2', price: 0, capacity: 30, status: 'approved',
        desc: 'A hands-on session covering HTML, CSS and a first JavaScript page.',
        budget: { items: [{ name: 'Refreshments', amount: 6000 }, { name: 'Printed handouts', amount: 2500 }], price: 0, expected: 25 } },
      { id: 2, title: 'Campus Hackathon 2026', club: CLUB, date: '2026-12-05', time: '09:00', venue: 'Auditorium Foyer', price: 500, capacity: 40, status: 'approved',
        desc: 'A 12-hour team build competition with mentors, meals and prizes.',
        budget: { items: [{ name: 'Venue setup', amount: 15000 }, { name: 'Meals', amount: 42000 }, { name: 'Prizes', amount: 30000 }], price: 500, expected: 35 } },
      { id: 3, title: 'Inter-Club Charity Gala', club: 'Student Affairs Council', date: '2026-12-12', time: '18:30', venue: 'Main Hall', price: 1500, capacity: 30, status: 'approved',
        desc: 'An evening of performances and a charity auction.',
        budget: { items: [{ name: 'Sound and lighting', amount: 55000 }, { name: 'Catering', amount: 90000 }], price: 1500, expected: 28 } },
      { id: 4, title: 'Career Talk: Data Careers in Kenya', club: CLUB, date: '2027-01-14', time: '16:00', venue: 'Lecture Theatre 1', price: 200, capacity: 40, status: 'pending',
        desc: 'Industry speakers discuss data analyst and database roles. Awaiting approval.',
        budget: { items: [{ name: 'Speaker transport', amount: 12000 }, { name: 'Refreshments', amount: 18000 }], price: 200, expected: 35 } }
    ];
    var names = ['Brian Otieno', 'Cynthia Mwangi', 'David Kamau', 'Esther Achieng', 'Faith Njeri', 'George Mutua'];
    var tickets = [];
    [[1, 8, 3], [2, 8, 2], [3, 6, 0]].forEach(function (s) {
      for (var i = 0; i < s[1]; i++) {
        tickets.push({ code: 'TET-0' + s[0] + '-' + (1000 + tickets.length * 7).toString(36).toUpperCase(), eventId: s[0],
          status: i < s[2] ? 'Used' : 'Confirmed', owner: names[i % names.length], adm: String(160000 + tickets.length * 37) });
      }
    });
    tickets[3].owner = ME.name; tickets[3].adm = ME.adm;
    return { events: events, tickets: tickets };
  }

  function load() {
    try {
      var d = JSON.parse(localStorage.getItem(KEY));
      if (d && d.events && d.tickets) return d;
    } catch (e) { }
    return seed();
  }

  var data = load();
  return {
    events: function () { return data.events; },
    tickets: function () { return data.tickets; },
    event: function (id) { return data.events.filter(function (e) { return e.id === +id; })[0]; },
    save: function () { try { localStorage.setItem(KEY, JSON.stringify(data)); } catch (e) { } },
    reset: function () { try { localStorage.removeItem(KEY); } catch (e) { } data = seed(); }
  };
})();

function kes(n) { return 'KES ' + Math.round(n).toLocaleString('en-KE'); }

function esc(t) {
  return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}

function registered(eventId) {
  return Store.tickets().filter(function (t) { return t.eventId === eventId && t.status !== 'Cancelled'; }).length;
}

function attended(eventId) {
  return Store.tickets().filter(function (t) { return t.eventId === eventId && t.status === 'Used'; }).length;
}

function calcBudget(e) {
  var cost = e.budget.items.reduce(function (s, i) { return s + (+i.amount || 0); }, 0);
  var income = e.budget.price * e.budget.expected;
  return { cost: cost, income: income, net: income - cost, breakEven: e.budget.price > 0 ? Math.ceil(cost / e.budget.price) : null };
}

function pill(status) {
  var cls = { Confirmed: 'p-ok', Used: 'p-info', Pending: 'p-warn', Cancelled: 'p-bad', approved: 'p-ok', pending: 'p-warn', rejected: 'p-bad' }[status] || 'p-info';
  var label = { approved: 'Approved', pending: 'Awaiting approval', rejected: 'Rejected' }[status] || status;
  return '<span class="pill ' + cls + '">' + label + '</span>';
}

function toast(message) {
  var t = document.getElementById('toast');
  if (!t) { t = document.createElement('div'); t.id = 'toast'; t.className = 'toast'; document.body.appendChild(t); }
  t.textContent = message;
  t.hidden = false;
  clearTimeout(toast.timer);
  toast.timer = setTimeout(function () { t.hidden = true; }, 2600);
}
// ---------- Login state ----------
var Auth = {
  user: function () { try { return JSON.parse(localStorage.getItem('tet-user')); } catch (e) { return null; } },
  login: function (role, id) { try { localStorage.setItem('tet-user', JSON.stringify({ role: role, id: id })); } catch (e) { } },
  logout: function () { try { localStorage.removeItem('tet-user'); } catch (e) { } },
  require: function (role) {                     // send visitors with the wrong role to the login page
    var u = Auth.user();
    if (!u || u.role !== role) location.href = 'login.html';
  }
};

document.addEventListener('DOMContentLoaded', function () {
  var link = document.getElementById('authLink');
  if (link && Auth.user()) {
    link.textContent = 'Log out';
    link.href = '#';
    link.addEventListener('click', function (e) {
      e.preventDefault();
      Auth.logout();
      location.href = 'index.html';
    });
  }
});