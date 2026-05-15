/* W5D — Mega-menu rendered from JS so every page stays in sync */
(function () {
  const NAV = {
    product: {
      title: 'Product',
      groups: [
        { heading: 'Platform', items: [
          { i: 'sparkles',     t: 'Features overview', d: 'Everything W5D does, in one map.', href: 'pages/features.html' },
          { i: 'bot',          t: 'AI Agents',         d: 'Multi-step reasoning with tools.',   href: 'pages/features.html#agents' },
          { i: 'database',     t: 'Knowledge',         d: 'Your private, cited source layer.',  href: 'pages/features.html#knowledge' },
          { i: 'git-branch',   t: 'Workflows',         d: 'Visual chains of agents + actions.', href: 'pages/features.html#workflows' }
        ]},
        { heading: 'Connect', items: [
          { i: 'puzzle',       t: 'Integrations',      d: '120+ tools, native + bidirectional.', href: 'pages/integrations.html' },
          { i: 'shield-check', t: 'Security',          d: 'SOC 2, SSO, audit logs by default.',  href: 'pages/security.html' },
          { i: 'terminal',     t: 'Developer API',     d: 'REST + webhooks + 6 SDKs.',           href: 'pages/features.html#api' }
        ]}
      ],
      promo: {
        tag: 'New',
        title: 'W5D Agents 2.0',
        body: 'Multi-step reasoning, parallel tool use, and a 60% cost cut on long runs.',
        href: 'pages/blog-post.html'
      }
    },
    solutions: {
      title: 'Solutions',
      groups: [
        { heading: 'By team', items: [
          { i: 'megaphone',  t: 'Marketing',   d: 'Brief in, campaign out.',           href: 'pages/features.html#marketing' },
          { i: 'code-2',     t: 'Engineering', d: 'PR review, docs, on-call.',          href: 'pages/features.html#engineering' },
          { i: 'briefcase',  t: 'Operations',  d: 'Recaps, reminders, follow-ups.',     href: 'pages/features.html#operations' },
          { i: 'headphones', t: 'Support',     d: 'Grounded tier-1 replies.',           href: 'pages/features.html#support' }
        ]},
        { heading: 'By size', items: [
          { i: 'rocket',     t: 'Startups',     d: 'Move fast without breaking finance.', href: 'pages/pricing.html' },
          { i: 'building-2', t: 'Mid-market',   d: 'Scale workflows across departments.', href: 'pages/pricing.html' },
          { i: 'landmark',   t: 'Enterprise',   d: 'SSO, VPC, SLAs, solutions team.',     href: 'pages/contact.html' }
        ]}
      ],
      promo: {
        tag: 'Story',
        title: 'How Northwind tripled output',
        body: 'A 6-person growth team that out-shipped a 40-person agency.',
        href: 'pages/blog-post.html'
      }
    },
    resources: {
      title: 'Resources',
      groups: [
        { heading: 'Learn', items: [
          { i: 'book-open',   t: 'Blog',       d: 'Field notes from the frontier.',  href: 'pages/blog.html' },
          { i: 'history',     t: 'Changelog',  d: 'What we shipped this week.',      href: 'pages/changelog.html' },
          { i: 'life-buoy',   t: 'Help center',d: 'Guides + how-tos.',               href: 'pages/blog.html' }
        ]},
        { heading: 'Company', items: [
          { i: 'compass',     t: 'About',      d: 'Why we\'re building W5D.',        href: 'pages/about.html' },
          { i: 'briefcase',   t: 'Careers',    d: 'We\'re hiring across 4 teams.',   href: 'pages/careers.html' },
          { i: 'shield',      t: 'Security',   d: 'SOC 2, GDPR, sub-processors.',    href: 'pages/security.html' },
          { i: 'mail',        t: 'Contact',    d: 'Sales, support, press, etc.',     href: 'pages/contact.html' }
        ]}
      ],
      promo: {
        tag: 'Live',
        title: 'Spring product webinar',
        body: 'A walkthrough of Agents 2.0 + Q&A. Thursday, 10am PT.',
        href: 'pages/contact.html'
      }
    },
    pages: {
      title: 'Pages',
      groups: [
        { heading: 'Homes', items: [
          { i: 'home',       t: 'Home 01 — Classic',   d: 'Centered hero, classic flow.',     href: 'index.html' },
          { i: 'layout',     t: 'Home 02 — Product',   d: 'Split hero, bento features.',      href: 'home-2.html' },
          { i: 'newspaper',  t: 'Home 03 — Editorial', d: 'Bold typography, manifesto.',      href: 'home-3.html' }
        ]},
        { heading: 'Inner', items: [
          { i: 'compass',     t: 'About',      d: 'Story, values, team.',           href: 'pages/about.html' },
          { i: 'tag',         t: 'Pricing',    d: 'Plans + add-ons.',               href: 'pages/pricing.html' },
          { i: 'mail',        t: 'Contact',    d: 'Get in touch.',                  href: 'pages/contact.html' },
          { i: 'frown',       t: '404',        d: 'Friendly not-found.',            href: 'pages/404.html' }
        ]},
        { heading: 'Library', items: [
          { i: 'sparkles',    t: 'Heroes',         d: '12 animated hero variants.',  href: 'pages/heros.html' },
          { i: 'layout-grid', t: 'Sections',       d: '28 ready-to-copy sections.',  href: 'pages/sections.html' },
          { i: 'layout',      t: 'Blocks',         d: 'Every section in one page.',  href: 'pages/blocks.html' },
          { i: 'book-open',   t: 'Documentation',  d: 'How to reuse the kit.',       href: 'pages/documentations.html' }
        ]}
      ],
      promo: {
        tag: 'New',
        title: 'Heroes + Sections library',
        body: '40+ animated, copy-paste blocks to launch any new site in hours.',
        href: 'pages/heros.html'
      }
    }
  };

  function buildItem(it, prefix) {
    return `
      <a href="${prefix}${it.href}" class="group flex items-start gap-3 p-3 rounded-xl hover:bg-brand-500/5 transition">
        <span class="mt-0.5 w-9 h-9 rounded-lg bg-brand-500/10 text-brand-500 flex items-center justify-center shrink-0 group-hover:bg-brand-500 group-hover:text-white transition">
          <i data-lucide="${it.i}" class="w-4 h-4"></i>
        </span>
        <span>
          <span class="block text-sm font-semibold text-slate-900 dark:text-white">${it.t}</span>
          <span class="block text-xs text-slate-500 mt-0.5">${it.d}</span>
        </span>
      </a>`;
  }

  function buildPanel(key, def, prefix) {
    const cols = def.groups.map(g => `
      <div>
        <p class="text-[11px] uppercase tracking-widest text-slate-500 font-semibold mb-2 px-3">${g.heading}</p>
        <div class="space-y-1">${g.items.map(it => buildItem(it, prefix)).join('')}</div>
      </div>`).join('');

    const promo = def.promo ? `
      <a href="${prefix}${def.promo.href}" class="rounded-2xl p-5 bg-gradient-to-br from-brand-600 via-indigo-600 to-cyan-500 text-white block hover:brightness-110 transition relative overflow-hidden">
        <div class="absolute inset-0 grid-bg opacity-30"></div>
        <div class="relative">
          <span class="inline-block text-[11px] uppercase tracking-widest bg-white/20 rounded-full px-2 py-0.5">${def.promo.tag}</span>
          <h4 class="font-display font-bold text-lg mt-3">${def.promo.title}</h4>
          <p class="text-sm text-white/85 mt-1.5">${def.promo.body}</p>
          <span class="text-sm font-semibold mt-3 inline-flex items-center gap-1">Read more <i data-lucide="arrow-right" class="w-4 h-4"></i></span>
        </div>
      </a>` : '';

    // pt-3 inside (instead of mt-3 outside) creates a transparent "bridge"
    // between the trigger and the visible card so the cursor never leaves
    // the hover area. data-mega-panel is the whole hoverable region.
    return `
      <div data-mega-panel="${key}" class="hidden absolute left-1/2 -translate-x-1/2 top-full pt-3 w-[min(960px,calc(100vw-2rem))] max-w-[calc(100vw-2rem)] z-50">
        <div class="glass rounded-2xl p-5 shadow-2xl shadow-brand-500/10 border border-slate-200/60 dark:border-white/10 mega-panel-enter">
          <div class="grid lg:grid-cols-[1.4fr_1fr] gap-5">
            <div class="grid sm:grid-cols-2 gap-4">${cols}</div>
            ${promo}
          </div>
        </div>
      </div>`;
  }

  function buildTriggers(prefix) {
    return Object.entries(NAV).map(([key, def]) => `
      <li class="static" data-mega-trigger="${key}">
        <button class="link-underline flex items-center gap-1 py-2">
          ${def.title} <i data-lucide="chevron-down" class="w-4 h-4 transition-transform"></i>
        </button>
        ${buildPanel(key, def, prefix)}
      </li>`).join('');
  }

  function buildMobile(prefix) {
    return Object.entries(NAV).map(([key, def]) => `
      <details class="border-b border-slate-200/60 dark:border-white/5 py-2">
        <summary class="font-semibold py-2 cursor-pointer flex items-center justify-between">
          ${def.title} <i data-lucide="chevron-down" class="w-4 h-4"></i>
        </summary>
        <div class="pl-2 pb-2 space-y-1">
          ${def.groups.flatMap(g => g.items).map(it =>
            `<a href="${prefix}${it.href}" class="block py-1.5 text-sm text-slate-600 dark:text-slate-400">${it.t}</a>`
          ).join('')}
        </div>
      </details>`).join('');
  }

  function buildMegaMenu() {
    const host = document.querySelector('[data-mega-host]');
    const mhost = document.querySelector('[data-mega-mobile]');
    if (!host) return false;
    if (host.dataset.megaBuilt === '1') return true;
    const prefix = host.dataset.prefix || ''; // '' for root pages, '../' for /pages/
    host.innerHTML = buildTriggers(prefix);
    if (mhost) mhost.innerHTML = buildMobile(prefix);
    host.dataset.megaBuilt = '1';

    // Hover/focus open with a small close-delay so the cursor can travel
    // from trigger → panel without the menu disappearing.
    const CLOSE_DELAY = 120; // ms
    let closeTimer = null;
    let activeLi = null;

    function closeAll() {
      host.querySelectorAll('[data-mega-panel]').forEach(p => p.classList.add('hidden'));
      host.querySelectorAll('.lucide-chevron-down').forEach(c => c.classList.remove('rotate-180'));
      host.querySelectorAll('[data-mega-trigger]').forEach(li => li.removeAttribute('data-open'));
      activeLi = null;
    }

    host.querySelectorAll('[data-mega-trigger]').forEach(li => {
      const panel  = li.querySelector('[data-mega-panel]');
      const chev   = li.querySelector('.lucide-chevron-down');
      const button = li.querySelector('button');

      const open = () => {
        clearTimeout(closeTimer);
        if (activeLi && activeLi !== li) {
          activeLi.querySelector('[data-mega-panel]')?.classList.add('hidden');
          activeLi.querySelector('.lucide-chevron-down')?.classList.remove('rotate-180');
          activeLi.removeAttribute('data-open');
        }
        panel.classList.remove('hidden');
        chev?.classList.add('rotate-180');
        li.setAttribute('data-open', '');
        button?.setAttribute('aria-expanded', 'true');
        activeLi = li;
      };
      const scheduleClose = () => {
        clearTimeout(closeTimer);
        closeTimer = setTimeout(() => {
          panel.classList.add('hidden');
          chev?.classList.remove('rotate-180');
          li.removeAttribute('data-open');
          button?.setAttribute('aria-expanded', 'false');
          if (activeLi === li) activeLi = null;
        }, CLOSE_DELAY);
      };

      // Hover the trigger OR the panel keeps the menu open
      li.addEventListener('mouseenter', open);
      li.addEventListener('mouseleave', scheduleClose);
      panel.addEventListener('mouseenter', () => clearTimeout(closeTimer));
      panel.addEventListener('mouseleave', scheduleClose);

      // Keyboard / click toggle
      button?.setAttribute('aria-haspopup', 'true');
      button?.setAttribute('aria-expanded', 'false');
      button.addEventListener('click', e => {
        e.preventDefault();
        panel.classList.contains('hidden') ? open() : scheduleClose();
      });
      li.addEventListener('focusin', open);
      li.addEventListener('focusout', (e) => {
        if (!li.contains(e.relatedTarget)) scheduleClose();
      });
    });

    // Click outside / Escape closes
    document.addEventListener('click', (e) => { if (!host.contains(e.target)) closeAll(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAll(); });

    if (window.lucide) try { lucide.createIcons(); } catch(e){}
    document.dispatchEvent(new CustomEvent('megamenu:rendered'));
    return true;
  }

  function tryBuild(retry) {
    if (buildMegaMenu()) return;
    if (retry > 0) setTimeout(() => tryBuild(retry - 1), 80);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => tryBuild(20));
  } else {
    tryBuild(20);
  }
  document.addEventListener('shell:rendered', () => tryBuild(5));

  window.W5DMega = { build: buildMegaMenu };
})();
