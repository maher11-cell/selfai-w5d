/* W5D — UX enhancements
 * Loader / asides / active states. Guarded against double-init.
 */
(function () {
  if (window.__w5dEnhanced) return;
  window.__w5dEnhanced = true;

  // ========== 1. LOADER ==========
  function injectLoader() {
    if (document.getElementById('w5d-loader')) return;
    if (!document.body) {
      document.addEventListener('DOMContentLoaded', injectLoader, { once: true });
      return;
    }
    const loader = document.createElement('div');
    loader.id = 'w5d-loader';
    loader.setAttribute('aria-hidden', 'true');
    loader.innerHTML = `
      <div class="loader-inner">
        <div class="loader-mark"><span>W5</span></div>
        <div class="loader-text">Loading W5D…</div>
        <div class="loader-bar" role="progressbar" aria-label="Loading"></div>
      </div>`;
    document.body.appendChild(loader);
  }
  function hideLoader() {
    const l = document.getElementById('w5d-loader');
    if (!l) return;
    setTimeout(() => l.classList.add('is-hidden'), 200);
    setTimeout(() => l.parentNode && l.parentNode.removeChild(l), 1100);
  }
  injectLoader();
  if (document.readyState === 'complete') {
    setTimeout(hideLoader, 700);
  } else {
    window.addEventListener('load', () => setTimeout(hideLoader, 400));
  }
  setTimeout(hideLoader, 6000);

  // ========== 2. ACTIVE STATES ==========
  function currentPageKey() {
    const path = location.pathname.split('/').pop() || 'index.html';
    return (path || 'index.html').toLowerCase();
  }
  function applyActiveStates() {
    const here = currentPageKey();
    document.querySelectorAll('[data-mega-host] [data-mega-trigger]').forEach(li => {
      let hasActive = false;
      li.querySelectorAll('[data-mega-panel] a').forEach(a => {
        const href = (a.getAttribute('href') || '').split('/').pop().split('#')[0].toLowerCase();
        if (href && href === here) { a.setAttribute('data-active-link',''); hasActive = true; }
      });
      if (hasActive) li.setAttribute('data-active','');
    });
    document.querySelectorAll('[data-mega-mobile] a').forEach(a => {
      const href = (a.getAttribute('href') || '').split('/').pop().split('#')[0].toLowerCase();
      if (href === here) a.setAttribute('data-active-link','');
    });
  }

  // ========== 3. ASIDES ==========
  const LANGS = [
    { code:'en', flag:'🇬🇧', name:'English' },
    { code:'fr', flag:'🇫🇷', name:'Français' },
    { code:'es', flag:'🇪🇸', name:'Español' },
    { code:'de', flag:'🇩🇪', name:'Deutsch' },
    { code:'pt', flag:'🇵🇹', name:'Português' },
    { code:'it', flag:'🇮🇹', name:'Italiano' },
    { code:'ar', flag:'🇸🇦', name:'العربية' },
    { code:'zh', flag:'🇨🇳', name:'中文' }
  ];
  function buildAsides() {
    if (!document.body) return;
    if (document.getElementById('w5d-aside-host')) return;
    const host = document.createElement('div');
    host.id = 'w5d-aside-host';
    host.innerHTML = `
      <div class="w5d-aside-backdrop" data-aside-close></div>
      <aside class="w5d-aside" id="w5d-aside-user" role="dialog" aria-label="User account" aria-hidden="true">
        <header><h3>Your account</h3>
          <button class="btn-ghost rounded-lg w-9 h-9 flex items-center justify-center" data-aside-close aria-label="Close"><i data-lucide="x" class="w-5 h-5"></i></button>
        </header>
        <div class="aside-body">
          <div class="flex items-center gap-3"><span class="user-avatar">G</span>
            <div><p class="font-semibold">Guest</p><p class="text-xs opacity-70">Sign in to access your workspace</p></div>
          </div>
          <div class="mt-5 space-y-2">
            <button class="aside-action" data-aside-act="signin"><i data-lucide="log-in" class="w-4 h-4 text-violet-400"></i><span>Sign in</span></button>
            <button class="aside-action" data-aside-act="signup"><i data-lucide="user-plus" class="w-4 h-4 text-cyan-400"></i><span>Create account</span></button>
            <a class="aside-action" href="#"><i data-lucide="layout-dashboard" class="w-4 h-4 text-pink-400"></i><span>Dashboard</span></a>
            <a class="aside-action" href="#"><i data-lucide="bell" class="w-4 h-4 text-amber-400"></i><span>Notifications</span></a>
            <a class="aside-action" href="#"><i data-lucide="settings" class="w-4 h-4 text-emerald-400"></i><span>Settings</span></a>
          </div>
        </div>
      </aside>
      <aside class="w5d-aside" id="w5d-aside-lang" role="dialog" aria-label="Choose language" aria-hidden="true">
        <header><h3>Language</h3>
          <button class="btn-ghost rounded-lg w-9 h-9 flex items-center justify-center" data-aside-close aria-label="Close"><i data-lucide="x" class="w-5 h-5"></i></button>
        </header>
        <div class="aside-body">
          <p class="text-sm opacity-70 mb-4">Pick your preferred interface language.</p>
          <div class="lang-grid">
            ${LANGS.map(l => `<button class="lang-item" data-lang="${l.code}" aria-selected="false"><span>${l.flag}</span><span class="font-medium">${l.name}</span></button>`).join('')}
          </div>
        </div>
      </aside>`;
    document.body.appendChild(host);
  }

  function openAside(id) {
    const aside = document.getElementById(id);
    const backdrop = document.querySelector('.w5d-aside-backdrop');
    if (!aside) return;
    document.querySelectorAll('.w5d-aside').forEach(a => { a.classList.remove('is-open'); a.setAttribute('aria-hidden','true'); });
    aside.classList.add('is-open');
    aside.setAttribute('aria-hidden','false');
    backdrop && backdrop.classList.add('is-open');
  }
  function closeAsides() {
    document.querySelectorAll('.w5d-aside').forEach(a => { a.classList.remove('is-open'); a.setAttribute('aria-hidden','true'); });
    document.querySelectorAll('.w5d-aside-backdrop').forEach(b => b.classList.remove('is-open'));
  }
  function wireAsides() {
    document.querySelectorAll('[data-aside-toggle]').forEach(btn => {
      if (btn.dataset.asideWired) return;
      btn.dataset.asideWired = '1';
      btn.addEventListener('click', () => openAside(btn.dataset.asideToggle));
    });
    if (!window.__w5dAsideGlobal) {
      window.__w5dAsideGlobal = true;
      document.addEventListener('click', (e) => { if (e.target.closest('[data-aside-close]')) closeAsides(); });
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAsides(); });
    }
    const stored = localStorage.getItem('w5d-lang') || 'en';
    document.querySelectorAll('[data-lang]').forEach(item => {
      if (item.dataset.lang === stored) item.setAttribute('aria-selected','true');
      if (item.dataset.langWired) return;
      item.dataset.langWired = '1';
      item.addEventListener('click', () => {
        document.querySelectorAll('[data-lang]').forEach(x => x.setAttribute('aria-selected','false'));
        item.setAttribute('aria-selected','true');
        localStorage.setItem('w5d-lang', item.dataset.lang);
        document.documentElement.setAttribute('lang', item.dataset.lang);
        if (item.dataset.lang === 'ar') document.documentElement.setAttribute('dir','rtl');
        else document.documentElement.removeAttribute('dir');
      });
    });
    document.querySelectorAll('[data-aside-act]').forEach(b => {
      if (b.dataset.actWired) return;
      b.dataset.actWired = '1';
      b.addEventListener('click', () => {
        const act = b.dataset.asideAct;
        if (act === 'signin' || act === 'signup') {
          const prefix = document.body.dataset.prefix || '';
          location.href = prefix + 'pages/contact.html';
        }
      });
    });
  }

  function initOnce() {
    buildAsides();
    applyActiveStates();
    wireAsides();
    if (window.lucide) try { lucide.createIcons(); } catch(e) {}
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => setTimeout(initOnce, 50));
  } else {
    setTimeout(initOnce, 50);
  }
  document.addEventListener('shell:rendered', () => setTimeout(initOnce, 30));
  document.addEventListener('megamenu:rendered', () => setTimeout(applyActiveStates, 30));

  window.W5DEnhance = { applyActiveStates, openAside, closeAsides, hideLoader };
})();
