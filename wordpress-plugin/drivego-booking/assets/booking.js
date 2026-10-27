/* DriveGo Booking · front end. Talks to the server for free days and times, then books. */
(function () {
  'use strict';
  if (!window.DGB) return;
  var CFG = window.DGB, SERVICES = CFG.services;
  var STEPS = ['Service', 'Day', 'Time', 'Details', 'Confirm'];
  var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  var DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  var ICON = {
    close: '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/></svg>',
    lock: '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M7 11V8a5 5 0 0110 0v3M6 11h12v9H6z" stroke="currentColor" stroke-width="2" fill="none" stroke-linejoin="round"/></svg>',
    prev: '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M15 5l-7 7 7 7" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>',
    next: '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>',
    check: '<svg viewBox="0 0 24 24" width="48" height="48" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="currentColor"/><path d="M7 12.5l3.2 3.2L17 9" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>'
  };
  var money = function (n) { return '$' + Number(n).toLocaleString('en-CA', { minimumFractionDigits: n % 1 ? 2 : 0 }); };
  var pad = function (n) { return String(n).padStart(2, '0'); };
  var iso = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
  var parse = function (s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); };
  var fmtTime = function (m) { var h = Math.floor(m / 60), mm = m % 60; return ((h + 11) % 12 + 1) + ':' + pad(mm) + ' ' + (h < 12 ? 'AM' : 'PM'); };
  var fmtDate = function (d) { return d.toLocaleDateString('en-CA', { weekday: 'long', month: 'long', day: 'numeric' }); };
  var esc = function (t) { return String(t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var api = function (path, opts) {
    var url = CFG.rest + (CFG.rest.indexOf('?') > -1 ? path.replace('?', '&') : path);
    return fetch(url, opts).then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Request failed'); return j; }); });
  };

  function create(host, modal) {
    var dialog = null, state = null;

    function build() {
      dialog = document.createElement(modal ? 'dialog' : 'div');
      dialog.className = modal ? 'bk' : 'bk bk--inline';
      dialog.setAttribute('aria-labelledby', 'bk-title');
      dialog.innerHTML = '<div class="bk__panel"><aside class="bk__summary"><h2 class="bk__title" id="bk-title"></h2><dl class="bk__facts" data-bk-facts></dl>' +
        '<p class="bk__note">' + ICON.lock + (CFG.pay && CFG.pay.square ? 'Secure payment with Square' : 'Your details are kept private') + '</p></aside><div class="bk__main">' +
        '<button type="button" class="bk__close" data-bk-close aria-label="Close booking">' + ICON.close + '</button>' +
        '<ol class="bk__steps" data-bk-steps></ol><div class="bk__body" data-bk-body></div>' +
        '<div class="bk__nav"><button type="button" class="dgb-btn dgb-btn--outline" data-bk-back>Back</button><button type="button" class="dgb-btn dgb-btn--dark" data-bk-next>Continue</button></div></div></div>';
      host.appendChild(dialog);
      if (modal) {
        dialog.querySelector('[data-bk-close]').addEventListener('click', function () { dialog.close(); });
        dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
        dialog.addEventListener('close', function () { document.body.style.overflow = ''; });
      } else { dialog.querySelector('[data-bk-close]').remove(); }
      dialog.querySelector('[data-bk-back]').addEventListener('click', function () { go(state.step - 1); });
      dialog.querySelector('[data-bk-next]').addEventListener('click', next);
    }

    function open(key, optionIndex) {
      var service = SERVICES[key];
      if (!service) return false;
      if (!dialog) build();
      var first = parse(CFG.window.today); first.setDate(first.getDate() + CFG.window.leadDays);
      state = { key: key, service: service, option: optionIndex || 0, step: 0, date: null, time: null, month: new Date(first.getFullYear(), first.getMonth(), 1), free: {}, slots: [], details: {}, sent: false, payUrl: '', busy: false };
      dialog.querySelector('#bk-title').textContent = service.name;
      go(0);
      if (modal) { document.body.style.overflow = 'hidden'; dialog.showModal(); }
      return true;
    }

    function opt() { return state.service.options[state.option]; }
    function pickupText() {
      var os = state.service.options.filter(function (o) { return o.pickup > 0; });
      var same = os.every(function (o) { return o.pickup === os[0].pickup; });
      return same ? money(os[0].pickup) : os.map(function (o) { return money(o.pickup) + ' (' + esc(o.label) + ')'; }).join(', ');
    }
    function homeTest() { return state.key === 'roadtest' && opt().home !== false; }
    function payMethods() {
      var all = CFG.pay || {}, out = {};
      Object.keys(all).forEach(function (k) { if (!(state.key === 'roadtest' && k === 'cash')) out[k] = all[k]; });
      return out;
    }
    var CENTRE_NOTE = 'Prices may increase depending on the location of your test. We will confirm the final price with you before your booking is confirmed.';
    function pickupOn() { return state.key === 'roadtest' ? homeTest() : (state.details.meet || 'Pick me up at home') === 'Pick me up at home'; }
    function extra() { var o = opt(); return o.pickup > 0 && pickupOn() ? o.pickup : 0; }
    function total() { return opt().price + extra(); }

    function summary() {
      var o = opt();
      var rows = [['Option', o.label], ['Price', money(o.price) + ' + HST']];
      if (o.pickup > 0 && state.step >= 3) rows.push(['Home pick up', extra() ? '+ ' + money(extra()) : 'Not needed']);
      if (extra()) rows[1] = ['Total', money(total()) + ' + HST'];
      rows = rows.concat([['Day', state.date ? fmtDate(state.date) : 'Not chosen yet'], ['Time', state.time !== null ? fmtTime(state.time) : 'Not chosen yet']]);
      dialog.querySelector('[data-bk-facts]').innerHTML = rows.map(function (r) { return '<div><dt>' + r[0] + '</dt><dd>' + esc(r[1]) + '</dd></div>'; }).join('');
    }

    function go(step) {
      state.step = Math.max(0, Math.min(step, STEPS.length - 1));
      dialog.querySelector('[data-bk-steps]').innerHTML = STEPS.map(function (s, i) { return '<li class="' + (i === state.step ? 'is-current' : '') + (i < state.step ? ' is-done' : '') + '"><span>' + (i + 1) + '</span>' + s + '</li>'; }).join('');
      var body = dialog.querySelector('[data-bk-body]'), back = dialog.querySelector('[data-bk-back]'), nb = dialog.querySelector('[data-bk-next]');
      back.hidden = state.step === 0 || state.sent; nb.hidden = false; nb.textContent = 'Continue';
      [renderOption, renderDay, renderTime, renderDetails, renderConfirm][state.step](body, nb);
      summary();
    }

    function hint(t) { var el = dialog.querySelector('[data-bk-hint]'); if (el) { el.textContent = t; el.hidden = false; } }

    function next() {
      if (state.busy) return;
      if (state.step === 1 && !state.date) return hint('Pick a day to continue.');
      if (state.step === 2 && state.time === null) return hint('Pick a time to continue.');
      if (state.step === 3 && !validDetails()) return;
      if (state.step === 4) return submit();
      go(state.step + 1);
    }

    function renderOption(body) {
      body.innerHTML = '<h3 class="bk__h">Choose an option</h3><div class="bk__options">' + state.service.options.map(function (o, i) {
        return '<button type="button" class="bk__option" aria-pressed="' + (i === state.option) + '" data-i="' + i + '"><span class="bk__option-name">' + esc(o.label) + '</span><span class="bk__option-price">' + money(o.price) + ' <small>+ HST</small></span></button>';
      }).join('') + '</div>' + (state.key !== 'roadtest' && state.service.options.some(function (o) { return o.pickup > 0; }) ? '<p class="bk__small">Meeting us at White Oaks Mall is the base price. Home pick up adds ' + pickupText() + '.</p>' : '');
      body.querySelectorAll('.bk__option').forEach(function (b) {
        b.addEventListener('click', function () {
          state.option = +b.dataset.i; state.time = null; state.date = null; state.free = {};
          body.querySelectorAll('.bk__option').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
          summary();
        });
      });
    }

    function loadMonth(m) {
      var key = m.getFullYear() + '-' + pad(m.getMonth() + 1);
      if (state.free[key]) return Promise.resolve(state.free[key]);
      return api('days?service=' + state.key + '&option=' + state.option + '&month=' + key).then(function (j) { state.free[key] = j.days; return j.days; });
    }

    function renderDay(body) {
      var m = state.month;
      body.innerHTML = '<h3 class="bk__h">' + esc(state.service.when) + '</h3><div class="bk__cal"><div class="bk__cal-head">' +
        '<button type="button" class="bk__cal-nav" data-m="-1" aria-label="Previous month">' + ICON.prev + '</button><p>' + MONTHS[m.getMonth()] + ' ' + m.getFullYear() + '</p>' +
        '<button type="button" class="bk__cal-nav" data-m="1" aria-label="Next month">' + ICON.next + '</button></div><div class="bk__cal-grid" data-grid><p class="bk__loading">Loading days…</p></div></div><p class="bk__hint" data-bk-hint hidden></p>';
      var today = parse(CFG.window.today), minM = new Date(today.getFullYear(), today.getMonth(), 1);
      var maxD = new Date(today); maxD.setDate(maxD.getDate() + CFG.window.horizonDays);
      var prev = body.querySelector('[data-m="-1"]'), nxt = body.querySelector('[data-m="1"]');
      prev.disabled = m <= minM; nxt.disabled = new Date(m.getFullYear(), m.getMonth() + 1, 1) > maxD;
      [prev, nxt].forEach(function (b) { b.addEventListener('click', function () { state.month = new Date(m.getFullYear(), m.getMonth() + (+b.dataset.m), 1); renderDay(body); }); });
      loadMonth(m).then(function (days) {
        var grid = body.querySelector('[data-grid]'); if (!grid) return;
        var firstDow = new Date(m.getFullYear(), m.getMonth(), 1).getDay(), n = new Date(m.getFullYear(), m.getMonth() + 1, 0).getDate();
        var cells = DAYS.map(function (d) { return '<span class="bk__dow">' + d + '</span>'; }).join('');
        for (var i = 0; i < firstDow; i++) cells += '<span></span>';
        for (var d = 1; d <= n; d++) {
          var date = new Date(m.getFullYear(), m.getMonth(), d), ok = days.indexOf(iso(date)) > -1, sel = state.date && iso(date) === iso(state.date);
          cells += '<button type="button" class="bk__day" data-d="' + d + '" ' + (ok ? '' : 'disabled') + ' aria-pressed="' + !!sel + '" aria-label="' + esc(fmtDate(date)) + (ok ? '' : ', not available') + '">' + d + '</button>';
        }
        grid.innerHTML = cells;
        grid.querySelectorAll('.bk__day:not([disabled])').forEach(function (b) {
          b.addEventListener('click', function () { state.date = new Date(m.getFullYear(), m.getMonth(), +b.dataset.d); state.time = null; go(2); });
        });
      }).catch(function () { var g = body.querySelector('[data-grid]'); if (g) g.innerHTML = '<p class="bk__hint">We could not load the calendar. Please call ' + esc(CFG.phone) + '.</p>'; });
    }

    function renderTime(body) {
      body.innerHTML = '<h3 class="bk__h">' + esc(fmtDate(state.date)) + '</h3><div data-slots><p class="bk__loading">Loading times…</p></div><p class="bk__hint" data-bk-hint hidden></p>';
      api('slots?service=' + state.key + '&option=' + state.option + '&date=' + iso(state.date)).then(function (j) {
        var box = body.querySelector('[data-slots]'); if (!box) return;
        var groups = [['Morning', function (t) { return t < 720; }], ['Afternoon', function (t) { return t >= 720 && t < 1020; }], ['Evening', function (t) { return t >= 1020; }]];
        var html = groups.map(function (g) {
          var l = j.slots.filter(g[1]); if (!l.length) return '';
          return '<p class="bk__group">' + g[0] + '</p><div class="bk__slots">' + l.map(function (t) { return '<button type="button" class="bk__slot" data-t="' + t + '" aria-pressed="' + (state.time === t) + '">' + fmtTime(t) + '</button>'; }).join('') + '</div>';
        }).join('');
        box.innerHTML = html || '<p class="bk__hint">No times left on this day. Please pick another day.</p>';
        box.querySelectorAll('.bk__slot').forEach(function (b) { b.addEventListener('click', function () { state.time = +b.dataset.t; go(3); }); });
      }).catch(function () { var b2 = body.querySelector('[data-slots]'); if (b2) b2.innerHTML = '<p class="bk__hint">We could not load the times. Please call ' + esc(CFG.phone) + '.</p>'; });
    }

    function field(id, label, input, optional) {
      return '<div class="field"><label for="' + id + '">' + label + (optional ? ' <span class="field__opt">(optional)</span>' : '') + '</label>' + input + '<p class="field__error" id="' + id + '-err"></p></div>';
    }

    function renderDetails(body) {
      var d = state.details, isTest = state.key === 'roadtest';
      var v = function (k) { return esc(d[k] || ''); };
      var sel = function (k, opts) { return opts.map(function (o) { return '<option' + (d[k] === o ? ' selected' : '') + '>' + o + '</option>'; }).join(''); };
      body.innerHTML = '<h3 class="bk__h">A few quick questions</h3><div class="bk__form"><input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px" id="bk-website">' +
        '<div class="field-row">' + field('bk-name', 'Full name', '<input id="bk-name" type="text" autocomplete="name" value="' + v('name') + '">') + field('bk-phone', 'Phone or WhatsApp', '<input id="bk-phone" type="tel" autocomplete="tel" placeholder="(519) 000 0000" value="' + v('phone') + '">') + '</div>' +
        field('bk-email', 'Email', '<input id="bk-email" type="email" autocomplete="email" placeholder="you@email.com" value="' + v('email') + '">') +
        '<div class="field-row">' + field('bk-licence', 'Licence you have now', '<select id="bk-licence">' + sel('licence', ['G1', 'G2', 'Full G']) + '</select>') + field('bk-lang', 'Lesson language', '<select id="bk-lang">' + sel('lang', ['English', 'Spanish', 'Arabic', 'Portuguese']) + '</select>') + '</div>' +
        (isTest ? field('bk-centre', 'DriveTest centre', '<select id="bk-centre">' + sel('centre', ['London', 'Other']) + '</select><p class="bk__notice" id="bk-centre-note" role="status" hidden>' + CENTRE_NOTE + '</p>') : field('bk-meet', 'Where should we start?', '<select id="bk-meet">' + sel('meet', ['Pick me up at home', 'Meet at White Oaks Mall']) + '</select>')) +
        (Object.keys(payMethods()).length > 1 ? field('bk-pay', 'How would you like to pay?', '<select id="bk-pay">' + Object.keys(payMethods()).map(function (k) { return '<option value="' + k + '"' + (d.pay === k ? ' selected' : '') + '>' + esc(payMethods()[k]) + '</option>'; }).join('') + '</select>') : '') +
        field('bk-address', 'Pick up address', '<input id="bk-address" type="text" autocomplete="street-address" placeholder="Street, London, ON" value="' + v('address') + '">') +
        field('bk-notes', 'Anything we should know?', '<textarea id="bk-notes" rows="2" placeholder="For example, what you want to practise.">' + v('notes') + '</textarea>', true) + '</div>';
      var meet = body.querySelector('#bk-meet'), addr = body.querySelector('#bk-address').closest('.field');
      var sync = function () { addr.hidden = meet ? meet.value !== 'Pick me up at home' : !homeTest(); };
      if (meet) { meet.addEventListener('change', function () { state.details.meet = meet.value; sync(); summary(); }); }
      sync();
      var centre = body.querySelector('#bk-centre'), note = body.querySelector('#bk-centre-note');
      if (centre && note) { var cs = function () { state.details.centre = centre.value; note.hidden = centre.value !== 'Other'; }; centre.addEventListener('change', cs); cs(); }
    }

    function validDetails() {
      var g = function (id) { return dialog.querySelector('#' + id); }, d = state.details;
      d.name = g('bk-name').value.trim(); d.phone = g('bk-phone').value.trim(); d.email = g('bk-email').value.trim();
      d.licence = g('bk-licence').value; d.lang = g('bk-lang').value; d.centre = g('bk-centre') ? g('bk-centre').value : ''; d.meet = g('bk-meet') ? g('bk-meet').value : '';
      d.pay = g('bk-pay') ? g('bk-pay').value : Object.keys(payMethods())[0] || ''; d.address = g('bk-address').value.trim(); if (state.key === 'roadtest' && !homeTest()) d.address = ''; d.notes = g('bk-notes').value.trim(); d.website = g('bk-website').value;
      var needAddr = state.key === 'roadtest' ? homeTest() : d.meet === 'Pick me up at home';
      var errors = {
        'bk-name': d.name.length >= 2 ? '' : 'Please enter your full name.',
        'bk-phone': d.phone.replace(/\D/g, '').length >= 10 ? '' : 'Enter a phone number with at least 10 digits.',
        'bk-email': /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(d.email) ? '' : 'Enter a valid email so we can send your confirmation.',
        'bk-address': !needAddr || d.address.length >= 5 ? '' : 'Tell us where to pick you up.'
      };
      var first = null;
      Object.keys(errors).forEach(function (id) { var i = g(id), e = g(id + '-err'); i.setAttribute('aria-invalid', errors[id] ? 'true' : 'false'); e.textContent = errors[id]; if (errors[id] && !first) first = i; });
      if (first) first.focus();
      return !first;
    }

    function renderConfirm(body, nb) {
      var o = opt(), d = state.details;
      var where = state.key === 'roadtest' ? 'DriveTest ' + d.centre + (homeTest() && d.address ? ', pick up at ' + d.address : ', meet at White Oaks Mall') : (d.meet === 'Meet at White Oaks Mall' ? 'White Oaks Mall' : 'Pick up at ' + d.address);
      if (state.sent) {
        body.innerHTML = '<div class="bk__done"><span class="bk__done-icon">' + ICON.check + '</span><h3 class="bk__h">Request sent</h3><p>We will confirm your ' + esc(state.service.name.toLowerCase()) + ' on ' + esc(fmtDate(state.date)) + ' at ' + esc(fmtTime(state.time)) + ' by phone or WhatsApp within one business day. A copy was sent to your email.</p>' +
          (state.payUrl ? '<p>Your payment page opened in a new tab. If it did not, use the button below.</p>' : state.payMethod === 'etransfer' ? '<p>To pay, send an Interac e-Transfer to <strong>' + esc(state.etransfer || '') + '</strong> and write your name in the message. We also emailed you these steps.</p>' : state.payMethod === 'cash' ? '<p>You will pay in cash at your lesson. Please bring the exact amount if you can.</p>' : '<p>We will send you the payment details when we confirm.</p>') + '</div>';
        nb.hidden = !state.payUrl; nb.textContent = 'Open payment page'; return;
      }
      body.innerHTML = '<h3 class="bk__h">Check your booking</h3><ul class="bk__review"><li><span>Service</span><strong>' + esc(state.service.name) + ' · ' + esc(o.label) + '</strong></li>' +
        '<li><span>When</span><strong>' + esc(fmtDate(state.date)) + ' at ' + esc(fmtTime(state.time)) + '</strong></li><li><span>Where</span><strong>' + esc(where) + '</strong></li>' +
        (d.pay && CFG.pay ? '<li><span>Payment</span><strong>' + esc(CFG.pay[d.pay] || '') + '</strong></li>' : '') + '<li><span>Name</span><strong>' + esc(d.name) + ' · ' + esc(d.phone) + '</strong></li><li><span>Total</span><strong>' + money(total()) + ' + HST' + (extra() ? ' <small>(includes ' + money(extra()) + ' home pick up)</small>' : '') + '</strong></li></ul>' +
        (state.key === 'roadtest' && d.centre === 'Other' ? '<p class="bk__notice">' + CENTRE_NOTE + '</p>' : '') + '<p class="bk__small">We confirm every booking. Free reschedule up to 24 hours before.</p><p class="bk__hint" data-bk-hint hidden></p>';
      nb.textContent = state.details.pay === 'square' ? 'Request booking and pay' : 'Request booking';
    }

    function submit() {
      if (state.sent) { if (state.payUrl) window.open(state.payUrl, '_blank', 'noopener'); return; }
      var d = state.details, nb = dialog.querySelector('[data-bk-next]');
      state.busy = true; nb.disabled = true; nb.textContent = 'Sending…';
      var payload = { service: state.key, option: state.option, date: iso(state.date), start: state.time, name: d.name, email: d.email, phone: d.phone, licence: d.licence, lang: d.lang, meet: d.meet, centre: d.centre, address: d.address, notes: d.notes, website: d.website, pay: d.pay };
      api('book', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }).then(function (j) {
        state.sent = true; state.payMethod = j.pay || d.pay; state.etransfer = j.etransfer || ''; state.payUrl = j.pay_url || ''; state.busy = false; nb.disabled = false;
        if (state.payUrl) window.open(state.payUrl, '_blank', 'noopener');
        go(4);
      }).catch(function (e) {
        state.busy = false; nb.disabled = false; nb.textContent = d.pay === 'square' ? 'Request booking and pay' : 'Request booking';
        hint(e.message || 'We could not send your booking. Please call ' + CFG.phone + '.');
        if (/just taken/i.test(e.message)) { state.time = null; setTimeout(function () { go(2); }, 1800); }
      });
    }
    return { open: open };
  }

  var modalBooking = null;
  window.openBooking = function (key, optionIndex) {
    if (!modalBooking) modalBooking = create(document.body, true);
    return modalBooking.open(key, optionIndex || 0);
  };
  function mountAll() {
    document.querySelectorAll('[data-booking-calendar]:not([data-dgb-ready])').forEach(function (host) {
      host.setAttribute('data-dgb-ready', '1');
      var b = create(host, false); b.open(host.getAttribute('data-booking-calendar'), 0); host.booking = b;
    });
    document.querySelectorAll('[data-book]:not([data-dgb-ready])').forEach(function (btn) {
      btn.setAttribute('data-dgb-ready', '1');
      btn.addEventListener('click', function (e) { if (window.openBooking(btn.getAttribute('data-book'), +(btn.getAttribute('data-book-option') || 0))) e.preventDefault(); });
    });
  }
  window.DriveGoBooking = { mountAll: mountAll, open: window.openBooking };
  if (document.readyState !== 'loading') mountAll(); else document.addEventListener('DOMContentLoaded', mountAll);
})();
