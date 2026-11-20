// management.js: link on management.html (after app.js).
// Needs: <div id="pendingList"></div>  <tbody id="summaryRows"></tbody>
var pendingBox = document.getElementById('pendingList');
var summaryRows = document.getElementById('summaryRows');

function renderPending() {
  var pending = Store.events().filter(function (e) { return e.status === 'pending'; });
  if (!pending.length) {
    pendingBox.innerHTML = '<p class="muted">Nothing is waiting. New events from organizers will appear here.</p>';
    return;
  }
  pendingBox.innerHTML = pending.map(function (e) {
    var c = calcBudget(e);
    return '<article class="card ev"><h3>' + esc(e.title) + '</h3>' +
      '<div class="muted small">' + esc(e.club) + ' · ' + e.date + '</div>' +
      '<p class="small">' + esc(e.desc) + '</p>' +
      '<div class="small">Planned cost <b>' + kes(c.cost) + '</b> · Projected ' + (c.net >= 0 ? 'surplus' : 'deficit') + ' <b>' + kes(Math.abs(c.net)) + '</b></div>' +
      '<div class="row"><button class="btn sm" data-approve="' + e.id + '">Approve</button>' +
      '<button class="btn bad sm" data-reject="' + e.id + '">Reject</button></div></article>';
  }).join('');
}

function renderSummary() {
  summaryRows.innerHTML = Store.events().map(function (e) {
    return '<tr><td>' + esc(e.title) + '</td><td>' + esc(e.club) + '</td><td>' + e.date + '</td><td>' + pill(e.status) +
      '</td><td>' + registered(e.id) + ' / ' + e.capacity + '</td><td>' + attended(e.id) + '</td></tr>';
  }).join('');
}

pendingBox.addEventListener('click', function (ev) {
  var a = ev.target.closest('[data-approve]'), r = ev.target.closest('[data-reject]');
  if (!a && !r) return;
  Store.event((a || r).dataset.approve || (a || r).dataset.reject).status = a ? 'approved' : 'rejected';
  Store.save();
  toast(a ? 'Event approved and published.' : 'Event rejected.');
  renderPending();
  renderSummary();
});

renderPending();
renderSummary();