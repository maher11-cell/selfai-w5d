/* W5D — Inline SVG partner wordmarks (original art, not copies of any real brand)
 *
 * BOOT LOADER: This file is loaded earliest on every page. We use it as the
 * boot vehicle for the page loader (so it shows BEFORE page paint).
 */
(function bootLoader() {
  if (window.__w5dBoot) return;
  window.__w5dBoot = true;
  var css = "#w5d-loader{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:radial-gradient(ellipse at center,#0f172a 0%,#020617 100%);transition:opacity .55s ease,visibility .55s ease;font-family:'Space Grotesk',Inter,system-ui,sans-serif}#w5d-loader.is-hidden{opacity:0;visibility:hidden;pointer-events:none}#w5d-loader .loader-inner{display:flex;flex-direction:column;align-items:center;gap:1.25rem}#w5d-loader .loader-mark{width:96px;height:96px;border-radius:1.25rem;background:linear-gradient(135deg,#7c3aed,#6366f1,#06b6d4);display:flex;align-items:center;justify-content:center;box-shadow:0 12px 40px -8px rgba(124,58,237,.55);animation:w5dPop 2s ease-in-out infinite;position:relative;overflow:hidden}#w5d-loader .loader-mark::before{content:\"\";position:absolute;inset:0;background:linear-gradient(115deg,transparent 30%,rgba(255,255,255,.35) 50%,transparent 70%);animation:w5dShine 2.4s linear infinite}#w5d-loader .loader-mark span{font-weight:800;color:#fff;font-size:1.6rem;letter-spacing:-.02em;position:relative;z-index:1}#w5d-loader .loader-text{font-weight:700;font-size:1.05rem;background:linear-gradient(90deg,#7c3aed,#06b6d4,#7c3aed);-webkit-background-clip:text;background-clip:text;color:transparent;background-size:200% 100%;animation:w5dSlide 2.2s linear infinite}#w5d-loader .loader-bar{width:200px;height:3px;border-radius:99px;background:rgba(255,255,255,.08);overflow:hidden;position:relative}#w5d-loader .loader-bar::after{content:\"\";position:absolute;inset:0;width:40%;background:linear-gradient(90deg,transparent,#7c3aed,#06b6d4,transparent);animation:w5dBar 1.4s ease-in-out infinite}@keyframes w5dPop{0%,100%{transform:scale(1)}50%{transform:scale(1.06)}}@keyframes w5dShine{0%{transform:translateX(-100%)}100%{transform:translateX(100%)}}@keyframes w5dSlide{0%{background-position:0 0}100%{background-position:200% 0}}@keyframes w5dBar{0%{transform:translateX(-100%)}100%{transform:translateX(350%)}}@media(prefers-reduced-motion:reduce){#w5d-loader *{animation:none!important}}";
  var s = document.createElement('style'); s.id='w5d-loader-css'; s.textContent = css;
  (document.head || document.documentElement).appendChild(s);

  function build() {
    if (document.getElementById('w5d-loader')) return;
    if (!document.body) return setTimeout(build, 10);
    var l = document.createElement('div');
    l.id = 'w5d-loader'; l.setAttribute('aria-hidden','true');
    l.innerHTML = '<div class="loader-inner">' +
      '<div class="loader-mark"><span>W5</span></div>' +
      '<div class="loader-text">Loading W5D…</div>' +
      '<div class="loader-bar" role="progressbar" aria-label="Loading"></div></div>';
    document.body.appendChild(l);
  }
  if (document.body) build(); else document.addEventListener('DOMContentLoaded', build, { once:true });

  function hide() {
    var l = document.getElementById('w5d-loader'); if (!l) return;
    setTimeout(function(){ l.classList.add('is-hidden'); }, 200);
    setTimeout(function(){ l.parentNode && l.parentNode.removeChild(l); }, 1200);
  }
  if (document.readyState === 'complete') setTimeout(hide, 700);
  else window.addEventListener('load', function(){ setTimeout(hide, 400); });
  setTimeout(hide, 6000);
})();

