/* ASharp Agency add-on: standard site footer.
   Data: window.SITE_FOOTER (footer-data.js). Mount: <footer data-site-footer></footer>.
   Every row is data-driven; empty values (no logo, no email, no social link) are left out,
   so the footer never shows a dead link. Vanilla JS, no libraries. */
(function () {
  'use strict';
  var D = window.SITE_FOOTER, el = document.querySelector('[data-site-footer]');
  if (!D || !el) return;
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var has = function (v) { return v && String(v).trim() !== ''; };
  var here = location.pathname.split('/').pop() || 'index.html';
  var link = function (l, cls) {
    if (!l || !has(l.href) || !has(l.label)) return '';
    var ext = /^https?:/.test(l.href) ? ' target="_blank" rel="noopener"' : '';
    var cur = (l.href === here || (l.href === './' && here === 'index.html')) ? ' aria-current="page"' : '';
    var hook = has(l.hook) ? ' data-hook="' + esc(l.hook) + '"' : '';   // e.g. hook:"chat" opens the site chat (2026-10-17)
    return '<a href="' + esc(l.href) + '"' + ext + cur + hook + (cls ? ' class="' + cls + '"' : '') + '>' + esc(l.label) + '</a>';
  };
  var list = function (arr) { var h = (arr || []).map(function (l) { var a = link(l); return a ? '<li>' + a + '</li>' : ''; }).join(''); return h ? '<ul class="ft-links">' + h + '</ul>' : ''; };
  var col = function (title, inner) { return inner ? '<div class="ft-col"><h2>' + esc(title) + '</h2>' + inner + '</div>' : ''; };
  var L = D.labels || {};

  var logo = D.logo && has(D.logo.src)
    ? '<a class="ft-logo" href="./"><img src="' + esc(D.logo.src) + '" alt="' + esc(D.logo.alt || D.name) + '"></a>'
    : '<a class="ft-logo" href="./">' + esc(D.name) + '</a>';
  var brand = '<div class="ft-brand">' + logo + (has(D.tagline) ? '<p class="ft-tag">' + esc(D.tagline) + '</p>' : '') +
    (D.quote && has(D.quote.href) ? link(D.quote, 'ft-btn') : '') + '</div>';

  var c = [];
  if (has(D.phone)) c.push('<li><a href="tel:' + esc(D.phoneTel || D.phone.replace(/[^\d+]/g, '')) + '">' + esc(D.phone) + '</a></li>');
  if (has(D.email)) c.push('<li><a href="mailto:' + esc(D.email) + '">' + esc(D.email) + '</a></li>');
  if (has(D.address)) {
    var map = has(D.mapUrl) ? D.mapUrl : 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(D.address);
    c.push('<li><a href="' + esc(map) + '" target="_blank" rel="noopener">' + esc(D.address) + '</a></li>');
  }
  if (D.reviews && has(D.reviews.href)) c.push('<li>' + link(D.reviews) + '</li>');
  var contact = c.length ? '<ul class="ft-contact">' + c.join('') + '</ul>' : '';
  var social = (D.social || []).map(function (s) { return link(s); }).filter(Boolean).join('');
  if (social) contact += '<div class="ft-social" aria-label="' + esc(L.social || 'Social') + '">' + social + '</div>';

  var hours = (D.hours || []).filter(has).map(function (h) { return '<li>' + esc(h) + '</li>'; }).join('');
  var cities = (D.cities || []).filter(has);
  var where = (hours ? '<ul class="ft-hours">' + hours + '</ul>' : '') +
    (cities.length ? '<h2 style="margin-top:18px">' + esc(L.cities || 'Cities we serve') + '</h2><p class="ft-cities">' + cities.map(esc).join(' · ') + '</p>' : '');

  var legal = (D.legal || []).map(function (l) { return link(l); }).filter(Boolean).join('');
  var year = D.year || new Date().getFullYear();
  /* Credit line: on by default (Paul, 2026-10-17). Set credit: { show: false } to turn it off.
     The name is always the full "ASharp Agency". */
  var cr = D.credit || {};
  var credit = cr.show === false ? '' : '<p class="ft-credit"><a href="' + esc(cr.href || 'https://asharpagency.com') + '" target="_blank" rel="noopener">' + esc(cr.label || 'Website by ASharp Agency') + '</a></p>';

  el.classList.add('ft');
  el.innerHTML = '<div class="ft-in">' + brand +
    '<div class="ft-cols">' + col(L.pages || 'Pages', list(D.pages)) + col(L.contact || 'Contact', contact) + col(L.hours || 'Hours', where) + '</div>' +
    '<div class="ft-bottom">' + (legal ? '<nav class="ft-legal" aria-label="' + esc(L.legal || 'Legal') + '">' + legal + '</nav>' : '') +
    '<p class="ft-copy">© ' + esc(year) + ' ' + esc(D.legalName || D.name) + '</p></div>' + credit + '</div>';
})();
