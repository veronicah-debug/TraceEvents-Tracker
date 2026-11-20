// organizer.js: link on organizer.html (after app.js).
// Needs: #evSel  #budgetForm  #items  #addItem  #saveBudget  #bPrice  #bExp
//        #totCost  #inc  #net  #be  #verifyForm  #vCode  #verifyResult  #regRows
var sel = document.getElementById('evSel');
var itemsBox = document.getElementById('items');
var current;

function myEvents() { return Store.events().filter(function (e) { return e.club === CLUB; }); }

function fillPicker() {
  sel.innerHTML = myEvents().map(function (e) {
    return '<option value="' + e.id + '">' + esc(e.title) + '</option>';
  }).join('');
}

function selectEvent(id) {
  current = Store.event(id);
  renderItems();
  document.getElementById('bPrice').value = current.budget.price;
  document.getElementById('bExp').value = current.budget.expected;
  recompute();
  renderRegistrations();
  document.getElementById('verifyResult').textContent = '';
}

function itemRow(item) {
  return '<div class="row item" style="flex-wrap:nowrap">' +
    '<input class="name" aria-label="Item name" value="' + esc(item.name) + '">' +
    '<input class="amount" aria-label="Amount" type="number" min="0" value="' + item.amount + '" style="max-width:130px">' +
    '<button class="btn bad sm" type="button" data-remove>Remove</button></div>';
}

function renderItems() {
  itemsBox.innerHTML = current.budget.items.map(itemRow).join('');
}

function readForm() {
  current.budget.items = [].map.call(itemsBox.querySelectorAll('.item'), function (row) {
    return { name: row.querySelector('.name').value, amount: +row.querySelector('.amount').value || 0 };
  });
  current.budget.price = +document.getElementById('bPrice').value || 0;
  current.budget.expected = +document.getElementById('bExp').value || 0;
}

function recompute() {
  readForm();
  var c = calcBudget(current), good = c.net >= 0;
  document.getElementById('totCost').textContent = kes(c.cost);
  document.getElementById('inc').textContent = kes(c.income);

  var net = document.getElementById('net');
  net.textContent = 'Projected ' + (good ? 'surplus' : 'deficit') + ': ' + kes(Math.abs(c.net));
  net.style.background = 'var(--' + (good ? 'ok' : 'bad') + '-soft)';
  net.style.color = 'var(--' + (good ? 'ok' : 'bad') + ')';

  document.getElementById('be').textContent = c.breakEven === null
    ? 'Free event: costs must be covered by sponsorship or club funds.'
    : 'Break-even attendance: ' + c.breakEven + ' tickets (capacity ' + current.capacity + ')' +
      (c.breakEven > current.capacity ? '. Break-even is above capacity.' : '.');
}

document.getElementById('budgetForm').addEventListener('input', recompute);

document.getElementById('addItem').addEventListener('click', function () {
  itemsBox.insertAdjacentHTML('beforeend', itemRow({ name: 'New item', amount: 0 }));
  recompute();
});

itemsBox.addEventListener('click', function (ev) {
  if (ev.target.matches('[data-remove]')) { ev.target.closest('.item').remove(); recompute(); }
});

document.getElementById('saveBudget').addEventListener('click', function () {
  recompute();
  Store.save();
  toast('Budget estimate saved.');
});

function renderRegistrations() {
  var rows = Store.tickets().filter(function (t) { return t.eventId === current.id; });
  document.getElementById('regRows').innerHTML = rows.map(function (t) {
    return '<tr><td>' + esc(t.owner) + '</td><td>' + esc(t.adm) + '</td><td class="code">' + t.code + '</td><td>' + pill(t.status) + '</td></tr>';
  }).join('');
}

function showResult(kind, message) {
  var box = document.getElementById('verifyResult');
  box.className = 'result';
  box.style.background = 'var(--' + kind + '-soft)';
  box.style.color = 'var(--' + kind + ')';
  box.textContent = message;
}

document.getElementById('verifyForm').addEventListener('submit', function (ev) {
  ev.preventDefault();
  var code = document.getElementById('vCode').value.trim().toUpperCase();
  var t = Store.tickets().filter(function (x) { return x.code === code; })[0];

  if (!t) return showResult('bad', 'Ticket not found. Check the code and try again.');
  if (t.eventId !== current.id) return showResult('bad', 'This ticket is for a different event: ' + Store.event(t.eventId).title + '.');
  if (t.status === 'Used') return showResult('bad', 'Already used. This ticket was checked in earlier.');
  if (t.status === 'Cancelled') return showResult('bad', 'Cancelled ticket. Do not admit.');
  if (t.status === 'Pending') return showResult('warn', 'Payment not confirmed yet. Ask the student to complete payment.');

  t.status = 'Used';
  Store.save();
  renderRegistrations();
  showResult('ok', 'Valid. ' + t.owner + ' checked in.');
  document.getElementById('vCode').value = '';
});

sel.addEventListener('change', function () { selectEvent(sel.value); });
fillPicker();
selectEvent(sel.value);