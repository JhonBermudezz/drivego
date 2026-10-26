/* DriveGo London · booking calendar for services
   Opens from any [data-book] button when no Square Appointments link is set
   (see SQUARE_BOOKING_LINKS in main.js). Visitors pick an option, a day and a
   time, answer a few questions and either pay with a Square checkout link or
   send the request. Steven confirms each request. */

const BOOKING = {
  // Working hours per weekday (0 = Sunday). Times in 24 h, [open, close].
  hours: {
    0: ['09:00', '16:00'],
    1: ['09:00', '18:00'],
    2: ['09:00', '18:00'],
    3: ['09:00', '18:00'],
    4: ['09:00', '18:00'],
    5: ['09:00', '18:00'],
    6: ['09:00', '16:00'],
  },
  slotMinutes: 30, // start times every 30 minutes
  leadDays: 1, // earliest booking is tomorrow
  horizonDays: 60, // how far ahead people can book
  closedDates: [], // e.g. '2026-12-25'

  services: {
    lessons: {
      name: 'Single lesson',
      when: 'Lesson day and start time',
      options: [
        { label: '1 hour', minutes: 60, price: 65, pay: '' },
        { label: '1.5 hours', minutes: 90, price: 95, pay: '' },
        { label: '2 hours', minutes: 120, price: 125, pay: '' },
      ],
    },
    refresher: {
      name: 'G-Full & G2 Refresher',
      when: 'Lesson day and start time',
      options: [
        { label: '1 hour', minutes: 60, price: 65, pay: '' },
        { label: '1.5 hours', minutes: 90, price: 95, pay: '' },
        { label: '2 hours', minutes: 120, price: 125, pay: '' },
      ],
    },
    roadtest: {
      name: 'Road test package',
      when: 'Your DriveTest date and test time',
      options: [
        { label: 'G2 road test', minutes: 120, price: 189, pay: '' },
        { label: 'Full G road test', minutes: 120, price: 209, pay: '' },
      ],
    },
  },
};
// `pay` takes a Square checkout link for that option. When set, the last step
// shows "Pay with Square" and opens it after the request is sent.

