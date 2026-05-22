/* SelfAI — Client-side i18n bootstrap
 *
 * The PHP layout already injected `window.__SELFAI__.i18n` with:
 *   { locale, dir, info, locales, strings }
 *
 * This file:
 *   1. Exposes `SelfAII18n.t(key, args?)` for runtime translation.
 *   2. Auto-translates any element with [data-i18n], [data-i18n-aria],
 *      [data-i18n-placeholder] attributes on DOMContentLoaded.
 *   3. Wires the language switcher in the navbar.
 *   4. Re-runs translation when locale changes (without full page reload
 *      for cached/static text, but does reload the page to refresh
 *      server-rendered strings — kept simple per "first make it work").
 */
(function (global) {
  'use strict';

  const bootstrap = (global.__SELFAI__ && global.__SELFAI__.i18n) || {
    locale: 'en', dir: 'ltr', info: {}, locales: {}, strings: {}
  };

  const state = {
    locale:  bootstrap.locale,
    dir:     bootstrap.dir,
    info:    bootstrap.info,
    locales: bootstrap.locales,
    strings: bootstrap.strings || {}
  };

  /** Translate a dotted key. Optional `args` like `{name: 'Maher'}` will be
   *  applied via simple `{token}` placeholder substitution. */
  function t(key, args) {
    let v = state.strings[key];
    if (v == null) return key;
    if (args && typeof args === 'object') {
      v = v.replace(/\{(\w+)\}/g, (m, k) => (k in args ? String(args[k]) : m));
    }
    return v;
  }

  /** Apply translations to any element marked with data-i18n*. */
  function applyToDom(root) {
    root = root || document;
    // textContent
    root.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.getAttribute('data-i18n');
      const txt = t(key);
      if (txt && txt !== key) el.textContent = txt;
    });
    // aria-label
    root.querySelectorAll('[data-i18n-aria]').forEach(el => {
      const key = el.getAttribute('data-i18n-aria');
      const txt = t(key);
      if (txt && txt !== key) el.setAttribute('aria-label', txt);
    });
    // placeholder
    root.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
      const key = el.getAttribute('data-i18n-placeholder');
      const txt = t(key);
      if (txt && txt !== key) el.setAttribute('placeholder', txt);
    });
    // composite placeholder: prefix + dynamic suffix (e.g. "Talk to <code>")
    root.querySelectorAll('[data-i18n-placeholder-prefix]').forEach(el => {
      const key = el.getAttribute('data-i18n-placeholder-prefix');
      const suffix = el.getAttribute('data-clone-code') || '';
      const txt = t(key);
      if (txt && txt !== key) el.setAttribute('placeholder', `${txt} ${suffix}…`);
    });
    // title
    root.querySelectorAll('[data-i18n-title]').forEach(el => {
      const key = el.getAttribute('data-i18n-title');
      const txt = t(key);
      if (txt && txt !== key) el.setAttribute('title', txt);
    });
  }

  /** Switch the active locale. Persists via cookie + full page reload so
   *  PHP-rendered strings (page titles, dashboard labels) update too. */
  async function setLocale(lang) {
    if (!lang || lang === state.locale) return;
    try {
      const res = await fetch('api/i18n.php?action=set&lang=' + encodeURIComponent(lang), {
        credentials: 'same-origin'
      });
      const json = await res.json();
      if (!res.ok || !json.ok) {
        console.warn('[selfai-i18n] failed to set locale:', json);
        return;
      }
      // Update local state so any tooltips set in same tick already use new strings.
      state.locale  = json.locale;
      state.info    = json.info;
      state.strings = json.strings;
      state.dir     = json.info.dir;
      // Reload to let PHP re-render with the new locale.
      window.location.reload();
    } catch (err) {
      console.error('[selfai-i18n] setLocale error:', err);
    }
  }

  /** Wire the navbar language switcher (a <details><menu>…</menu></details>). */
  function wireLanguageSwitcher() {
    const sw = document.querySelector('[data-component="lang-switcher"]');
    if (!sw) return;
    sw.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-lang]');
      if (!btn) return;
      e.preventDefault();
      const lang = btn.getAttribute('data-lang');
      // Close the dropdown immediately for snappy UX.
      sw.removeAttribute('open');
      setLocale(lang);
    });
    // Close on outside click
    document.addEventListener('click', (e) => {
      if (!sw.contains(e.target)) sw.removeAttribute('open');
    });
    // Close on Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') sw.removeAttribute('open');
    });
  }

  /** Format an ISO timestamp using the active locale. */
  function formatTime(iso) {
    if (!iso) return '';
    try {
      const d = new Date(iso.replace(' ', 'T') + (iso.endsWith('Z') ? '' : 'Z'));
      if (isNaN(d.getTime())) return iso;
      return new Intl.DateTimeFormat(state.locale || 'en', {
        hour: '2-digit', minute: '2-digit',
        month: 'short', day: '2-digit'
      }).format(d);
    } catch (_) { return iso; }
  }

  global.SelfAII18n = {
    t,
    applyToDom,
    setLocale,
    formatTime,
    get locale()  { return state.locale; },
    get dir()     { return state.dir; },
    get info()    { return state.info; },
    get locales() { return state.locales; }
  };

  // Auto-init
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      applyToDom();
      wireLanguageSwitcher();
    });
  } else {
    applyToDom();
    wireLanguageSwitcher();
  }
})(window);
