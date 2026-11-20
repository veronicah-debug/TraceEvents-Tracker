// my-tickets.js: link on my-tickets.html. Load the QR library BEFORE app.js:
//   <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
// Needs: <tbody id="ticketRows"></tbody>  <div id="ticketView"></div>
var rows = document.getElementById('ticketRows');
var view = document.getElementById('ticketView');

function myTickets() {
  return Store.tickets().filter(function (t) { return t.owner === ME.name; });
}

function drawQr(code) {
  try {
    var q = qrcode(0, 'M');
    q.addData(code);
    q.make();
    return q.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
  } catch (e) {
    return '<div class="code">' + esc(code) + '</div>';
  }
}

function renderTickets() {
  var list = myTickets();
  if (!list.length) {
    rows.innerHTML = '<tr><td colspan="4" class="muted">You have no tickets yet. Register for an event first.</td></tr>';
    return;
  }
  rows.innerHTML = list.map(function (t) {
    var e = Store.event(t.eventId);
    return '<tr><td>' + esc(e.title) + '</td><td class="code">' + t.code + '</td><td>' + pill(t.status) + '</td>' +
      '<td><button class="btn ghost sm" data-open="' + t.code + '">Open</button></td></tr>';
  }).join('');
}

function showTicket(code) {
  var t = Store.tickets().filter(function (x) { return x.code === code; })[0];
  var e = Store.event(t.eventId);
  var canCancel = t.status === 'Confirmed' || t.status === 'Pending';
  view.innerHTML =
    '<div class="ticket"><div class="l">' + pill(t.status) +
    '<h2>' + esc(e.title) + '</h2><div class="muted">' + e.date + ' · ' + esc(e.time) + ' · ' + esc(e.venue) + '</div>' +
    '<div class="small muted">Ticket holder</div><b>' + esc(t.owner) + '</b>' +
    (canCancel ? '<div><button class="btn bad sm" data-cancel="' + t.code + '">Cancel ticket</button></div>' : '') +
    '</div><div class="r">' + drawQr(t.code) + '<div class="code">' + t.code + '</div></div></div>';
}

document.addEventListener('click', function (ev) {
  var open = ev.target.closest('[data-open]');
  var cancel = ev.target.closest('[data-cancel]');
  if (open) showTicket(open.dataset.open);
  if (cancel) {
    Store.tickets().forEach(function (t) { if (t.code === cancel.dataset.cancel) t.status = 'Cancelled'; });
    Store.save();
    renderTickets();
    showTicket(cancel.dataset.cancel);
    toast('Ticket cancelled.');
  }
});

renderTickets();