(function () {
  const css = 'h-7 sm:h-8 w-auto opacity-70 hover:opacity-100 transition text-slate-600 dark:text-slate-300';
  const LOGOS = [
    // Northwind — windmill mark + wordmark
    `<svg viewBox="0 0 200 32" fill="currentColor" class="${css}" aria-label="Northwind"><g><circle cx="14" cy="16" r="3"/><path d="M14 16 L14 4 L17 7 Z M14 16 L26 16 L23 19 Z M14 16 L14 28 L11 25 Z M14 16 L2 16 L5 13 Z" opacity=".7"/></g><text x="34" y="22" font-family="Space Grotesk, sans-serif" font-weight="700" font-size="18">Northwind</text></svg>`,

    // Lumen — sun ring
    `<svg viewBox="0 0 180 32" fill="currentColor" class="${css}" aria-label="Lumen"><circle cx="14" cy="16" r="6" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="14" cy="16" r="2"/><text x="28" y="22" font-family="Space Grotesk, sans-serif" font-weight="700" font-size="18">Lumen</text></svg>`,

    // Vertex — triangle slash
    `<svg viewBox="0 0 200 32" fill="currentColor" class="${css}" aria-label="Vertex AI"><path d="M4 26 L14 6 L24 26 Z" opacity=".85"/><text x="32" y="22" font-family="Space Grotesk, sans-serif" font-weight="700" font-size="18">Vertex</text><text x="108" y="22" font-family="Inter, sans-serif" font-weight="500" font-size="16" opacity=".6">/AI</text></svg>`,

    // Atlasly — stacked diamonds
    `<svg viewBox="0 0 180 32" fill="currentColor" class="${css}" aria-label="Atlasly"><path d="M14 4 L22 16 L14 28 L6 16 Z"/><text x="30" y="22" font-family="Space Grotesk, sans-serif" font-weight="700" font-size="18">Atlasly</text></svg>`,

    // Quanta — dot pattern
    `<svg viewBox="0 0 200 32" fill="currentColor" class="${css}" aria-label="Quanta·co"><circle cx="6" cy="10" r="2"/><circle cx="14" cy="10" r="2"/><circle cx="22" cy="10" r="2"/><circle cx="6" cy="18" r="2"/><circle cx="14" cy="18" r="2"/><circle cx="22" cy="18" r="2"/><circle cx="6" cy="26" r="2"/><circle cx="14" cy="26" r="2"/><circle cx="22" cy="26" r="2"/><text x="34" y="24" font-family="Space Grotesk, sans-serif" font-weight="700" font-size="18">Quanta·co</text></svg>`,

    // Helix — hex
    `<svg viewBox="0 0 180 32" fill="currentColor" class="${css}" aria-label="Helix"><path d="M14 4 L24 10 L24 22 L14 28 L4 22 L4 10 Z" fill="none" stroke="currentColor" stroke-width="2"/><text x="32" y="22" font-family="Space Grotesk, sans-serif" font-weight="700" font-size="18">Helix</text></svg>`,

    // Pulsewave — wave
    `<svg viewBox="0 0 200 32" fill="none" stroke="currentColor" stroke-width="2" class="${css}" aria-label="Pulsewave"><path d="M2 16 Q 8 4, 14 16 T 26 16" /><text x="34" y="22" font-family="Space Grotesk, sans-serif" font-weight="700" font-size="18" fill="currentColor" stroke="none">Pulsewave</text></svg>`,

    // Orbital — ring + dot
    `<svg viewBox="0 0 180 32" fill="currentColor" class="${css}" aria-label="Orbital"><ellipse cx="14" cy="16" rx="10" ry="4" fill="none" stroke="currentColor" stroke-width="1.5" transform="rotate(-20 14 16)"/><circle cx="14" cy="16" r="3"/><text x="30" y="22" font-family="Space Grotesk, sans-serif" font-weight="700" font-size="18">Orbital</text><text x="98" y="14" font-family="Inter, sans-serif" font-size="9" opacity=".6">®</text></svg>`,

    // Tessera — square stack
    `<svg viewBox="0 0 180 32" fill="currentColor" class="${css}" aria-label="Tessera"><rect x="3" y="3" width="11" height="11" opacity=".7"/><rect x="3" y="17" width="11" height="11" opacity=".4"/><rect x="17" y="10" width="11" height="11"/><text x="34" y="22" font-family="Space Grotesk, sans-serif" font-weight="700" font-size="18">Tessera</text></svg>`,

    // Brightline — line + line
    `<svg viewBox="0 0 200 32" fill="currentColor" class="${css}" aria-label="Brightline"><rect x="4" y="14" width="22" height="3" rx="1.5"/><rect x="4" y="20" width="14" height="3" rx="1.5" opacity=".6"/><text x="32" y="22" font-family="Space Grotesk, sans-serif" font-weight="700" font-size="18">Brightline</text></svg>`,

    // Cinder — flame
    `<svg viewBox="0 0 180 32" fill="currentColor" class="${css}" aria-label="Cinder"><path d="M14 4 C 18 10, 22 12, 22 18 C 22 24, 18 28, 14 28 C 10 28, 6 24, 6 18 C 6 14, 10 12, 14 4 Z"/><text x="30" y="22" font-family="Space Grotesk, sans-serif" font-weight="700" font-size="18">Cinder</text></svg>`,

    // Mosaic — 4-grid
    `<svg viewBox="0 0 180 32" fill="currentColor" class="${css}" aria-label="Mosaic"><rect x="4" y="6" width="9" height="9"/><rect x="15" y="6" width="9" height="9" opacity=".6"/><rect x="4" y="17" width="9" height="9" opacity=".6"/><rect x="15" y="17" width="9" height="9"/><text x="30" y="22" font-family="Space Grotesk, sans-serif" font-weight="700" font-size="18">Mosaic</text></svg>`
  ];

  function render() {
    document.querySelectorAll('[data-logo-marquee]').forEach(track => {
      const html = LOGOS.join('');
      track.innerHTML = html + html; // duplicate for seamless loop
    });
    document.querySelectorAll('[data-logo-grid]').forEach(grid => {
      grid.innerHTML = LOGOS.map(l => `<div class="flex items-center justify-center py-3">${l}</div>`).join('');
    });
  }
  document.addEventListener('DOMContentLoaded', render);
})();
