/* DriveGo London · site behaviour */

// Square checkout links for packages (Square Dashboard > Online checkout > Checkout links).
// Packages start with the theory course, so they only take a payment.
// While a link is empty, "Buy now" sends the visitor to the contact form
// with that package preselected, so no button is ever dead.
const SQUARE_CHECKOUT_LINKS = {
  basic: '',
  standard: '',
  complete: '',
};

// Square Appointments booking links for services that are booked on a calendar
// and paid at booking (Square Dashboard > Appointments > Online booking > share link).
// When a link is set, "Book and pay" opens it and the road test page shows the
// calendar inline instead of the request form.
const SQUARE_BOOKING_LINKS = {
  lessons: '',
  refresher: '',
  roadtest: '',
};

// Form backend (Formspree, Basin, Getform...). Leave empty to fall back to
// opening the visitor's email app with the message prefilled.
const FORM_ENDPOINT = '';
const CONTACT_EMAIL = 'info@drivegodrivingschool.com';

const PACKAGE_TOPICS = {
  basic: 'Package 1 · Basic',
  standard: 'Package 2 · Standard',
  complete: 'Package 3 · Complete',
};

document.documentElement.classList.add('js');

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

document.querySelectorAll('[data-year]').forEach((el) => {
  el.textContent = new Date().getFullYear();
});

/* Scroll reveal */
const revealObserver = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('is-in');
      revealObserver.unobserve(entry.target);
    });
  },
  { rootMargin: '0px 0px -4% 0px', threshold: 0.05 }
);
document.querySelectorAll('.reveal').forEach((el) => {
  // Stagger siblings that share a parent (cards in a grid)
  const index = Array.from(el.parentElement.children).filter((c) => c.classList.contains('reveal')).indexOf(el);
  el.style.transitionDelay = `${Math.min(index, 4) * 100}ms`;
  revealObserver.observe(el);
});

/* Tagline: words light up one at a time as they cross the trigger line */
document.querySelectorAll('[data-words]').forEach((el) => {
  const accent = new Set(['Four', 'months', 'sooner']);
  el.innerHTML = el.textContent
    .trim()
    .split(/\s+/)
    .map((word) => `<span class="w${accent.has(word) ? ' is-accent' : ''}">${word}</span>`)
    .join(' ');

  const words = el.querySelectorAll('.w');
  if (reduceMotion) {
    words.forEach((w) => w.classList.add('is-lit'));
    return;
  }

  const wordObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        const lit = entry.isIntersecting || entry.boundingClientRect.top < 0;
        entry.target.classList.toggle('is-lit', lit);
      });
    },
    { rootMargin: '0px 0px -45% 0px', threshold: 1 }
  );
  words.forEach((w) => wordObserver.observe(w));
});

/* Count up the About figures when they scroll into view */
function formatCount(value, el) {
  const decimals = Number(el.dataset.decimals || 0);
  return value.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + (el.dataset.suffix || '');
}

const counterObserver = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      counterObserver.unobserve(entry.target);
      const el = entry.target;
      const target = Number(el.dataset.count);
      const duration = 1800;
      const start = performance.now();
      const tick = (now) => {
        const t = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - t, 4);
        el.textContent = formatCount(t < 1 ? target * eased : target, el);
        if (t < 1) requestAnimationFrame(tick);
      };
      el.textContent = formatCount(0, el);
      requestAnimationFrame(tick);
    });
  },
  { threshold: 0.6 }
);
if (!reduceMotion) document.querySelectorAll('[data-count]').forEach((el) => counterObserver.observe(el));

/* Hero slider */
const slider = document.querySelector('[data-slider]');

if (slider) {
  const slides = Array.from(slider.querySelectorAll('[data-slide]'));
  const lines = Array.from(slider.querySelectorAll('[data-dot]'));
  const DELAY = 7000;
  let current = 0;
  let timer = null;
  let startedAt = 0;
  let remaining = DELAY;
  let holding = false;

  slider.style.setProperty('--slide-delay', `${DELAY}ms`);
  if (reduceMotion) slider.classList.add('is-static');

  function show(index) {
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, i) => {
      const active = i === current;
      slide.classList.toggle('is-active', active);
      slide.setAttribute('aria-hidden', String(!active));
      slide.inert = !active;
    });
    lines.forEach((line, i) => {
      line.removeAttribute('aria-current');
      if (i === current) {
        void line.offsetWidth; // restart the fill animation
        line.setAttribute('aria-current', 'true');
      }
    });
    remaining = DELAY;
    run();
  }

  function run() {
    clearTimeout(timer);
    if (reduceMotion || holding || document.hidden) return;
    startedAt = performance.now();
    timer = setTimeout(() => show(current + 1), remaining);
  }

  // Pause while the visitor reads or uses a slide, then finish the remaining time
  function hold(value) {
    if (value === holding) return;
    holding = value;
    slider.classList.toggle('is-paused', holding);
    if (holding) {
      clearTimeout(timer);
      remaining = Math.max(0, remaining - (performance.now() - startedAt));
    } else {
      run();
    }
  }

  lines.forEach((line) => line.addEventListener('click', () => show(Number(line.dataset.dot))));
  slider.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse') hold(true); });
  slider.addEventListener('pointerleave', () => hold(false));
  slider.addEventListener('focusin', () => hold(true));
  slider.addEventListener('focusout', () => hold(false));
  slider.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight') show(current + 1);
    if (e.key === 'ArrowLeft') show(current - 1);
  });

  let startX = null;
  slider.addEventListener('touchstart', (e) => { startX = e.touches[0].clientX; }, { passive: true });
  slider.addEventListener('touchend', (e) => {
    if (startX === null) return;
    const dx = e.changedTouches[0].clientX - startX;
    if (Math.abs(dx) > 50) show(current + (dx < 0 ? 1 : -1));
    startX = null;
  });

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) hold(true);
    else hold(false);
  });

  show(0);
}

