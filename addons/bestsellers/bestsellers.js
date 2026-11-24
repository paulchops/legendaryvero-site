/* ASharp Agency add-on: Best sellers (filter chips + grid + quick view + quote pre-fill).
   Reusable on any of the 20 designs. Data: window.BESTSELLERS (bestsellers-data.js).
   Markup it needs:
     <div data-bestsellers data-quote-target="#quote"></div>      grid mounts here
     inside the quote form:
       <div data-bs-pick></div>                                   "you picked" box
       <input data-bs-item>                                       item / style number field
       <input data-bs-colors>                                     sizes & colors field (optional)
   Styling comes only from the design's shared --sx-* tokens (bestsellers.css). Vanilla JS, no libraries. */
(function () {
  'use strict';
  var D = window.BESTSELLERS;
  var mount = document.querySelector('[data-bestsellers]');
  if (!D || !mount) return;

  var esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var find = function (id) { for (var i = 0; i < D.items.length; i++) if (D.items[i].id === id) return D.items[i]; return null; };
  var LIGHT = function (hex) { var h = hex.replace('#', ''); var r = parseInt(h.substr(0, 2), 16), g = parseInt(h.substr(2, 2), 16), b = parseInt(h.substr(4, 2), 16); return (r * 299 + g * 587 + b * 114) / 1000 > 150; };
  var target = mount.getAttribute('data-quote-target') || '#quote';
  var tiers = D.tiers || [[12, 1.3], [24, 1], [48, 0.85], ['72+', 0.75]];
  var shots = D.shots || ['Front', 'Back', 'On a real job', 'Close-up'];
  /* UI words: English defaults, overridable per page with D.labels (e.g. a Spanish data file). 2026-10-17 */
  var L = {}, L0 = { all: 'All', photos: 'Photos and details: ', sample: 'Sample', photoPh: 'photo', finished: 'finished job', peek: 'Photos &amp; details',
    style: 'Style', from: 'From $', each: 'each with your logo', min: 'Min', quote: 'Quote this item', quoteIn: 'Quote this item in ', close: 'Close',
    largePh: 'large photo', color: 'Color', colors: 'colors', sizes: 'Sizes', decoration: 'Decoration', placement: 'Logo placement',
    minTurn: 'Minimum · turnaround', pcs: 'pcs', priceEach: 'Price each with your logo', samplePrices: 'Sample prices.', picked: 'You picked' };
  for (var k in L0) L[k] = (D.labels && D.labels[k] != null) ? D.labels[k] : L0[k];
  var state = { cat: 'All', view: null, color: 0, shot: 0, back: null };
  var GARMENT = '<svg aria-hidden="true" viewBox="0 0 256 256" fill="currentColor" width="96" height="96"><path d="M247.59,61.22,195.83,33A8,8,0,0,0,192,32H160a8,8,0,0,0-8,8,24,24,0,0,1-48,0,8,8,0,0,0-8-8H64a8,8,0,0,0-3.84,1L8.41,61.22A15.76,15.76,0,0,0,1.82,82.48l19.27,36.81A16.37,16.37,0,0,0,35.67,128H56v80a16,16,0,0,0,16,16H184a16,16,0,0,0,16-16V128h20.34a16.37,16.37,0,0,0,14.58-8.71l19.27-36.81A15.76,15.76,0,0,0,247.59,61.22ZM35.67,112a.62.62,0,0,1-.41-.13L16.09,75.26,56,53.48V112Zm149.32,96H72V48h16.8a40,40,0,0,0,78.38,0H184Zm35.74-96.14a.55.55,0,0,1-.39.14H200V53.48l39.91,21.78Z"/></svg>';

  mount.classList.add('bs');
  mount.innerHTML =
    '<div class="bs-chips" role="group" aria-label="Filter by category"></div>' +
    '<div class="bs-grid" aria-live="polite"></div>' +
    '<div class="bs-qv" hidden></div>';
  var chips = mount.querySelector('.bs-chips'), grid = mount.querySelector('.bs-grid'), qv = mount.querySelector('.bs-qv');
  /* Move the dialog to <body> so a design's container/overflow rules can't clip it */
  document.body.appendChild(qv);
  /* The dialog now sits outside the design root, so copy the design's shared tokens onto it */
  (function () {
    var cs = getComputedStyle(mount);
    ['--sx-accent', '--sx-accent-ink', '--sx-ink', '--sx-link', '--sx-muted', '--sx-panel-bg', '--sx-panel-ink', '--sx-panel-muted', '--sx-r', '--sx-font', '--sx-font-h'].forEach(function (k) {
      var v = cs.getPropertyValue(k); if (v) qv.style.setProperty(k, v.trim());
    });
    qv.style.fontFamily = cs.fontFamily;
  })();

  chips.innerHTML = ['All'].concat(D.categories).map(function (c) {
    return '<button type="button" class="bs-chip" data-cat="' + esc(c) + '" aria-pressed="' + (c === 'All') + '">' + esc(c === 'All' ? L.all : c) + '</button>';
  }).join('');
  chips.addEventListener('click', function (e) {
    var b = e.target.closest('.bs-chip'); if (!b) return;
    state.cat = b.getAttribute('data-cat');
    chips.querySelectorAll('.bs-chip').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
    renderGrid();
  });

  function renderGrid() {
    grid.innerHTML = D.items.filter(function (it) { return state.cat === 'All' || it.category === state.cat; }).map(function (it) {
      return '<article class="bs-card">' +
        '<button type="button" class="bs-photo" data-open="' + it.id + '" aria-label="' + esc(L.photos) + esc(it.name) + '">' +
          (D.sample ? '<span class="bs-sample">' + esc(L.sample) + '</span>' : '') +
          '<span class="bs-ph-label">[' + esc(L.photoPh) + ': ' + esc(it.name) + ', ' + esc(L.finished) + ']</span>' +
          '<span class="bs-peek">' + L.peek + '</span>' +
        '</button>' +
        '<div class="bs-body">' +
          '<p class="bs-cat">' + esc(it.category) + '</p>' +
          '<h3><button type="button" class="bs-name" data-open="' + it.id + '">' + esc(it.name) + '</button></h3>' +
          '<p class="bs-style">' + esc(L.style) + ' ' + esc(it.style) + '</p>' +
          '<p class="bs-methods">' + esc(it.methods) + '</p>' +
          '<p class="bs-price"><strong>' + esc(L.from) + it.price + '</strong> ' + esc(L.each) + '</p>' +
          '<p class="bs-meta">' + esc(it.basis) + ' · ' + esc(L.min) + ' ' + it.min + ' · ' + esc(it.turn) + '</p>' +
          '<a class="sx-btn bs-quote" href="' + esc(target) + '" data-quote="' + it.id + '">' + esc(L.quote) + '</a>' +
        '</div>' +
      '</article>';
    }).join('');
  }
  grid.addEventListener('click', function (e) {
    var o = e.target.closest('[data-open]');
    if (o) { open(+o.getAttribute('data-open'), o); return; }
    var q = e.target.closest('[data-quote]');
    if (q) pick(find(+q.getAttribute('data-quote')), null);
  });

  function open(id, from) {
    state.view = id; state.color = 0; state.shot = 0; state.back = from || document.activeElement;
    render(); qv.hidden = false; qv.classList.add('bs-opening'); setTimeout(function () { qv.classList.remove('bs-opening'); }, 320); document.documentElement.classList.add('bs-locked');
    var c = qv.querySelector('.bs-x'); if (c) c.focus();
  }
  function close() {
    qv.hidden = true; document.documentElement.classList.remove('bs-locked'); state.view = null;
    if (state.back && document.body.contains(state.back)) state.back.focus();
  }
  function render() {
    var v = find(state.view); if (!v) return;
    var cols = D.palettes[v.palette] || [['Default', '#888888']], col = cols[state.color];
    var light = LIGHT(col[1]);
    qv.innerHTML =
      '<div class="bs-panel" role="dialog" aria-modal="true" aria-labelledby="bs-title">' +
        '<button type="button" class="bs-x" data-close aria-label="' + esc(L.close) + '">×</button>' +
        '<div class="bs-media">' +
          '<div class="bs-big" style="background-color:' + (col[1] === '#FFFFFF' ? '#E9EBEE' : col[1]) + ';color:' + (light ? '#1B1D21' : '#FFFFFF') + '">' + GARMENT +
            '<span class="bs-big-label">[' + esc(L.largePh) + ': ' + esc(shots[state.shot]) + ' · ' + esc(col[0]) + ']</span></div>' +
          '<div class="bs-thumbs">' + shots.map(function (s, i) {
            return '<button type="button" class="bs-thumb" data-shot="' + i + '" aria-pressed="' + (i === state.shot) + '">' + esc(s) + '</button>';
          }).join('') + '</div>' +
        '</div>' +
        '<div class="bs-info">' +
          '<p class="bs-cat">' + esc(v.category) + ' · ' + esc(L.style) + ' ' + esc(v.style) + '</p>' +
          '<h2 id="bs-title">' + esc(v.name) + '</h2>' +
          '<p class="bs-desc">' + esc(v.desc || '') + '</p>' +
          '<div><p class="bs-k"><b>' + esc(L.color) + ':</b> ' + esc(col[0]) + ' <span>(' + cols.length + '+ ' + esc(L.colors) + ')</span></p>' +
            '<div class="bs-swatches">' + cols.map(function (c, i) {
              return '<button type="button" class="bs-sw" data-color="' + i + '" aria-label="' + esc(c[0]) + '" aria-pressed="' + (i === state.color) + '" style="background:' + c[1] + '"></button>';
            }).join('') + '</div></div>' +
          '<dl class="bs-facts">' +
            '<div><dt>' + esc(L.sizes) + '</dt><dd>' + esc(v.sizes || '') + '</dd></div>' +
            '<div><dt>' + esc(L.decoration) + '</dt><dd>' + esc(v.methods) + '</dd></div>' +
            '<div><dt>' + esc(L.placement) + '</dt><dd>' + esc(v.placements || '') + '</dd></div>' +
            '<div><dt>' + esc(L.minTurn) + '</dt><dd>' + v.min + ' ' + esc(L.pcs) + ' · ' + esc(v.turn) + '</dd></div>' +
          '</dl>' +
          '<div><p class="bs-k"><b>' + esc(L.priceEach) + '</b> <span>(' + esc(v.basis.replace(/^at \d+ pcs, /, '')) + ')</span></p>' +
            '<div class="bs-tiers">' + tiers.map(function (t) {
              return '<div><small>' + t[0] + ' ' + esc(L.pcs) + '</small><strong>$' + (Math.round(v.price * t[1] * 100) / 100).toFixed(2) + '</strong></div>';
            }).join('') + '</div>' +
            (D.sample ? '<p class="bs-note">' + esc(D.note || L.samplePrices) + '</p>' : '') + '</div>' +
          '<a class="sx-btn bs-go" href="' + esc(target) + '" data-quote-view>' + esc(L.quoteIn) + esc(col[0]) + '</a>' +
        '</div>' +
      '</div>';
  }
  qv.addEventListener('click', function (e) {
    if (e.target === qv || e.target.closest('[data-close]')) { close(); return; }
    var s = e.target.closest('[data-shot]');
    if (s) { state.shot = +s.getAttribute('data-shot'); render(); qv.querySelector('[data-shot="' + state.shot + '"]').focus(); return; }
    var c = e.target.closest('[data-color]');
    if (c) { state.color = +c.getAttribute('data-color'); render(); qv.querySelector('[data-color="' + state.color + '"]').focus(); return; }
    if (e.target.closest('[data-quote-view]')) {
      var v = find(state.view); pick(v, (D.palettes[v.palette] || [])[state.color][0]); close();
    }
  });
  document.addEventListener('keydown', function (e) {
    if (qv.hidden) return;
    if (e.key === 'Escape') { close(); return; }
    if (e.key === 'Tab') { /* keep focus inside the window */
      var f = qv.querySelectorAll('button,a[href]'); if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });

  function pick(it, color) {
    if (!it) return;
    var box = document.querySelector('[data-bs-pick]'), item = document.querySelector('[data-bs-item]'), cols = document.querySelector('[data-bs-colors]');
    if (box) box.innerHTML = '<div class="bs-picked"><span class="bs-cat">' + esc(L.picked) + '</span><b>' + esc(it.name) + ' · ' + esc(L.style) + ' ' + esc(it.style) + '</b><span>' + esc(it.methods) + (color ? ' · ' + esc(L.color) + ': ' + esc(color) : '') + '</span></div>';
    if (item) item.value = it.name + ' (' + it.style + (color ? ', ' + color : '') + ')';
    if (cols && color && !cols.value) cols.value = color + ': ';
  }

  renderGrid();
})();
