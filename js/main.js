/* W5D — Shared interactivity */

(function () {
  // ---------- Theme toggle ----------
  const root = document.documentElement;
  const stored = localStorage.getItem('w5d-theme');
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  const initial = stored || (prefersDark ? 'dark' : 'dark'); // default to dark
  if (initial === 'dark') root.classList.add('dark'); else root.classList.remove('dark');

  function setTheme(mode) {
    if (mode === 'dark') root.classList.add('dark');
    else root.classList.remove('dark');
    localStorage.setItem('w5d-theme', mode);
    document.querySelectorAll('[data-theme-icon]').forEach(el => {
      el.dataset.themeIcon === 'sun'
        ? el.classList.toggle('hidden', mode !== 'dark')
        : el.classList.toggle('hidden', mode === 'dark');
    });
  }

  // initialize icons
  document.addEventListener('DOMContentLoaded', () => {
    setTheme(root.classList.contains('dark') ? 'dark' : 'light');
    document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
      btn.addEventListener('click', () => {
        setTheme(root.classList.contains('dark') ? 'light' : 'dark');
      });
    });

    // ---------- Mobile nav ----------
    document.querySelectorAll('[data-mobile-toggle]').forEach(btn => {
      btn.addEventListener('click', () => {
        const target = document.getElementById('mobileMenu');
        if (target) target.classList.toggle('hidden');
      });
    });
    // Close mobile menu when a link inside it is clicked
    document.addEventListener('click', (e) => {
      const a = e.target.closest('#mobileMenu a');
      if (a) document.getElementById('mobileMenu')?.classList.add('hidden');
    });

    // ---------- FAQ accordion ----------
    document.querySelectorAll('[data-faq]').forEach(item => {
      const btn = item.querySelector('[data-faq-btn]');
      const panel = item.querySelector('[data-faq-panel]');
      const icon = item.querySelector('[data-faq-icon]');
      if (!btn || !panel) return;
      btn.addEventListener('click', () => {
        const open = !panel.classList.contains('hidden');
        // close all
        document.querySelectorAll('[data-faq-panel]').forEach(p => p.classList.add('hidden'));
        document.querySelectorAll('[data-faq-icon]').forEach(i => i.classList.remove('rotate-45'));
        if (!open) {
          panel.classList.remove('hidden');
          icon && icon.classList.add('rotate-45');
        }
      });
    });

    // ---------- Pricing toggle ----------
    const pricingToggle = document.getElementById('pricingToggle');
    if (pricingToggle) {
      pricingToggle.addEventListener('change', e => {
        const yearly = e.target.checked;
        document.querySelectorAll('[data-price-monthly]').forEach(el => el.classList.toggle('hidden', yearly));
        document.querySelectorAll('[data-price-yearly]').forEach(el => el.classList.toggle('hidden', !yearly));
      });
    }

    // ---------- Reveal on scroll ----------
    // Legacy [data-reveal] (older pages) + new [data-rv] (heros/sections/docs)
    if (!window.AOS) {
      const io = new IntersectionObserver(entries => {
        entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('is-visible'); });
      }, { threshold: 0.12 });
      document.querySelectorAll('[data-reveal]').forEach(el => io.observe(el));
    } else {
      AOS.init({ duration: 700, once: true, offset: 60 });
    }
    const rvIO = new IntersectionObserver(entries => {
      entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('rv-in'); rvIO.unobserve(e.target); } });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    document.querySelectorAll('[data-rv]').forEach(el => rvIO.observe(el));

    // ---------- Spotlight cursor follow ----------
    document.querySelectorAll('.spotlight').forEach(el => {
      el.addEventListener('pointermove', (e) => {
        const r = el.getBoundingClientRect();
        el.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100) + '%');
        el.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100) + '%');
      });
    });

    // ---------- Tabs (data-tabs) ----------
    document.querySelectorAll('[data-tabs]').forEach(group => {
      const tabs   = group.querySelectorAll('[data-tab]');
      const panels = group.querySelectorAll('[data-tab-panel]');
      tabs.forEach(t => t.addEventListener('click', () => {
        const k = t.dataset.tab;
        tabs.forEach(x => x.classList.toggle('is-active', x === t));
        panels.forEach(p => p.classList.toggle('hidden', p.dataset.tabPanel !== k));
      }));
    });

    // ---------- Copy-to-clipboard ----------
    document.querySelectorAll('[data-copy]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const sel = btn.dataset.copy;
        const src = sel ? document.querySelector(sel) : btn.previousElementSibling;
        const text = src ? (src.innerText || src.textContent) : '';
        try { await navigator.clipboard.writeText(text); }
        catch { /* noop */ }
        const original = btn.dataset._orig || btn.innerHTML;
        btn.dataset._orig = original;
        btn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> Copied';
        if (window.lucide) lucide.createIcons();
        setTimeout(() => { btn.innerHTML = original; if (window.lucide) lucide.createIcons(); }, 1400);
      });
    });

    // ---------- Counter animation ----------
    const counters = document.querySelectorAll('[data-counter]');
    if (counters.length) {
      const cio = new IntersectionObserver(entries => {
        entries.forEach(en => {
          if (!en.isIntersecting) return;
          const el = en.target;
          const target = parseFloat(el.dataset.counter);
          const decimals = parseInt(el.dataset.decimals || '0', 10);
          const suffix = el.dataset.suffix || '';
          let cur = 0;
          const step = target / 60;
          const t = setInterval(() => {
            cur += step;
            if (cur >= target) { cur = target; clearInterval(t); }
            el.textContent = cur.toFixed(decimals) + suffix;
          }, 16);
          cio.unobserve(el);
        });
      }, { threshold: 0.4 });
      counters.forEach(c => cio.observe(c));
    }

    // ---------- Lucide icons ----------
    if (window.lucide) lucide.createIcons();
  });
})();
