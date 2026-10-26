/* DriveGo spam protection: adds a trap field, a timestamp and a reCAPTCHA v3 token to the Elementor form request. */
(function () {
  'use strict';
  var CFG = window.DGBSPAM || {};
  var started = Date.now();
  var loaded = null;
  function loadRecaptcha() {
    if (!CFG.site) return Promise.resolve(null);
    if (loaded) return loaded;
    loaded = new Promise(function (resolve) {
      if (window.grecaptcha && window.grecaptcha.execute) return resolve(window.grecaptcha);
      var s = document.createElement('script');
      s.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(CFG.site);
      s.async = true;
      s.onload = function () { window.grecaptcha.ready(function () { resolve(window.grecaptcha); }); };
      s.onerror = function () { resolve(null); };
      document.head.appendChild(s);
    });
    return loaded;
  }
  function token() {
    return loadRecaptcha().then(function (g) { return g ? g.execute(CFG.site, { action: CFG.action }).catch(function () { return ''; }) : ''; });
  }
  function isFormRequest(url, body) {
    return typeof url === 'string' && url.indexOf('admin-ajax.php') > -1 && body && typeof body.get === 'function' && String(body.get('action')) === 'elementor_pro_atomic_forms_send_form';
  }
  /* Loader: the request waits on reCAPTCHA and the mail server, so show the visitor that it is sending. */
  var lastForm = null;
  var css = document.createElement('style');
  css.textContent = '@keyframes dgbspin{to{transform:rotate(360deg)}}' +
    'form.e-form-base.dgb-sending button[type=submit]{pointer-events:none;opacity:.8;position:relative}' +
    'form.e-form-base.dgb-sending button[type=submit]::after{content:"";display:inline-block;width:1em;height:1em;margin-left:.6em;vertical-align:-.15em;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:dgbspin .7s linear infinite}' +
    '@media (prefers-reduced-motion:reduce){form.e-form-base.dgb-sending button[type=submit]::after{animation-duration:2s}}';
  document.head.appendChild(css);
  document.addEventListener('submit', function (e) { if (e.target && e.target.matches && e.target.matches('form.e-form-base')) lastForm = e.target; }, true);
  function busy(on) {
    var f = lastForm || document.querySelector('form.e-form-base');
    if (!f) return function () {};
    f.classList.toggle('dgb-sending', on);
    f.setAttribute('aria-busy', on ? 'true' : 'false');
    return function () { f.classList.remove('dgb-sending'); f.setAttribute('aria-busy', 'false'); };
  }
  function decorate(body) {
    body.set('dgb_ts', String(started));
    body.set('dgb_hp', (document.querySelector('input[name="dgb_hp"]') || { value: '' }).value);
    return token().then(function (t) { if (t) body.set('dgb_recaptcha', t); return body; });
  }
  /* Elementor sends the form with fetch (or XHR in older builds): add the extra fields to either. */
  var nativeFetch = window.fetch;
  if (nativeFetch) {
    window.fetch = function (input, init) {
      var url = typeof input === 'string' ? input : (input && input.url) || '';
      var body = init && init.body;
      if (isFormRequest(url, body)) {
        var done = busy(true);
        return decorate(body).then(function () { return nativeFetch.call(window, input, init); }).then(function (r) { done(); return r; }, function (e) { done(); throw e; });
      }
      return nativeFetch.apply(window, arguments);
    };
  }
  var open = XMLHttpRequest.prototype.open, send = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.open = function (m, u) { this.__dgbUrl = u; return open.apply(this, arguments); };
  XMLHttpRequest.prototype.send = function (body) {
    var xhr = this, args = arguments;
    if (isFormRequest(this.__dgbUrl, body)) {
      var done = busy(true);
      xhr.addEventListener('loadend', done);
      decorate(body).then(function () { send.apply(xhr, args); });
      return;
    }
    return send.apply(this, args);
  };
  /* Trap field: invisible to people, tempting to bots. */
  function addTrap() {
    document.querySelectorAll('form.e-form-base:not([data-dgb-trap])').forEach(function (f) {
      f.setAttribute('data-dgb-trap', '1');
      var i = document.createElement('input');
      i.type = 'text'; i.name = 'dgb_hp'; i.tabIndex = -1; i.autocomplete = 'off'; i.setAttribute('aria-hidden', 'true');
      i.style.cssText = 'position:absolute;left:-9999px;width:1px;height:1px;opacity:0';
      f.appendChild(i);
      if (CFG.site) f.addEventListener('focusin', loadRecaptcha, { once: true });
    });
  }
  if (document.readyState !== 'loading') addTrap(); else document.addEventListener('DOMContentLoaded', addTrap);
  window.addEventListener('load', addTrap);
})();