/* Mobile menu */
const burger = document.querySelector('.burger');
const menu = document.getElementById('mobile-menu');

function setMenu(open) {
  burger.setAttribute('aria-expanded', String(open));
  burger.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  document.body.style.overflow = open ? 'hidden' : '';
  if (open) {
    menu.hidden = false;
    requestAnimationFrame(() => requestAnimationFrame(() => menu.classList.add('is-open')));
  } else {
    menu.classList.remove('is-open');
    setTimeout(() => {
      if (burger.getAttribute('aria-expanded') === 'false') menu.hidden = true;
    }, 700);
  }
}

if (burger && menu) {
  burger.addEventListener('click', () => setMenu(burger.getAttribute('aria-expanded') !== 'true'));
  menu.addEventListener('click', (e) => {
    if (e.target.closest('a')) setMenu(false);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && burger.getAttribute('aria-expanded') === 'true') {
      setMenu(false);
      burger.focus();
    }
  });
}

/* Current section in the nav */
const navLinks = document.querySelectorAll('.nav__link[data-spy]');
const spyTargets = Array.from(navLinks)
  .map((link) => (link.dataset.spy === 'top' ? document.querySelector('.hero') : document.getElementById(link.dataset.spy)))
  .filter(Boolean);

function setCurrent(id) {
  navLinks.forEach((link) => {
    if (link.dataset.spy === id) link.setAttribute('aria-current', 'true');
    else link.removeAttribute('aria-current');
  });
}

const spyObserver = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      setCurrent(entry.target.classList.contains('hero') ? 'top' : entry.target.id);
    });
  },
  { rootMargin: '-45% 0px -50% 0px' }
);
spyTargets.forEach((el) => spyObserver.observe(el));
if (navLinks.length) setCurrent('top');

/* Preselect a topic in the contact form */
const topicSelect = document.getElementById('f-topic');

function selectTopic(topic) {
  if (!topicSelect || !topic) return;
  const option = Array.from(topicSelect.options).find((o) => o.text === topic);
  if (option) topicSelect.value = option.text;
}

/* Square checkout buttons (packages) */
// Pages without the form send the visitor to contact.html with the topic preselected
const contactHref = (topic) => (topicSelect ? '#contact' : `contact.html?topic=${encodeURIComponent(topic)}#contact`);

document.querySelectorAll('[data-checkout]').forEach((btn) => {
  const key = btn.dataset.checkout;
  const link = SQUARE_CHECKOUT_LINKS[key];
  if (link) {
    btn.href = link;
    btn.rel = 'noopener';
  } else {
    btn.href = contactHref(PACKAGE_TOPICS[key]);
    btn.addEventListener('click', () => selectTopic(PACKAGE_TOPICS[key]));
  }
});

document.querySelectorAll('[data-topic]').forEach((link) => {
  link.href = contactHref(link.dataset.topic);
  link.addEventListener('click', () => selectTopic(link.dataset.topic));
});

/* Square Appointments booking (services) */
document.querySelectorAll('[data-book]').forEach((btn) => {
  const link = SQUARE_BOOKING_LINKS[btn.dataset.book];
  if (link) {
    btn.href = link;
    btn.target = '_blank';
    btn.rel = 'noopener';
  } else {
    // No Square link yet: open the booking calendar (assets/js/booking.js)
    btn.addEventListener('click', (e) => {
      if (window.openBooking && window.openBooking(btn.dataset.book, Number(btn.dataset.bookOption || 0))) e.preventDefault();
    });
  }
});

document.querySelectorAll('[data-booking-embed]').forEach((box) => {
  const link = SQUARE_BOOKING_LINKS[box.dataset.bookingEmbed];
  if (!link) return;
  box.innerHTML = `<iframe class="booking__frame" src="${link}" title="Book and pay online" loading="lazy"></iframe>`;
});

