/**
 * SelfAI Typewriter — character/phrase/paragraph paced reveal of HTML content.
 *
 * Config source: window.__SELFAI__.animation (loaded from api/animation.json).
 *
 *  • Each visible character revealed every `char_delay_ms`.
 *  • At the boundary of each phrase (split by `.!?؟…` + space) a tiny
 *    "loader" dot animation runs for `phrase_loader_ms`.
 *  • At each block-level boundary (p, h1-3, ul, ol, blockquote, pre) the
 *    engine pauses for `paragraph_pause_ms`.
 *  • Content inside <code>/<pre> is revealed in one shot (no per-char tick).
 *  • Respects @media (prefers-reduced-motion: reduce) → renders instantly.
 *  • Clicking the bubble fast-forwards to the end.
 *
 *  Usage:
 *      const tw = SelfAITypewriter.run(containerEl, replyHtml, { onDone });
 *      tw.skip();   // jump to end
 *      tw.cancel(); // abort
 */
(function () {
  'use strict';

  const DEFAULT_CFG = {
    enabled: true,
    respect_prefers_reduced_motion: true,
    timing: { char_delay_ms: 100, phrase_loader_ms: 600, paragraph_pause_ms: 1300, max_total_ms: 45000, max_chars_animated: 1800 },
    boundaries: {
      phrase_split_regex: "[\\.!?؟…]+\\s+",
      paragraph_split_selector: "p, h1, h2, h3, blockquote, pre, ul, ol",
      skip_inside: ["pre", "code"]
    },
    loader: { show_between_phrases: true, dots: 3, dot_interval_ms: 180 },
    cursor: { show: true, char: "▎", blink_ms: 600 },
    fast_forward_on_click: true,
    fast_forward_event: "click"
  };

  function getConfig() {
    const root = (typeof window !== 'undefined' && window.__SELFAI__ && window.__SELFAI__.animation) || {};
    const cfg = JSON.parse(JSON.stringify(DEFAULT_CFG));
    Object.assign(cfg, root);
    cfg.timing    = Object.assign({}, DEFAULT_CFG.timing, root.timing || {});
    cfg.boundaries= Object.assign({}, DEFAULT_CFG.boundaries, root.boundaries || {});
    cfg.loader    = Object.assign({}, DEFAULT_CFG.loader, root.loader || {});
    cfg.cursor    = Object.assign({}, DEFAULT_CFG.cursor, root.cursor || {});
    return cfg;
  }

  function prefersReducedMotion() {
    return typeof window !== 'undefined'
      && window.matchMedia
      && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  /** Parse HTML safely into a DOM fragment. */
  function htmlToFragment(html) {
    const tpl = document.createElement('template');
    tpl.innerHTML = html;
    return tpl.content;
  }

  /** Flatten DOM tree into a linear list of "steps":
   *   { type:'text', node:<textNode>, value:<string>, skipTyping:<bool> }
   *   { type:'block-end', tag:<string> }
   *   { type:'open', node:<element> }   // emits element + sets it ready
   */
  function buildSteps(root, cfg) {
    const skipSet = new Set((cfg.boundaries.skip_inside || []).map(t => t.toUpperCase()));
    const blockSelector = cfg.boundaries.paragraph_split_selector || 'p,h1,h2,h3,blockquote,pre,ul,ol';
    const blockTags = new Set(blockSelector.split(',').map(s => s.trim().toUpperCase()));
    const steps = [];

    function isInsideSkip(el) {
      let p = el;
      while (p && p.nodeType === 1) {
        if (skipSet.has(p.tagName)) return true;
        p = p.parentNode;
      }
      return false;
    }

    function walk(node) {
      if (node.nodeType === 3) {
        const txt = node.nodeValue || '';
        if (txt.length === 0) return;
        steps.push({
          type: 'text',
          node,
          value: txt,
          skipTyping: isInsideSkip(node.parentNode)
        });
        return;
      }
      if (node.nodeType !== 1) return;
      // For each child, recurse
      const kids = Array.from(node.childNodes);
      for (const k of kids) walk(k);
      // Block boundary after children
      if (blockTags.has(node.tagName)) {
        steps.push({ type: 'block-end', tag: node.tagName });
      }
    }

    Array.from(root.childNodes).forEach(walk);
    return steps;
  }

  /** Replace each text node's content with empty string (we'll fill it back). */
  function blankOutTextNodes(steps) {
    for (const s of steps) {
      if (s.type === 'text') {
        s.full = s.value;
        if (!s.skipTyping) s.node.nodeValue = '';
        // skipTyping nodes keep their value
      }
    }
  }

  function sleep(ms, signal) {
    return new Promise((resolve) => {
      if (ms <= 0) return resolve();
      const id = setTimeout(resolve, ms);
      if (signal) signal.addEventListener('abort', () => { clearTimeout(id); resolve(); }, { once: true });
    });
  }

  /** Run a tiny "…" loader for `ms` inside `container`, then remove it. */
  function showLoader(container, ms, cfg, signal) {
    return new Promise((resolve) => {
      if (ms <= 0) return resolve();
      const span = document.createElement('span');
      span.className = 'tw-loader';
      span.setAttribute('aria-hidden', 'true');
      const dotsCount = cfg.loader.dots || 3;
      for (let i = 0; i < dotsCount; i++) {
        const d = document.createElement('span');
        d.className = 'tw-loader__dot';
        d.style.animationDelay = (i * (cfg.loader.dot_interval_ms || 180)) + 'ms';
        d.textContent = '·';
        span.appendChild(d);
      }
      container.appendChild(span);
      const tid = setTimeout(() => {
        span.remove();
        resolve();
      }, ms);
      if (signal) signal.addEventListener('abort', () => {
        clearTimeout(tid);
        span.remove();
        resolve();
      }, { once: true });
    });
  }

  function ensureCursor(container, cfg) {
    if (!cfg.cursor || !cfg.cursor.show) return null;
    let c = container.querySelector(':scope > .tw-cursor');
    if (!c) {
      c = document.createElement('span');
      c.className = 'tw-cursor';
      c.setAttribute('aria-hidden', 'true');
      c.textContent = cfg.cursor.char || '▎';
      container.appendChild(c);
    }
    return c;
  }

  /**
   * Reveal pre-built steps inside `container`.
   * Returns a controller { skip(), cancel(), donePromise }.
   */
  function runSteps(container, steps, cfg, opts) {
    opts = opts || {};
    const ctrl = new AbortController();
    let skipped = false;
    let finished = false;
    const phraseRe = new RegExp(cfg.boundaries.phrase_split_regex || "[\\.!?؟…]+\\s+", 'u');

    const cursor = ensureCursor(container, cfg);

    function cleanupCursor() {
      if (cursor && cursor.parentNode) cursor.remove();
    }

    function fastForward() {
      skipped = true;
      ctrl.abort();
    }

    function cancel() {
      ctrl.abort();
    }

    // Click-to-skip
    let clickHandler = null;
    if (cfg.fast_forward_on_click) {
      clickHandler = () => fastForward();
      container.addEventListener(cfg.fast_forward_event || 'click', clickHandler, { once: true });
    }

    const startedAt = Date.now();
    const maxTotal = cfg.timing.max_total_ms || 0;
    const maxChars = cfg.timing.max_chars_animated || 0;
    let typedCount = 0;

    const donePromise = (async () => {
      try {
        for (let i = 0; i < steps.length; i++) {
          if (skipped) break;
          if (maxTotal > 0 && (Date.now() - startedAt) > maxTotal) { skipped = true; break; }
          const step = steps[i];

          if (step.type === 'text') {
            if (step.skipTyping) {
              step.node.nodeValue = step.full;
              continue;
            }
            const full = step.full;
            // Find phrase boundaries
            // We type char by char, and between matched boundary positions
            // we insert a loader.
            const charDelay = Math.max(0, cfg.timing.char_delay_ms || 0);
            // Pre-scan boundary positions
            const boundaries = [];
            let m;
            const re = new RegExp(cfg.boundaries.phrase_split_regex || "[\\.!?؟…]+\\s+", 'gu');
            while ((m = re.exec(full)) !== null) {
              boundaries.push(m.index + m[0].length);
            }
            let bi = 0;
            for (let j = 0; j < full.length; j++) {
              if (skipped) { step.node.nodeValue = full; break; }
              step.node.nodeValue = full.slice(0, j + 1);
              typedCount++;
              if (maxChars > 0 && typedCount > maxChars) { skipped = true; step.node.nodeValue = full; break; }
              // After a phrase boundary, show loader
              if (bi < boundaries.length && (j + 1) === boundaries[bi]) {
                bi++;
                if (cfg.loader.show_between_phrases) {
                  // Insert loader after the current text node's owning element
                  const host = step.node.parentNode || container;
                  await showLoader(host, cfg.timing.phrase_loader_ms || 0, cfg, ctrl.signal);
                  if (skipped) { step.node.nodeValue = full; break; }
                } else {
                  await sleep(cfg.timing.phrase_loader_ms || 0, ctrl.signal);
                  if (skipped) { step.node.nodeValue = full; break; }
                }
              } else if (charDelay > 0) {
                await sleep(charDelay, ctrl.signal);
              }
              if (skipped) { step.node.nodeValue = full; break; }
            }
            // Scroll while typing
            if (opts.onTick) opts.onTick();
          } else if (step.type === 'block-end') {
            if (skipped) break;
            await sleep(cfg.timing.paragraph_pause_ms || 0, ctrl.signal);
            if (opts.onTick) opts.onTick();
          }
        }
        // If skipped (or aborted), flush all text nodes to full content.
        if (skipped) {
          for (const s of steps) {
            if (s.type === 'text') s.node.nodeValue = s.full;
          }
        }
      } finally {
        finished = true;
        cleanupCursor();
        if (clickHandler) container.removeEventListener(cfg.fast_forward_event || 'click', clickHandler);
        if (opts.onDone) try { opts.onDone({ skipped }); } catch (e) {}
      }
    })();

    return { skip: fastForward, cancel, donePromise, get finished() { return finished; } };
  }

  /** Public API */
  const SelfAITypewriter = {
    /** Render `html` into `container` with animation. */
    run(container, html, opts) {
      opts = opts || {};
      const cfg = getConfig();

      // Mount HTML first
      container.replaceChildren();
      const frag = htmlToFragment(html || '');
      container.appendChild(frag);

      // Disabled or reduced-motion → instant render, just call onDone.
      const reduced = cfg.respect_prefers_reduced_motion && prefersReducedMotion();
      if (!cfg.enabled || reduced || opts.instant) {
        if (opts.onDone) try { opts.onDone({ skipped: false, instant: true }); } catch (e) {}
        return { skip: () => {}, cancel: () => {}, donePromise: Promise.resolve(), finished: true };
      }

      const steps = buildSteps(container, cfg);
      blankOutTextNodes(steps);
      return runSteps(container, steps, cfg, opts);
    },

    /** Render the HTML instantly without animation. */
    renderInstant(container, html) {
      container.replaceChildren();
      container.appendChild(htmlToFragment(html || ''));
    },

    getConfig
  };

  if (typeof window !== 'undefined') window.SelfAITypewriter = SelfAITypewriter;
})();