(() => {
  const STEPS = ['Service', 'Day', 'Time', 'Details', 'Confirm'];
  const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const money = (n) => `$${n.toLocaleString('en-CA')}`;
  const pad = (n) => String(n).padStart(2, '0');
  const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  const toMin = (t) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
  const fmtTime = (min) => {
    const h = Math.floor(min / 60); const m = min % 60;
    return `${((h + 11) % 12) + 1}:${pad(m)} ${h < 12 ? 'AM' : 'PM'}`;
  };
  const fmtDate = (d) => d.toLocaleDateString('en-CA', { weekday: 'long', month: 'long', day: 'numeric' });
  const esc = (t) => String(t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

  function today() {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    return d;
  }

  function isOpenDay(d) {
    const start = today(); start.setDate(start.getDate() + BOOKING.leadDays);
    const end = today(); end.setDate(end.getDate() + BOOKING.horizonDays);
    return d >= start && d <= end && Boolean(BOOKING.hours[d.getDay()]) && !BOOKING.closedDates.includes(iso(d));
  }

  function slotsFor(d, minutes) {
    const hours = BOOKING.hours[d.getDay()];
    if (!hours) return [];
    const [open, close] = hours.map(toMin);
    const out = [];
    for (let t = open; t + minutes <= close; t += BOOKING.slotMinutes) out.push(t);
    return out;
  }

  function createBooking(host, modal) {
    let dialog = null;
    let state = null;

    function build() {
      dialog = document.createElement(modal ? 'dialog' : 'div');
      dialog.className = modal ? 'bk' : 'bk bk--inline';
      dialog.setAttribute('aria-labelledby', 'bk-title');
      dialog.innerHTML = `
        <div class="bk__panel">
          <aside class="bk__summary">
            <h2 class="bk__title" id="bk-title"></h2>
            <dl class="bk__facts" data-bk-facts></dl>
            <p class="bk__note"><i class="ph ph-lock-simple" aria-hidden="true"></i>Secure payment with Square</p>
          </aside>
          <div class="bk__main">
            <button type="button" class="bk__close" data-bk-close aria-label="Close booking"><i class="ph ph-x" aria-hidden="true"></i></button>
            <ol class="bk__steps" data-bk-steps></ol>
            <div class="bk__body" data-bk-body></div>
            <div class="bk__nav">
              <button type="button" class="btn btn--outline" data-bk-back>Back</button>
              <button type="button" class="btn btn--dark" data-bk-next>Continue</button>
            </div>
          </div>
        </div>`;
      host.appendChild(dialog);
      dialog.addEventListener('click', () => { state.touched = true; });
      if (modal) {
        dialog.querySelector('[data-bk-close]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });
        dialog.addEventListener('close', () => { document.body.style.overflow = ''; });
      } else {
        dialog.querySelector('[data-bk-close]').remove();
      }
      dialog.querySelector('[data-bk-back]').addEventListener('click', () => go(state.step - 1));
      dialog.querySelector('[data-bk-next]').addEventListener('click', next);
    }

    function open(key, optionIndex = 0) {
      const service = BOOKING.services[key];
      if (!service) return false;
      if (!dialog) build();
      const first = today(); first.setDate(first.getDate() + BOOKING.leadDays);
      state = { key, service, option: optionIndex, step: 0, date: null, time: null, month: new Date(first.getFullYear(), first.getMonth(), 1), details: {}, sent: false, touched: modal };
      dialog.querySelector('#bk-title').textContent = service.name;
      go(0);
      if (modal) {
        document.body.style.overflow = 'hidden';
        dialog.showModal();
      }
      return true;
    }

    function summary() {
      const o = state.service.options[state.option];
      const rows = [
        ['Option', o.label],
        ['Price', `${money(o.price)} + HST`],
        ['Day', state.date ? fmtDate(state.date) : 'Not chosen yet'],
        ['Time', state.time !== null ? fmtTime(state.time) : 'Not chosen yet'],
      ];
      dialog.querySelector('[data-bk-facts]').innerHTML = rows.map(([k, v]) => `<div><dt>${k}</dt><dd>${esc(v)}</dd></div>`).join('');
    }

    function go(step) {
      state.step = Math.max(0, Math.min(step, STEPS.length - 1));
      dialog.querySelector('[data-bk-steps]').innerHTML = STEPS.map((s, i) => `<li class="${i === state.step ? 'is-current' : ''}${i < state.step ? ' is-done' : ''}"><span>${i + 1}</span>${s}</li>`).join('');
      const body = dialog.querySelector('[data-bk-body]');
      const back = dialog.querySelector('[data-bk-back]');
      const nextBtn = dialog.querySelector('[data-bk-next]');
      back.hidden = state.step === 0 || state.sent;
      nextBtn.hidden = false;
      nextBtn.textContent = 'Continue';
      [renderOption, renderDay, renderTime, renderDetails, renderConfirm][state.step](body, nextBtn);
      summary();
      if (state.touched) body.querySelector('button, input, select')?.focus();
    }

    function next() {
      if (state.step === 1 && !state.date) return hint('Pick a day to continue.');
      if (state.step === 2 && state.time === null) return hint('Pick a time to continue.');
      if (state.step === 3 && !validDetails()) return;
      if (state.step === 4) return submit();
      go(state.step + 1);
    }

    function hint(text) {
      const el = dialog.querySelector('[data-bk-hint]');
      if (el) { el.textContent = text; el.hidden = false; }
    }

    function renderOption(body) {
      body.innerHTML = `<h3 class="bk__h">Choose an option</h3>
        <div class="bk__options">${state.service.options.map((o, i) => `
          <button type="button" class="bk__option" aria-pressed="${i === state.option}" data-i="${i}">
            <span class="bk__option-name">${esc(o.label)}</span>
            <span class="bk__option-price">${money(o.price)} <small>+ HST</small></span>
          </button>`).join('')}</div>`;
      body.querySelectorAll('.bk__option').forEach((b) => b.addEventListener('click', () => {
        state.option = Number(b.dataset.i);
        state.time = null;
        body.querySelectorAll('.bk__option').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
        summary();
      }));
    }

    function renderDay(body) {
      const m = state.month;
      const firstDow = new Date(m.getFullYear(), m.getMonth(), 1).getDay();
      const days = new Date(m.getFullYear(), m.getMonth() + 1, 0).getDate();
      const min = today(); min.setDate(1);
      const max = today(); max.setDate(max.getDate() + BOOKING.horizonDays);
      const canPrev = m > min;
      const canNext = new Date(m.getFullYear(), m.getMonth() + 1, 1) <= max;
      let cells = '';
      for (let i = 0; i < firstDow; i++) cells += '<span></span>';
      for (let d = 1; d <= days; d++) {
        const date = new Date(m.getFullYear(), m.getMonth(), d);
        const ok = isOpenDay(date);
        const sel = state.date && iso(date) === iso(state.date);
        cells += `<button type="button" class="bk__day" data-d="${d}" ${ok ? '' : 'disabled'} aria-pressed="${Boolean(sel)}" aria-label="${fmtDate(date)}${ok ? '' : ', not available'}">${d}</button>`;
      }
      body.innerHTML = `<h3 class="bk__h">${esc(state.service.when)}</h3>
        <div class="bk__cal">
          <div class="bk__cal-head">
            <button type="button" class="bk__cal-nav" data-m="-1" ${canPrev ? '' : 'disabled'} aria-label="Previous month"><i class="ph ph-caret-left" aria-hidden="true"></i></button>
            <p>${MONTHS[m.getMonth()]} ${m.getFullYear()}</p>
            <button type="button" class="bk__cal-nav" data-m="1" ${canNext ? '' : 'disabled'} aria-label="Next month"><i class="ph ph-caret-right" aria-hidden="true"></i></button>
          </div>
          <div class="bk__cal-grid">${DAYS.map((d) => `<span class="bk__dow">${d}</span>`).join('')}${cells}</div>
        </div>
        <p class="bk__hint" data-bk-hint hidden></p>`;
      body.querySelectorAll('.bk__cal-nav').forEach((b) => b.addEventListener('click', () => {
        state.month = new Date(m.getFullYear(), m.getMonth() + Number(b.dataset.m), 1);
        renderDay(body);
      }));
      body.querySelectorAll('.bk__day:not([disabled])').forEach((b) => b.addEventListener('click', () => {
        state.date = new Date(m.getFullYear(), m.getMonth(), Number(b.dataset.d));
        state.time = null;
        go(2);
      }));
    }

    function renderTime(body) {
      const o = state.service.options[state.option];
      const slots = slotsFor(state.date, o.minutes);
      const groups = [['Morning', (t) => t < 720], ['Afternoon', (t) => t >= 720 && t < 1020], ['Evening', (t) => t >= 1020]];
      body.innerHTML = `<h3 class="bk__h">${esc(fmtDate(state.date))}</h3>
        ${groups.map(([label, fn]) => {
          const list = slots.filter(fn);
          if (!list.length) return '';
          return `<p class="bk__group">${label}</p><div class="bk__slots">${list.map((t) => `<button type="button" class="bk__slot" data-t="${t}" aria-pressed="${state.time === t}">${fmtTime(t)}</button>`).join('')}</div>`;
        }).join('')}
        <p class="bk__hint" data-bk-hint hidden></p>`;
      body.querySelectorAll('.bk__slot').forEach((b) => b.addEventListener('click', () => {
        state.time = Number(b.dataset.t);
        go(3);
      }));
    }

    function field(id, label, input, opt = false) {
      return `<div class="field"><label for="${id}">${label}${opt ? ' <span class="field__opt">(optional)</span>' : ''}</label>${input}<p class="field__error" id="${id}-err"></p></div>`;
    }

    function renderDetails(body) {
      const d = state.details;
      const isTest = state.key === 'roadtest';
      const v = (k) => esc(d[k] || '');
      const sel = (k, opts) => opts.map((o) => `<option${d[k] === o ? ' selected' : ''}>${o}</option>`).join('');
      body.innerHTML = `<h3 class="bk__h">A few quick questions</h3>
        <div class="bk__form">
          <div class="field-row">
            ${field('bk-name', 'Full name', `<input id="bk-name" type="text" autocomplete="name" value="${v('name')}">`)}
            ${field('bk-phone', 'Phone or WhatsApp', `<input id="bk-phone" type="tel" autocomplete="tel" placeholder="(519) 000 0000" value="${v('phone')}">`)}
          </div>
          ${field('bk-email', 'Email', `<input id="bk-email" type="email" autocomplete="email" placeholder="you@email.com" value="${v('email')}">`)}
          <div class="field-row">
            ${field('bk-licence', 'Licence you have now', `<select id="bk-licence">${sel('licence', ['G1', 'G2', 'Full G', 'Licence from another country'])}</select>`)}
            ${field('bk-lang', 'Lesson language', `<select id="bk-lang">${sel('lang', ['English', 'Spanish'])}</select>`)}
          </div>
          ${isTest ? field('bk-centre', 'DriveTest centre', `<select id="bk-centre">${sel('centre', ['London', 'St. Thomas', 'Other'])}</select>`) : field('bk-meet', 'Where should we start?', `<select id="bk-meet">${sel('meet', ['Pick me up at home', 'Meet at White Oaks Mall'])}</select>`)}
          ${field('bk-address', 'Pick up address', `<input id="bk-address" type="text" autocomplete="street-address" placeholder="Street, London, ON" value="${v('address')}">`)}
          ${field('bk-notes', 'Anything we should know?', `<textarea id="bk-notes" rows="2" placeholder="For example, what you want to practise.">${v('notes')}</textarea>`, true)}
        </div>`;
      body.querySelectorAll('input').forEach((input) => input.addEventListener('input', () => {
      input.removeAttribute('aria-invalid');
      const err = body.querySelector(`#${input.id}-err`);
      if (err) err.textContent = '';
    }));
    const meet = body.querySelector('#bk-meet');
      const addr = body.querySelector('#bk-address').closest('.field');
      const sync = () => { addr.hidden = meet && meet.value !== 'Pick me up at home'; };
      if (meet) { meet.addEventListener('change', sync); sync(); }
    }

    function validDetails() {
      const g = (id) => dialog.querySelector(`#${id}`);
      const d = state.details;
      d.name = g('bk-name').value.trim();
      d.phone = g('bk-phone').value.trim();
      d.email = g('bk-email').value.trim();
      d.licence = g('bk-licence').value;
      d.lang = g('bk-lang').value;
      d.centre = g('bk-centre')?.value || '';
      d.meet = g('bk-meet')?.value || '';
      d.address = g('bk-address').value.trim();
      d.notes = g('bk-notes').value.trim();
      const needAddress = state.key === 'roadtest' || d.meet === 'Pick me up at home';
      const errors = {
        'bk-name': d.name.length >= 2 ? '' : 'Please enter your full name.',
        'bk-phone': d.phone.replace(/\D/g, '').length >= 10 ? '' : 'Enter a phone number with at least 10 digits.',
        'bk-email': /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(d.email) ? '' : 'Enter a valid email so we can send your confirmation.',
        'bk-address': !needAddress || d.address.length >= 5 ? '' : 'Tell us where to pick you up.',
      };
      let first = null;
      Object.entries(errors).forEach(([id, msg]) => {
        const input = g(id); const err = g(`${id}-err`);
        input.setAttribute('aria-invalid', msg ? 'true' : 'false');
        err.textContent = msg;
        if (msg && !first) first = input;
      });
      if (first) first.focus();
      return !first;
    }

    function renderConfirm(body, nextBtn) {
      const o = state.service.options[state.option];
      const d = state.details;
      const where = state.key === 'roadtest' ? `DriveTest ${d.centre}, pick up at ${d.address}` : (d.meet === 'Meet at White Oaks Mall' ? 'White Oaks Mall' : `Pick up at ${d.address}`);
      body.innerHTML = state.sent ? `
        <div class="bk__done">
          <span class="bk__done-icon"><i class="ph-fill ph-check-circle" aria-hidden="true"></i></span>
          <h3 class="bk__h">Request sent</h3>
          <p>We will confirm your ${esc(state.service.name.toLowerCase())} on ${esc(fmtDate(state.date))} at ${esc(fmtTime(state.time))} by phone or WhatsApp within one business day.</p>
          ${o.pay ? '<p>Your payment page opened in a new tab. If it did not, use the button below.</p>' : '<p>You can pay with Square, Interac e&#8209;Transfer or cash when we confirm.</p>'}
        </div>` : `
        <h3 class="bk__h">Check your booking</h3>
        <ul class="bk__review">
          <li><span>Service</span><strong>${esc(state.service.name)} · ${esc(o.label)}</strong></li>
          <li><span>When</span><strong>${esc(fmtDate(state.date))} at ${esc(fmtTime(state.time))}</strong></li>
          <li><span>Where</span><strong>${esc(where)}</strong></li>
          <li><span>Name</span><strong>${esc(d.name)} · ${esc(d.phone)}</strong></li>
          <li><span>Total</span><strong>${money(o.price)} + HST</strong></li>
        </ul>
        <p class="bk__small">Steven confirms every booking. Free reschedule up to 24 hours before.</p>
        <p class="bk__hint" data-bk-hint hidden></p>`;
      if (state.sent) {
        nextBtn.hidden = !o.pay;
        nextBtn.textContent = 'Open payment page';
        return;
      }
      nextBtn.innerHTML = o.pay ? '<i class="ph ph-lock-simple" aria-hidden="true"></i>Pay with Square' : 'Request booking';
    }

    async function submit() {
      const o = state.service.options[state.option];
      if (state.sent) { if (o.pay) window.open(o.pay, '_blank', 'noopener'); return; }
      const d = state.details;
      const data = {
        form: 'Booking calendar',
        service: `${state.service.name} · ${o.label}`,
        price: `${money(o.price)} + HST`,
        date: iso(state.date),
        time: fmtTime(state.time),
        ...d,
      };
      const nextBtn = dialog.querySelector('[data-bk-next]');
      nextBtn.classList.add('is-loading');
      try {
        if (typeof FORM_ENDPOINT !== 'undefined' && FORM_ENDPOINT) {
          const res = await fetch(FORM_ENDPOINT, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
          if (!res.ok) throw new Error(String(res.status));
        } else if (!window.DRIVEGO_PREVIEW) {
          const email = typeof CONTACT_EMAIL !== 'undefined' ? CONTACT_EMAIL : 'info@drivegodrivingschool.com';
          const text = Object.entries(data).map(([k, v]) => `${k}: ${v || 'not given'}`).join('\n');
          window.location.href = `mailto:${email}?subject=${encodeURIComponent(`Booking: ${data.service} on ${data.date}`)}&body=${encodeURIComponent(text)}`;
        }
        if (o.pay) window.open(o.pay, '_blank', 'noopener');
        state.sent = true;
        go(4);
      } catch {
        hint('We could not send your booking. Please try again or call (519) 694 3762.');
      } finally {
        nextBtn.classList.remove('is-loading');
      }
    }

      return { open };
  }

  let modalBooking = null;
  window.openBooking = (key, optionIndex = 0) => {
    if (!modalBooking) modalBooking = createBooking(document.body, true);
    return modalBooking.open(key, optionIndex);
  };

  // Inline calendars, e.g. <div data-booking-calendar="roadtest"></div>
  document.querySelectorAll('[data-booking-calendar]').forEach((host) => {
    const booking = createBooking(host, false);
    booking.open(host.dataset.bookingCalendar, 0);
    host.booking = booking;
  });

  // Buttons that pick an option in the inline calendar, e.g. data-calendar-option="roadtest:1"
  document.querySelectorAll('[data-calendar-option]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const [key, option] = btn.dataset.calendarOption.split(':');
      const host = document.querySelector(`[data-booking-calendar="${key}"]`);
      if (host && host.booking) host.booking.open(key, Number(option));
    });
  });
})();