selectTopic(new URLSearchParams(window.location.search).get('topic'));

// Buttons that preselect an option in a form, e.g. data-select="rt-type:Full G road test"
document.querySelectorAll('[data-select]').forEach((btn) => {
  btn.addEventListener('click', () => {
    const [id, value] = btn.dataset.select.split(':');
    const field = document.getElementById(id);
    if (field) field.value = value;
  });
});

/* Single lesson length picker */
document.querySelectorAll('.duration').forEach((group) => {
  const price = group.parentElement.querySelector('[data-duration-price]');
  const options = group.querySelectorAll('[role="radio"]');
  options.forEach((option, i) => {
    option.addEventListener('click', () => {
      options.forEach((o) => o.setAttribute('aria-checked', String(o === option)));
      if (price) price.textContent = option.dataset.price;
    });
    option.addEventListener('keydown', (e) => {
      if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
      const next = options[(i + (e.key === 'ArrowRight' ? 1 : options.length - 1)) % options.length];
      next.focus();
      next.click();
    });
  });
});

/* Forms (contact and road test booking requests) */
const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
const rules = {
  name: (v) => (v.trim().length >= 2 ? '' : 'Please enter your full name.'),
  email: (v) => {
    if (!v.trim()) return 'Please enter your email.';
    return emailPattern.test(v.trim()) ? '' : 'This email looks incomplete. Check it and try again.';
  },
  phone: (v, input) => {
    if (!v.trim()) return input.required ? 'Please enter a phone number so we can confirm.' : '';
    return v.replace(/\D/g, '').length >= 10 ? '' : 'Phone numbers need at least 10 digits.';
  },
  message: (v) => (v.trim().length >= 10 ? '' : 'Tell us a little more so we can help (10 characters minimum).'),
  test_date: (v) => (v ? '' : 'Pick the date of your road test.'),
  pickup: (v) => (v.trim().length >= 5 ? '' : 'Tell us where to pick you up.'),
};

document.querySelectorAll('form[data-form]').forEach((form) => {
  const status = form.querySelector('.form__status');
  const submit = form.querySelector('button[type="submit"]');
  const label = submit.querySelector('.btn__label');
  const subject = form.dataset.form;
  const done = form.dataset.done || 'Message sent. We will reply within one business day.';

  function validateField(input) {
    const rule = rules[input.name];
    if (!rule) return true;
    const message = rule(input.value, input);
    const error = document.getElementById(`${input.id}-err`);
    input.setAttribute('aria-invalid', message ? 'true' : 'false');
    if (error) error.textContent = message;
    if (message && error) input.setAttribute('aria-describedby', error.id);
    else input.removeAttribute('aria-describedby');
    return !message;
  }

  const fields = Array.from(form.querySelectorAll('input, textarea'));
  fields.forEach((input) => {
    input.addEventListener('blur', () => validateField(input));
    input.addEventListener('input', () => {
      if (input.getAttribute('aria-invalid') === 'true') validateField(input);
    });
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    status.textContent = '';
    status.className = 'form__status';

    const invalid = fields.filter((f) => !validateField(f));
    if (invalid.length) {
      invalid[0].focus();
      return;
    }

    const data = Object.fromEntries(new FormData(form));
    data.form = subject;

    if (!FORM_ENDPOINT) {
      const body = Object.entries(data).map(([k, v]) => `${k}: ${v || 'not given'}`).join('\n');
      window.location.href = `mailto:${CONTACT_EMAIL}?subject=${encodeURIComponent(`Website: ${data.topic || subject}`)}&body=${encodeURIComponent(body)}`;
      status.textContent = 'Your email app should open with the message ready to send.';
      status.classList.add('is-ok');
      return;
    }

    submit.classList.add('is-loading');
    label.textContent = 'Sending';
    try {
      const res = await fetch(FORM_ENDPOINT, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });
      if (!res.ok) throw new Error(String(res.status));
      form.reset();
      fields.forEach((f) => f.removeAttribute('aria-invalid'));
      status.textContent = done;
      status.classList.add('is-ok');
    } catch {
      status.textContent = `We could not send your request. Please try again or email ${CONTACT_EMAIL}.`;
      status.classList.add('is-err');
    } finally {
      submit.classList.remove('is-loading');
      label.textContent = submit.dataset.label || 'Send message';
    }
  });
});


/* Floating WhatsApp button */
(function () {
  if (document.querySelector('.wa-float')) return;
  var a = document.createElement('a');
  a.className = 'wa-float';
  a.href = 'https://wa.me/15196943762?text=' + encodeURIComponent('Hi DriveGo London, I would like information about driving lessons.');
  a.target = '_blank'; a.rel = 'noopener';
  a.setAttribute('aria-label', 'Chat with us on WhatsApp');
  a.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg><span class="wa-float__tip">Chat on WhatsApp</span>';
  document.body.appendChild(a);
})();
