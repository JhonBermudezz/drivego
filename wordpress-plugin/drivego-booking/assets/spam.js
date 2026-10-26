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
      if (isFormRequest(url, body)) { return decorate(body).then(function () { return nativeFetch.call(window, input, init); }); }
      return nativeFetch.apply(window, arguments);
    };
  }
  var open = XMLHttpRequest.prototype.open, send = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.open = function (m, u) { this.__dgbUrl = u; return open.apply(this, arguments); };
  XMLHttpRequest.prototype.send = function (body) {
    var xhr = this, args = arguments;
    if (isFormRequest(this.__dgbUrl, body)) { decorate(body).then(function () { send.apply(xhr, args); }); return; }
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
