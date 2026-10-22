/* Drive Go London · site behaviour */

// Paste the Stripe Payment Links here (Stripe Dashboard > Payment Links).
// While a link is empty, "Buy now" sends the visitor to the contact form
// with that package preselected, so no button is ever dead.
const STRIPE_LINKS = {
  basic: '',
  standard: '',
  complete: '',
};

// Form backend (Formspree, Basin, Getform...). Leave empty to fall back to
// opening the visitor's email app with the message prefilled.
const FORM_ENDPOINT = '';
const CONTACT_EMAIL = 'info@drivegolondon.ca';

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
  { rootMargin: '0px 0px -10% 0px', threshold: 0.1 }
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
  const dots = Array.from(slider.querySelectorAll('[data-dot]'));
  const toggle = slider.querySelector('[data-toggle]');
  const DELAY = 7000;
  let current = 0;
  let timer = null;
  let paused = reduceMotion;
  let hovering = false;

  function show(index) {
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, i) => {
      const active = i === current;
      slide.classList.toggle('is-active', active);
      slide.setAttribute('aria-hidden', String(!active));
      slide.inert = !active;
    });
    dots.forEach((dot, i) => {
      if (i === current) dot.setAttribute('aria-current', 'true');
      else dot.removeAttribute('aria-current');
    });
  }

  function schedule() {
    clearTimeout(timer);
    if (!paused && !hovering) timer = setTimeout(() => { show(current + 1); schedule(); }, DELAY);
  }

  function setPaused(value) {
    paused = value;
    toggle.setAttribute('aria-label', paused ? 'Play slides' : 'Pause slides');
    toggle.innerHTML = `<i class="ph ph-${paused ? 'play' : 'pause'}" aria-hidden="true"></i>`;
    schedule();
  }

  slider.querySelector('[data-next]').addEventListener('click', () => { show(current + 1); schedule(); });
  slider.querySelector('[data-prev]').addEventListener('click', () => { show(current - 1); schedule(); });
  dots.forEach((dot) => dot.addEventListener('click', () => { show(Number(dot.dataset.dot)); schedule(); }));
  toggle.addEventListener('click', () => setPaused(!paused));

  // Hold still while the visitor reads or uses a slide
  slider.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse') { hovering = true; schedule(); } });
  slider.addEventListener('pointerleave', () => { hovering = false; schedule(); });
  slider.addEventListener('focusin', () => { hovering = true; schedule(); });
  slider.addEventListener('focusout', () => { hovering = false; schedule(); });

  slider.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight') { show(current + 1); schedule(); }
    if (e.key === 'ArrowLeft') { show(current - 1); schedule(); }
  });

  // Swipe on touch screens
  let startX = null;
  slider.addEventListener('touchstart', (e) => { startX = e.touches[0].clientX; }, { passive: true });
  slider.addEventListener('touchend', (e) => {
    if (startX === null) return;
    const dx = e.changedTouches[0].clientX - startX;
    if (Math.abs(dx) > 50) { show(current + (dx < 0 ? 1 : -1)); schedule(); }
    startX = null;
  });

  // Stop rotating while the tab is hidden
  document.addEventListener('visibilitychange', () => { if (document.hidden) clearTimeout(timer); else schedule(); });

  show(0);
  setPaused(paused);
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

/* Stripe checkout buttons */
document.querySelectorAll('[data-checkout]').forEach((btn) => {
  const key = btn.dataset.checkout;
  const link = STRIPE_LINKS[key];
  if (link) {
    btn.href = link;
    btn.rel = 'noopener';
  } else {
    btn.addEventListener('click', () => selectTopic(PACKAGE_TOPICS[key]));
  }
});

document.querySelectorAll('[data-topic]').forEach((link) => {
  link.addEventListener('click', () => selectTopic(link.dataset.topic));
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

/* Contact form */
const form = document.getElementById('contact-form');

if (form) {
  const status = form.querySelector('.form__status');
  const submit = form.querySelector('button[type="submit"]');
  const label = submit.querySelector('.btn__label');
  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

  const rules = {
    name: (v) => (v.trim().length >= 2 ? '' : 'Please enter your full name.'),
    email: (v) => {
      if (!v.trim()) return 'Please enter your email.';
      return emailPattern.test(v.trim()) ? '' : 'This email looks incomplete. Check it and try again.';
    },
    phone: (v) => (!v.trim() || v.replace(/\D/g, '').length >= 10 ? '' : 'Phone numbers need at least 10 digits.'),
    message: (v) => (v.trim().length >= 10 ? '' : 'Tell us a little more so we can help (10 characters minimum).'),
  };

  function validateField(input) {
    const rule = rules[input.name];
    if (!rule) return true;
    const message = rule(input.value);
    const error = document.getElementById(`${input.id}-err`);
    input.setAttribute('aria-invalid', message ? 'true' : 'false');
    if (error) error.textContent = message;
    if (message) input.setAttribute('aria-describedby', error.id);
    else input.removeAttribute('aria-describedby');
    return !message;
  }

  form.querySelectorAll('input, textarea').forEach((input) => {
    input.addEventListener('blur', () => validateField(input));
    input.addEventListener('input', () => {
      if (input.getAttribute('aria-invalid') === 'true') validateField(input);
    });
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    status.textContent = '';
    status.className = 'form__status';

    const fields = Array.from(form.querySelectorAll('input, textarea'));
    const invalid = fields.filter((f) => !validateField(f));
    if (invalid.length) {
      invalid[0].focus();
      return;
    }

    const data = Object.fromEntries(new FormData(form));

    if (!FORM_ENDPOINT) {
      const body = `Name: ${data.name}\nEmail: ${data.email}\nPhone: ${data.phone || 'not given'}\nTopic: ${data.topic}\n\n${data.message}`;
      window.location.href = `mailto:${CONTACT_EMAIL}?subject=${encodeURIComponent(`Website enquiry: ${data.topic}`)}&body=${encodeURIComponent(body)}`;
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
      status.textContent = 'Message sent. We will reply within one business day.';
      status.classList.add('is-ok');
    } catch {
      status.textContent = `We could not send your message. Please try again or email ${CONTACT_EMAIL}.`;
      status.classList.add('is-err');
    } finally {
      submit.classList.remove('is-loading');
      label.textContent = 'Send message';
    }
  });
}
