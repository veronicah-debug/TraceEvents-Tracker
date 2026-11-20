// events.js: link on events.html (after app.js).
// Needs: <input id="q">  <select id="type">  <div id="eventGrid"></div>
var grid = document.getElementById('eventGrid');
var searchBox = document.getElementById('q');
var typeBox = document.getElementById('type');

function myTicket(eventId) {
  return Store.tickets().filter(function (t) {
    return t.eventId === eventId && t.owner === ME.name && t.status !== 'Cancelled';
  })[0];
}

function renderEvents() {
  var q = searchBox.value.trim().toLowerCase();
  var type = typeBox.value;

  var list = Store.events().filter(function (e) {
    return e.status === 'approved' &&
      (q === '' || (e.title + e.club).toLowerCase().indexOf(q) > -1) &&
      (type === '' || (type === 'free' ? e.price === 0 : e.price > 0));
  });

  if (!list.length) {
    grid.innerHTML = '<p class="muted">No events match. Clear the search or change the ticket type.</p>';
    return;
  }

  grid.innerHTML = list.map(function (e) {
    var reg = registered(e.id);
    var pct = Math.min(100, Math.round(reg / e.capacity * 100));
    var action = myTicket(e.id)
      ? '<a class="btn sm" href="my-tickets.html">View my ticket</a>'
      : (reg >= e.capacity ? '<span class="pill p-bad">Full</span>'
        : '<button class="btn sm" data-register="' + e.id + '">Register</button>');
    return '<article class="card ev">' +
      '<h3>' + esc(e.title) + '</h3>' +
      '<div class="muted small">' + esc(e.club) + '</div>' +
      '<div class="small muted">' + e.date + ' · ' + esc(e.time) + ' · ' + esc(e.venue) + '</div>' +
      '<div class="row"><b>' + (e.price ? kes(e.price) : 'Free') + '</b><span class="small muted">' + reg + ' of ' + e.capacity + ' registered</span></div>' +
      '<div class="bar"><i style="width:' + pct + '%"></i></div>' + action + '</article>';
  }).join('');
}

grid.addEventListener('click', function (ev) {
  var btn = ev.target.closest('[data-register]');
  if (!btn) return;
  var e = Store.event(btn.dataset.register);

  var ticket = {
    code: 'TET-0' + e.id + '-' + (Date.now() % 100000).toString(36).toUpperCase(),
    eventId: e.id,
    status: e.price ? 'Pending' : 'Confirmed',
    owner: ME.name,
    adm: ME.adm
  };
  Store.tickets().push(ticket);
  Store.save();
  toast('Registered. Open My tickets to see your QR code.');
  renderEvents();
});

searchBox.addEventListener('input', renderEvents);
typeBox.addEventListener('change', renderEvents);
renderEvents();