/* W5D — Renders nav + footer on inner pages.
 * A page just needs: <div data-shell></div> at top and <div data-shell-foot></div> at bottom.
 * The script reads the data-prefix from the body to know if it's a root or /pages/ page.
 */
(function () {
  function navHTML(prefix) {
    return `
    <header class="fixed top-0 inset-x-0 z-50" data-brand-id-hide>
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-3">
        <nav class="glass rounded-2xl flex items-center justify-between px-4 sm:px-6 py-3 relative">
          <a href="${prefix}index.html" class="flex items-center gap-2">
            <span class="relative inline-flex w-9 h-9 rounded-xl bg-gradient-to-br from-brand-500 via-indigo-500 to-cyan-400 items-center justify-center shadow-lg shadow-brand-500/30 w5d-mark-anim">
              <span class="font-display font-bold text-white text-sm relative z-10">W5</span>
            </span>
            <span class="brand-id-text font-display font-bold text-lg tracking-tight text-slate-900 dark:text-white">W5D</span>
          </a>
          <ul data-mega-host data-prefix="${prefix}" class="hidden lg:flex items-center gap-8 text-sm font-medium"></ul>
          <div class="flex items-center gap-1.5">
            <button class="nav-icon-btn btn-ghost text-slate-700 dark:text-slate-200" data-aside-toggle="w5d-aside-lang" aria-label="Choose language" title="Language">
              <i data-lucide="languages" class="w-5 h-5"></i>
              <span class="ring-pulse"></span>
            </button>
            <button class="nav-icon-btn btn-ghost text-slate-700 dark:text-slate-200" data-aside-toggle="w5d-aside-user" aria-label="User account" title="Account">
              <i data-lucide="user-circle" class="w-5 h-5"></i>
            </button>
            <button data-theme-toggle class="nav-icon-btn btn-ghost" aria-label="Toggle theme">
              <i data-theme-icon="sun" data-lucide="sun" class="w-5 h-5"></i>
              <i data-theme-icon="moon" data-lucide="moon" class="w-5 h-5 hidden"></i>
            </button>
            <a href="${prefix}pages/contact.html" class="hidden sm:inline-flex btn-primary rounded-lg px-4 py-2 text-sm font-semibold items-center gap-1 ml-1">Get started <i data-lucide="arrow-right" class="w-4 h-4"></i></a>
            <button data-mobile-toggle class="lg:hidden btn-ghost rounded-lg w-10 h-10 flex items-center justify-center" aria-label="Menu"><i data-lucide="menu" class="w-5 h-5"></i></button>
          </div>
        </nav>
        <div id="mobileMenu" class="hidden lg:hidden glass rounded-2xl mt-2 p-4">
          <div data-mega-mobile></div>
          <a href="${prefix}pages/contact.html" class="btn-primary rounded-lg px-4 py-2 text-sm font-semibold inline-flex mt-3">Get started</a>
        </div>
      </div>
    </header>`;
  }

  function footHTML(prefix) {
    const p = prefix; // either '' (root) or '../'
    const inPages = p === '../';
    const link = (slug) => inPages ? slug : `pages/${slug}`;
    return `
    <footer class="border-t border-slate-200/60 dark:border-white/5 pt-16 pb-8 mt-10">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 grid md:grid-cols-5 gap-10">
        <div class="md:col-span-2">
          <a href="${p}index.html" class="flex items-center gap-2">
            <span class="inline-flex w-9 h-9 rounded-xl bg-gradient-to-br from-brand-500 via-indigo-500 to-cyan-400 items-center justify-center"><span class="font-display font-bold text-white text-sm">W5</span></span>
            <span class="font-display font-bold text-lg text-slate-900 dark:text-white">W5D</span>
          </a>
          <p class="mt-4 text-sm text-slate-600 dark:text-slate-400 max-w-sm">The calm AI workspace for teams that actually ship. Built with care across three time zones.</p>
          <form data-newsletter class="mt-5 flex max-w-sm">
            <input type="email" required placeholder="you@company.com" class="flex-1 bg-white dark:bg-ink-900 border border-slate-200/60 dark:border-white/10 rounded-l-lg px-3 py-2 text-sm focus:outline-none focus:border-brand-500" />
            <button type="submit" class="btn-primary rounded-r-lg px-4 text-sm font-semibold">Subscribe</button>
          </form>
        </div>
        <div><h4 class="font-semibold text-slate-900 dark:text-white text-sm">Product</h4>
          <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-400">
            <li><a href="${link('features.html')}" class="hover:text-brand-500">Features</a></li>
            <li><a href="${link('pricing.html')}" class="hover:text-brand-500">Pricing</a></li>
            <li><a href="${link('integrations.html')}" class="hover:text-brand-500">Integrations</a></li>
            <li><a href="${link('changelog.html')}" class="hover:text-brand-500">Changelog</a></li>
          </ul></div>
        <div><h4 class="font-semibold text-slate-900 dark:text-white text-sm">Company</h4>
          <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-400">
            <li><a href="${link('about.html')}" class="hover:text-brand-500">About</a></li>
            <li><a href="${link('blog.html')}" class="hover:text-brand-500">Blog</a></li>
            <li><a href="${link('careers.html')}" class="hover:text-brand-500">Careers</a></li>
            <li><a href="${link('contact.html')}" class="hover:text-brand-500">Contact</a></li>
          </ul></div>
        <div><h4 class="font-semibold text-slate-900 dark:text-white text-sm">Library</h4>
          <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-400">
            <li><a href="${link('heros.html')}" class="hover:text-brand-500">Heroes</a></li>
            <li><a href="${link('sections.html')}" class="hover:text-brand-500">Sections</a></li>
            <li><a href="${link('blocks.html')}" class="hover:text-brand-500">Blocks</a></li>
            <li><a href="${link('documentations.html')}" class="hover:text-brand-500">Documentation</a></li>
            <li><a href="${link('privacy.html')}" class="hover:text-brand-500">Privacy</a></li>
          </ul></div>
      </div>
      <div class="max-w-7xl mx-auto px-4 sm:px-6 mt-12 pt-6 border-t border-slate-200/60 dark:border-white/5 flex flex-wrap items-center justify-between gap-4">
        <p class="text-xs text-slate-500">© 2026 W5D Labs Inc. All rights reserved.</p>
        <div class="flex items-center gap-3 text-slate-500">
          <a href="#" class="hover:text-brand-500"><i data-lucide="send" class="w-4 h-4"></i></a>
          <a href="#" class="hover:text-brand-500"><i data-lucide="briefcase" class="w-4 h-4"></i></a>
          <a href="#" class="hover:text-brand-500"><i data-lucide="git-branch" class="w-4 h-4"></i></a>
          <a href="#" class="hover:text-brand-500"><i data-lucide="play-circle" class="w-4 h-4"></i></a>
        </div>
      </div>
    </footer>`;
  }

  function loadOnce(src) {
    if (document.querySelector(`script[data-auto-src="${src}"]`)) return;
    const s = document.createElement('script');
    s.src = src; s.dataset.autoSrc = src; s.defer = true;
    document.head.appendChild(s);
  }
  function loadCssOnce(href) {
    if (document.querySelector(`link[data-auto-css="${href}"]`)) return;
    const l = document.createElement('link');
    l.rel = 'stylesheet'; l.href = href; l.dataset.autoCss = href;
    document.head.appendChild(l);
  }

  function renderShell() {
    const prefix = document.body && document.body.dataset.prefix || '';
    loadCssOnce(`${prefix}css/enhancements.css`);
    loadOnce(`${prefix}js/enhancements.js`);
    const head = document.querySelector('[data-shell]');
    const foot = document.querySelector('[data-shell-foot]');
    if (head && !head.dataset.shellRendered) {
      head.innerHTML = navHTML(prefix);
      head.dataset.shellRendered = '1';
    }
    if (foot && !foot.dataset.shellRendered) {
      foot.innerHTML = footHTML(prefix);
      foot.dataset.shellRendered = '1';
    }
    if (window.lucide) try { lucide.createIcons(); } catch(e){}
    document.dispatchEvent(new CustomEvent('shell:rendered'));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderShell);
  } else {
    renderShell();
  }
})();
