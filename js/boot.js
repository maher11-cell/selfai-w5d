/* W5D — Boot loader (place EARLY in <head>) */
(function () {
  var css = `
  #w5d-loader{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;
    background:radial-gradient(ellipse at center,#0f172a 0%,#020617 100%);
    transition:opacity .55s ease,visibility .55s ease;font-family:'Space Grotesk',Inter,system-ui,sans-serif}
  #w5d-loader.is-hidden{opacity:0;visibility:hidden;pointer-events:none}
  #w5d-loader .loader-inner{display:flex;flex-direction:column;align-items:center;gap:1.25rem}
  #w5d-loader .loader-mark{width:96px;height:96px;border-radius:1.25rem;
    background:linear-gradient(135deg,#7c3aed,#6366f1,#06b6d4);display:flex;align-items:center;justify-content:center;
    box-shadow:0 12px 40px -8px rgba(124,58,237,.55);animation:w5dPop 2s ease-in-out infinite;position:relative;overflow:hidden}
  #w5d-loader .loader-mark::before{content:"";position:absolute;inset:0;
    background:linear-gradient(115deg,transparent 30%,rgba(255,255,255,.35) 50%,transparent 70%);
    animation:w5dShine 2.4s linear infinite}
  #w5d-loader .loader-mark span{font-weight:800;color:#fff;font-size:1.6rem;letter-spacing:-.02em;position:relative;z-index:1}
  #w5d-loader .loader-text{font-weight:700;font-size:1.05rem;
    background:linear-gradient(90deg,#7c3aed,#06b6d4,#7c3aed);-webkit-background-clip:text;background-clip:text;color:transparent;
    background-size:200% 100%;animation:w5dSlide 2.2s linear infinite}
  #w5d-loader .loader-bar{width:200px;height:3px;border-radius:99px;background:rgba(255,255,255,.08);overflow:hidden;position:relative}
  #w5d-loader .loader-bar::after{content:"";position:absolute;inset:0;width:40%;
    background:linear-gradient(90deg,transparent,#7c3aed,#06b6d4,transparent);animation:w5dBar 1.4s ease-in-out infinite}
  @keyframes w5dPop{0%,100%{transform:scale(1)}50%{transform:scale(1.06)}}
  @keyframes w5dShine{0%{transform:translateX(-100%)}100%{transform:translateX(100%)}}
  @keyframes w5dSlide{0%{background-position:0 0}100%{background-position:200% 0}}
  @keyframes w5dBar{0%{transform:translateX(-100%)}100%{transform:translateX(350%)}}
  @media(prefers-reduced-motion:reduce){
    #w5d-loader .loader-mark,#w5d-loader .loader-mark::before,
    #w5d-loader .loader-text,#w5d-loader .loader-bar::after{animation:none!important}
  }`;
  var style = document.createElement('style');
  style.id = 'w5d-loader-css';
  style.textContent = css;
  (document.head || document.documentElement).appendChild(style);

  function build() {
    if (document.getElementById('w5d-loader')) return;
    if (!document.body) return setTimeout(build, 10);
    var loader = document.createElement('div');
    loader.id = 'w5d-loader';
    loader.setAttribute('aria-hidden', 'true');
    loader.innerHTML =
      '<div class="loader-inner">' +
        '<div class="loader-mark"><span>W5</span></div>' +
        '<div class="loader-text">Loading W5D…</div>' +
        '<div class="loader-bar" role="progressbar" aria-label="Loading"></div>' +
      '</div>';
    document.body.appendChild(loader);
  }
  if (document.body) build();
  else document.addEventListener('DOMContentLoaded', build, { once: true });

  function hide() {
    var l = document.getElementById('w5d-loader');
    if (!l) return;
    setTimeout(function () { l.classList.add('is-hidden'); }, 200);
    setTimeout(function () { l.parentNode && l.parentNode.removeChild(l); }, 1200);
  }
  if (document.readyState === 'complete') setTimeout(hide, 700);
  else window.addEventListener('load', function () { setTimeout(hide, 400); });
  setTimeout(hide, 6000);
})();
