/* SelfAI — dashboard + chat + auth UI logic
 * Strategy: render from IndexedDB cache instantly (skeleton until then),
 * then refresh from the server in the background and re-render.
 */
(function () {
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, m => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[m]));
  }

  async function api(path, body) {
    const opts = { credentials: 'same-origin' };
    if (body !== undefined) {
      opts.method = 'POST';
      opts.headers = { 'Content-Type': 'application/json' };
      opts.body = JSON.stringify(body);
    }
    const res = await fetch(path, opts);
    let json = null; try { json = await res.json(); } catch (_) {}
    if (!res.ok) throw new Error((json && (json.error || json.detail)) || ('HTTP ' + res.status));
    return json;
  }

  // ===== Auth pages =====
  const authForm = $('#authForm');
  if (authForm) {
    authForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const action = authForm.dataset.action; // signup | login
      const fd = new FormData(authForm);
      const body = {
        email: (fd.get('email') || '').toString().trim(),
        password: (fd.get('password') || '').toString(),
        display_name: (fd.get('display_name') || '').toString().trim() || null,
      };
      const status = $('#authStatus');
      const t = (k) => (window.SelfAII18n ? window.SelfAII18n.t(k) : k);
      status.classList.remove('hidden');
      status.className = 'text-sm text-slate-400';
      status.textContent = t(action === 'signup' ? 'auth.creating' : 'auth.signing_in');
      try {
        await api('api/auth.php?action=' + action, body);
        status.className = 'text-sm text-emerald-400';
        status.textContent = t('auth.redirecting');
        location.href = 'index.php?p=dashboard';
      } catch (err) {
        status.className = 'text-sm text-rose-400';
        status.textContent = (err && err.message) || t('errors.unknown');
      }
    });
  }

  // ===== Dashboard =====
  const cloneGrid = $('#cloneGrid');
  const ledgerGrid = $('#ledgerGrid');
  const saxScore = $('#saxScore');
  const statsList = $('#statsList');
  const recentList = $('#recentList');

  function renderClones(clones) {
    if (!cloneGrid || !clones) return;
    cloneGrid.innerHTML = clones.map(c => `
      <a class="clone-card" href="index.php?p=chat&clone=${encodeURIComponent(c.id)}">
        <div class="clone-head">
          <span class="clone-icon">${esc(c.icon)}</span>
          <div>
            <div class="clone-name">${esc(c.name)}</div>
            <div class="clone-meta">${esc(c.code)} · ${esc(c.english_name)}</div>
          </div>
        </div>
        <div class="clone-desc">${esc(c.description)}</div>
        <div class="clone-foot">
          <span style="color:${esc(c.color)}; border:1px solid ${esc(c.color)}55; background:${esc(c.color)}22; padding:.15rem .55rem; border-radius:999px;">
            ${esc(c.currency)}
          </span>
          <span class="text-slate-500" dir="rtl">${esc(c.ayah)}</span>
        </div>
      </a>
    `).join('');
  }

  function renderLedger(ledger, clones) {
    if (!ledgerGrid || !ledger) return;
    const ord = ['CTC','TIC','VTC','INC','SCC','WPC','WDC','JEC','FLC','GRC','SAX'];
    const colorByCur = {};
    (clones || []).forEach(c => colorByCur[c.currency] = c.color);
    ledgerGrid.innerHTML = ord.map(cur => `
      <div class="cur-tile">
        <span class="cur-code" style="color:${colorByCur[cur] || '#94a3b8'}">${cur}</span>
        <span class="cur-val">${ledger[cur] ?? 0}</span>
      </div>
    `).join('');
  }

  function renderStats(m) {
    if (!statsList) return;
    const t = (k) => (window.SelfAII18n ? window.SelfAII18n.t(k) : k);
    const lines = [
      [t('dashboard.total_messages'),    m.total_conversations ?? 0],
      [t('dashboard.distinct_personas'), (m.distinct_clones ?? 0) + ' / 11'],
      [t('dashboard.attributes_saved'),  m.attributes ?? 0],
      [t('dashboard.resumes_uploaded'),  m.resumes ?? 0],
      [t('dashboard.feedback_avg'),      (m.feedback_avg ?? 0).toFixed(2)],
      [t('dashboard.top_persona'),       m.top_clone ? `${m.top_clone.clone_name} (${m.top_clone.c})` : '—'],
    ];
    statsList.innerHTML = lines.map(([k, v]) =>
      `<li class="flex justify-between border-b border-white/5 pb-1">
         <span class="text-slate-400">${esc(k)}</span><span class="text-white">${esc(v)}</span>
       </li>`).join('');
  }

  function renderRecent(recent) {
    if (!recentList) return;
    const t = (k) => (window.SelfAII18n ? window.SelfAII18n.t(k) : k);
    if (!recent || !recent.length) {
      recentList.innerHTML = `<li class="text-slate-500 italic">${esc(t('dashboard.no_conv_yet'))}</li>`;
      return;
    }
    const fmt = (ts) => (window.SelfAII18n ? window.SelfAII18n.formatTime(ts) : ts);
    recentList.innerHTML = recent.map(r => `
      <li class="border-b border-white/5 pb-1">
        <a class="block hover:bg-white/5 rounded px-2 py-1" href="index.php?p=chat&clone=${encodeURIComponent(r.clone_id)}">
          <div class="text-xs text-slate-500">
            <time datetime="${esc(r.timestamp)}">${esc(fmt(r.timestamp))}</time> · ${esc(r.role)} · ${esc(r.clone_name)}
          </div>
          <div class="text-sm text-slate-200 truncate">${esc(r.preview)}</div>
        </a>
      </li>`).join('');
  }

  async function renderDashboard(data) {
    saxScore.textContent = (data.selfai_x_score ?? 0).toFixed ? data.selfai_x_score.toFixed(1) : data.selfai_x_score;
    renderClones(data.clones);
    renderLedger(data.ledger, data.clones);
    renderStats(data.metrics);
    renderRecent(data.recent);
  }

  if (cloneGrid && ledgerGrid && saxScore) {
    // 1) Skeletons
    cloneGrid.innerHTML = Array.from({ length: 11 }).map(() =>
      `<div class="clone-card"><div class="sk-line"></div><div class="sk-block mt-2"></div></div>`
    ).join('');
    ledgerGrid.innerHTML = Array.from({ length: 11 }).map(() =>
      `<div class="cur-tile"><span class="sk-line" style="width:60%"></span><span class="sk-line" style="width:40%;height:1.6rem"></span></div>`
    ).join('');

    // 2) IndexedDB first
    SelfAICache.getDashboard().then(c => { if (c) renderDashboard(c); }).catch(()=>{});

    // 3) Live
    api('api/user_data.php?action=dashboard').then(async data => {
      if (data && data.ok) {
        await SelfAICache.setDashboard(data);
        renderDashboard(data);
      }
    }).catch(err => {
      saxScore.textContent = '—';
      statsList && (statsList.innerHTML = `<li class="text-rose-400">Could not load dashboard: ${esc(err.message)}</li>`);
    });
  }

  // ===== Chat (3-pane shell) =====
  const chatShell    = $('.chat-shell');
  const chatForm     = $('#chatForm');
  const chatMessages = $('#chatMessages');
  const chatInput    = $('#chatInput');
  const chatStatus   = $('#chatStatus');

  // i18n shim: prefer SelfAII18n.t (loaded by i18n.js), fallback to English keys.
  const i18n = (key) => (window.SelfAII18n ? window.SelfAII18n.t(key) : key);
  const fmtTime = (ts) => (window.SelfAII18n ? window.SelfAII18n.formatTime(ts) : (ts || ''));

  /** Build a semantic <article> bubble from a <template>.
   *  role       — 'user' | 'assistant'
   *  content    — plain text content (for user, or fallback for assistant)
   *  opts       — { id, timestamp, provider, currency, memoryActive,
   *                 html (assistant only, safe HTML from server),
   *                 raw  (assistant only, plain text for clipboard),
   *                 animate (assistant only, run typewriter on .md-body),
   *                 pending (no metadata yet) }
   */
  function bubble(role, content, opts = {}) {
    const tpl = document.getElementById(role === 'user' ? 'tplBubbleUser' : 'tplBubbleAssistant');
    if (!tpl || !tpl.content) {
      const el = document.createElement('article');
      el.className = `bubble-row bubble-row--${role}`;
      el.innerHTML = `<div class="bubble ${role}"></div>`;
      el.querySelector('.bubble').textContent = content;
      return el;
    }
    const node = tpl.content.firstElementChild.cloneNode(true);

    // ── Content ─────────────────────────────────────────────────────
    const c = node.querySelector('[data-field="content"]');
    if (c) {
      if (role === 'assistant' && opts.html) {
        if (opts.animate && window.SelfAITypewriter) {
          // Defer: typewriter consumes the HTML
          c.dataset.pendingHtml = opts.html;
        } else {
          c.replaceChildren();
          const frag = document.createElement('template');
          frag.innerHTML = opts.html;
          c.appendChild(frag.content);
        }
      } else {
        c.textContent = content;
      }
    }

    // Cache the raw text on the article for Copy button.
    if (role === 'assistant') {
      node.dataset.rawReply = (opts.raw != null) ? String(opts.raw) : String(content || '');
    }

    // ── <time> ───────────────────────────────────────────────────────
    const timeEl = node.querySelector('[data-field="time"]');
    if (timeEl) {
      const iso = opts.timestamp || new Date().toISOString();
      timeEl.setAttribute('datetime', iso);
      timeEl.textContent = opts.justNow ? i18n('chat.just_now') : (fmtTime(iso) || iso);
    }

    if (role === 'assistant') {
      const pEl  = node.querySelector('[data-field="provider"]');
      const curEl= node.querySelector('[data-field="currency"]');
      const memEl= node.querySelector('[data-field="memory"]');

      if (pEl) {
        pEl.textContent = opts.provider ? `${i18n('chat.via')} ${opts.provider}` : '';
      }
      if (memEl) {
        memEl.textContent = opts.memoryActive ? i18n('chat.memory_active') : '';
        memEl.classList.toggle('text-emerald-400', !!opts.memoryActive);
      }
      if (curEl) {
        if (opts.currency) {
          const earned = Number(opts.currency.earned ?? opts.currency.delta ?? 0);
          const code   = String(opts.currency.code || '');
          const isGain = earned >= 0;
          const sign   = isGain ? '+' : '−';
          const label  = isGain ? i18n('chat.currency_earned') : i18n('chat.currency_lost');
          curEl.dataset.delta = isGain ? 'gain' : 'loss';
          curEl.textContent = '';
          const arrow = document.createElement('span');
          arrow.className = 'currency-badge__arrow';
          arrow.setAttribute('aria-hidden', 'true');
          arrow.textContent = isGain ? '▲' : '▼';
          const amt = document.createElement('strong');
          amt.className = 'currency-badge__amt';
          amt.textContent = `${sign}${Math.abs(earned)} ${code}`;
          const lbl = document.createElement('span');
          lbl.className = 'currency-badge__label sr-only';
          lbl.textContent = label;
          curEl.append(arrow, amt, lbl);
          curEl.title = `${code} ${label}: ${sign}${Math.abs(earned)}`;
        } else {
          curEl.textContent = '';
          curEl.removeAttribute('data-delta');
        }
      }
      // Wire feedback + action buttons to the turn id
      if (opts.id) {
        node.querySelectorAll('.fb-btn').forEach(btn => btn.setAttribute('data-tid', String(opts.id)));
        node.querySelectorAll('[data-action]').forEach(btn => btn.setAttribute('data-tid', String(opts.id)));
      }
      if (opts.pending) node.classList.add('is-pending');
    }

    if (opts.id) node.setAttribute('data-turn-id', String(opts.id));
    return node;
  }

  /**
   * After appending an assistant bubble that has `dataset.pendingHtml`,
   * run the typewriter inside its .md-body container.
   */
  function animateAssistantBubble(articleEl, onDone) {
    if (!articleEl) return;
    const body = articleEl.querySelector('[data-field="content"]');
    if (!body) return;
    const html = body.dataset.pendingHtml || '';
    delete body.dataset.pendingHtml;
    if (!html || !window.SelfAITypewriter) {
      if (body && html && !body.children.length) {
        const tmp = document.createElement('template');
        tmp.innerHTML = html;
        body.appendChild(tmp.content);
      }
      if (onDone) onDone();
      return;
    }
    window.SelfAITypewriter.run(body, html, {
      onTick() {
        if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
      },
      onDone: onDone || (() => {})
    });
  }

  function renderHistory(messages, memorySummary) {
    if (!chatMessages) return;
    chatMessages.replaceChildren();
    if (!messages || !messages.length) {
      const p = document.createElement('p');
      p.className = 'text-xs text-slate-500 italic';
      p.textContent = i18n('chat.say_hello');
      chatMessages.appendChild(p);
      // Also clear the drawer history list scoped to this chatbox
      renderDrawerHistory([], memorySummary || '');
      return;
    }
    messages.forEach(m => {
      const isAssistant = m.role === 'assistant';
      chatMessages.appendChild(bubble(m.role, m.content, {
        id: m.id,
        timestamp: m.timestamp,
        html: isAssistant ? (m.content_html || null) : null,
        raw:  isAssistant ? m.content : undefined,
        animate: false   // history is replayed instantly; only new replies animate
      }));
    });
    if (memorySummary !== undefined) renderDrawerHistory(messages, memorySummary || '');
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  // Expose so chatboxes.js can repaint when user switches chatbox
  window.SelfAIRenderHistory = renderHistory;

  // ---- Persona switching (without full reload) ----
  async function switchPersona(newCloneId, opts = {}) {
    if (!chatShell) return;
    const currentId = chatShell.dataset.cloneId;
    if (newCloneId === currentId && !opts.force) return;

    chatShell.dataset.cloneId = newCloneId;
    if (chatForm)  chatForm.dataset.cloneId = newCloneId;

    // Update active class in sidebar
    $$('.persona-item').forEach(el => el.classList.toggle('is-active', el.dataset.cloneId === newCloneId));

    // Update URL without reload
    const url = new URL(window.location.href);
    url.searchParams.set('p', 'chat');
    url.searchParams.set('clone', newCloneId);
    history.pushState({ cloneId: newCloneId }, '', url.toString());

    // Update toolbar title using the persona menu data
    const activeLink = $(`.persona-item[data-clone-id="${newCloneId}"]`);
    const cloneName  = $('#chatCloneName');
    const cloneCode  = $('#chatCloneCode');
    const cloneCur   = $('#chatCloneCurrency');
    if (activeLink) {
      const code  = activeLink.querySelector('.persona-item__name')?.textContent?.trim() || '';
      const proj  = activeLink.querySelector('.persona-item__sub')?.textContent?.trim() || '';
      const cur   = activeLink.querySelector('.persona-item__currency')?.textContent?.trim() || '';
      const color = activeLink.style.getPropertyValue('--persona-color') || '#a78bfa';
      if (cloneCode) cloneCode.textContent = code;
      if (cloneName) cloneName.textContent = activeLink.getAttribute('title') || code;
      if (cloneCur)  { cloneCur.textContent = cur; cloneCur.style.color = color; }
      if (chatInput) {
        chatInput.placeholder = `${i18n('chat.talk_to')} ${code}…`;
        chatInput.setAttribute('data-clone-code', code);
      }
      // ARIA: update the aria-checked state on the new menuitemradio set
      $$('.persona-item').forEach(el => {
        el.setAttribute('aria-checked', el.dataset.cloneId === newCloneId ? 'true' : 'false');
      });
    }
    if ($('#historyCloneLabel')) $('#historyCloneLabel').textContent = (activeLink?.querySelector('.persona-item__name')?.textContent || newCloneId);

    // Reset chatbox context when switching clones — let server pick default
    chatShell.dataset.chatboxId = '0';
    // Refresh chatbox list for the new clone
    if (window.SelfAIChatboxes) {
      window.SelfAIChatboxes.refresh(newCloneId).catch(()=>{});
    }

    // Render skeleton then hydrate
    if (chatMessages) {
      chatMessages.replaceChildren();
      const sk = document.createElement('p');
      sk.className = 'text-xs text-slate-500 italic sk-line';
      sk.textContent = i18n('chat.loading_history');
      chatMessages.appendChild(sk);
    }
    closeMobileDrawer('personas');

    // IndexedDB first (no chatbox-scoped cache yet → just show last clone-wide)
    try {
      const c = await SelfAICache.getHistory(newCloneId);
      if (c && c.messages) renderHistory(c.messages, c.memory_summary || '');
    } catch (_) {}

    // Live history — fetches default chatbox first time
    try {
      const r = await api('api/user_data.php?action=history&clone_id=' + encodeURIComponent(newCloneId));
      if (r && r.ok) {
        await SelfAICache.setHistory(newCloneId, r.messages);
        renderHistory(r.messages, r.memory_summary || '');
        renderDrawerHistory(r.messages, r.memory_summary || '');
      }
    } catch (err) {
      if (chatStatus) chatStatus.textContent = `${i18n('errors.could_not_load')}: ${err.message}`;
    }
  }

  // ---- History drawer rendering ----
  function renderDrawerHistory(messages, memorySummary) {
    const list   = $('#historyList');
    const memBox = $('#historyMemoryBody');
    if (memBox) {
      memBox.textContent = memorySummary ? memorySummary : i18n('chat.no_memory_yet');
    }
    if (!list) return;
    list.replaceChildren();
    if (!messages || !messages.length) {
      const li = document.createElement('li');
      li.className = 'text-xs text-slate-500 italic px-2 py-2';
      li.textContent = i18n('chat.no_conv_persona');
      list.appendChild(li);
      return;
    }
    const tpl = document.getElementById('tplHistoryItem');
    messages.slice().reverse().forEach(m => {
      let node;
      if (tpl && tpl.content) {
        node = tpl.content.firstElementChild.cloneNode(true);
        node.setAttribute('data-turn-id', String(m.id));
        node.setAttribute('data-role', m.role);
        const r = node.querySelector('[data-field="role"]');
        if (r) {
          r.textContent = i18n('chat.' + m.role) !== ('chat.' + m.role) ? i18n('chat.' + m.role) : m.role;
          r.className = `role-${m.role}`;
        }
        const t = node.querySelector('[data-field="time"]');
        if (t) {
          const iso = m.timestamp || '';
          t.setAttribute('datetime', iso);
          t.textContent = fmtTime(iso) || iso;
        }
        const p = node.querySelector('[data-field="preview"]');
        if (p) p.textContent = m.content;
      } else {
        node = document.createElement('li');
        node.className = 'history-item';
        node.textContent = m.content;
      }
      list.appendChild(node);
    });
  }

  // ---- Drawer toggles ----
  function openDesktopHistory(open) {
    if (!chatShell) return;
    chatShell.dataset.historyOpen = open ? 'true' : 'false';
  }
  function openMobileDrawer(which) {
    if (!chatShell) return;
    chatShell.dataset.mobileOpen = which;
    document.body.style.overflow = 'hidden';
  }
  function closeMobileDrawer(_which) {
    if (!chatShell) return;
    delete chatShell.dataset.mobileOpen;
    document.body.style.overflow = '';
  }
  function isMobile() { return window.matchMedia('(max-width: 900px)').matches; }

  if (chatShell) {
    const initialCloneId = chatShell.dataset.cloneId;

    // Initial load — same as switchPersona but without animating change
    (async () => {
      try {
        const c = await SelfAICache.getHistory(initialCloneId);
        if (c && c.messages) renderHistory(c.messages);
      } catch (_) {}
      try {
        const r = await api('api/user_data.php?action=history&clone_id=' + encodeURIComponent(initialCloneId));
        if (r && r.ok) {
          await SelfAICache.setHistory(initialCloneId, r.messages);
          renderHistory(r.messages);
          renderDrawerHistory(r.messages, r.memory_summary || '');
        }
      } catch (err) {
        if (chatStatus) chatStatus.textContent = `${i18n('errors.could_not_load')}: ${err.message}`;
      }
    })();

    // Click on a persona in sidebar
    chatShell.addEventListener('click', (e) => {
      const personaLink = e.target.closest('.persona-item');
      if (personaLink) {
        e.preventDefault();
        const id = personaLink.dataset.cloneId;
        if (id) switchPersona(id);
        return;
      }
      const action = e.target.closest('[data-action]')?.dataset.action;
      if (!action) return;
      if (action === 'open-personas') { e.preventDefault(); openMobileDrawer('personas'); }
      else if (action === 'close-personas') { e.preventDefault(); closeMobileDrawer('personas'); }
      else if (action === 'open-history') {
        e.preventDefault();
        const toggle = $('#historyToggle');
        if (isMobile()) {
          openMobileDrawer('history');
          if (toggle) toggle.setAttribute('aria-expanded', 'true');
        } else {
          const open = chatShell.dataset.historyOpen !== 'true';
          openDesktopHistory(open);
          if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
      }
      else if (action === 'close-history') {
        e.preventDefault();
        const toggle = $('#historyToggle');
        if (isMobile()) closeMobileDrawer('history');
        else openDesktopHistory(false);
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
      }
      else if (action === 'close-all') { e.preventDefault(); closeMobileDrawer(); }
      else if (action === 'clear-cache') {
        e.preventDefault();
        const cid = chatShell.dataset.cloneId;
        SelfAICache.setHistory(cid, []).then(() => switchPersona(cid, { force: true }));
      }
    });

    // Back/forward browser navigation
    window.addEventListener('popstate', (e) => {
      const url = new URL(window.location.href);
      const id = url.searchParams.get('clone');
      if (id) switchPersona(id, { force: true });
    });

    // Send message
    async function sendMessage(msg, { regenerate = false, replaceArticle = null } = {}) {
      const cloneId   = chatShell.dataset.cloneId;
      const chatboxId = parseInt(chatShell.dataset.chatboxId || '0', 10) || 0;
      if (!msg) return;

      // User bubble (skip if we're regenerating — the previous user turn is reused)
      if (!regenerate) {
        chatMessages.appendChild(bubble('user', msg, { justNow: true }));
      }

      // Pending assistant bubble (typing indicator)
      const pending = bubble('assistant', i18n('chat.thinking'), { justNow: true, pending: true });
      if (replaceArticle && replaceArticle.parentNode) {
        replaceArticle.replaceWith(pending);
      } else {
        chatMessages.appendChild(pending);
      }
      chatMessages.scrollTop = chatMessages.scrollHeight;
      if (chatStatus) chatStatus.textContent = i18n('chat.talking_to');

      try {
        const r = await api('api/chat.php', {
          clone_id:   cloneId,
          message:    msg,
          chatbox_id: chatboxId,
          regenerate: !!regenerate
        });
        const memActive = !!(r.memory && r.memory.summary_present);

        // Remember resolved chatbox id (may have been auto-created)
        if (r.chatbox_id) chatShell.dataset.chatboxId = String(r.chatbox_id);

        const finalBubble = bubble('assistant', r.reply, {
          id: r.turn_ids?.assistant,
          timestamp: new Date().toISOString(),
          provider: r.provider,
          currency: r.currency,
          memoryActive: memActive,
          html:    r.reply_html || null,
          raw:     r.reply,
          animate: true
        });
        pending.replaceWith(finalBubble);
        chatMessages.scrollTop = chatMessages.scrollHeight;

        // Trigger typewriter for the HTML body
        animateAssistantBubble(finalBubble, () => {
          chatMessages.scrollTop = chatMessages.scrollHeight;
        });

        if (chatStatus) {
          if (r.provider === 'offline_fallback' || r.offline) {
            chatStatus.textContent = '⚠ ' + i18n('chat.offline_warning');
            chatStatus.className = 'text-[11px] text-amber-400 px-4 pb-2 block';
          } else {
            chatStatus.textContent = '';
          }
        }

        // Refresh history cache + drawer (scoped to current chatbox)
        const histUrl = 'api/user_data.php?action=history&clone_id=' + encodeURIComponent(cloneId)
                      + '&chatbox_id=' + encodeURIComponent(chatShell.dataset.chatboxId || '0');
        api(histUrl).then(rr => {
          if (rr && rr.ok) {
            SelfAICache.setHistory(cloneId + ':' + (chatShell.dataset.chatboxId || '0'), rr.messages).catch(()=>{});
            renderDrawerHistory(rr.messages, rr.memory_summary || '');
          }
        }).catch(()=>{});

        // Refresh chatbox list if available
        if (window.SelfAIChatboxes) {
          window.SelfAIChatboxes.refresh(cloneId).catch(()=>{});
        }

        return r;
      } catch (err) {
        pending.remove();
        if (chatStatus) {
          chatStatus.textContent = `${i18n('common.error')}: ${err.message}`;
          chatStatus.className = 'text-[11px] text-rose-400 px-4 pb-2 block';
        }
      }
    }

    if (chatForm) {
      chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const msg = chatInput.value.trim();
        if (!msg) return;
        chatInput.value = '';
        await sendMessage(msg);
      });

      // Feedback clicks (delegated to the messages region)
      chatMessages.addEventListener('click', async (e) => {
        const btn = e.target.closest('.fb-btn');
        if (!btn) return;
        const tid = parseInt(btn.dataset.tid, 10);
        const fb  = parseInt(btn.dataset.fb,  10);
        if (!tid) return;
        try {
          await api('api/user_data.php?action=feedback', { turn_id: tid, feedback: fb });
          btn.parentElement.querySelectorAll('.fb-btn').forEach(b => {
            b.classList.remove('active');
            b.setAttribute('aria-pressed', 'false');
          });
          btn.classList.add('active');
          btn.setAttribute('aria-pressed', 'true');
          if (chatStatus) {
            chatStatus.textContent = i18n('chat.feedback_sent');
            chatStatus.className = 'text-[11px] text-emerald-400 px-4 pb-2 block';
            setTimeout(() => { if (chatStatus) chatStatus.textContent = ''; }, 1800);
          }
        } catch (err) {
          if (chatStatus) chatStatus.textContent = `${i18n('errors.feedback_error')}: ${err.message}`;
        }
      });

      // ── Copy + Regenerate (delegated) ────────────────────────────────
      chatMessages.addEventListener('click', async (e) => {
        const actionBtn = e.target.closest('[data-action="copy"], [data-action="regenerate"]');
        if (!actionBtn) return;
        const article = actionBtn.closest('article[data-role="assistant"]');
        if (!article) return;
        const action = actionBtn.dataset.action;

        if (action === 'copy') {
          const raw = article.dataset.rawReply
            || article.querySelector('[data-field="content"]')?.innerText
            || '';
          try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
              await navigator.clipboard.writeText(raw);
            } else {
              const ta = document.createElement('textarea');
              ta.value = raw;
              ta.style.position = 'fixed'; ta.style.opacity = '0';
              document.body.appendChild(ta); ta.select();
              document.execCommand('copy');
              ta.remove();
            }
            const lbl = actionBtn.querySelector('.sr-only');
            const oldText = lbl ? lbl.textContent : '';
            if (lbl) lbl.textContent = i18n('chat.copied');
            actionBtn.classList.add('is-success');
            actionBtn.setAttribute('aria-label', i18n('chat.copied'));
            setTimeout(() => {
              actionBtn.classList.remove('is-success');
              if (lbl) lbl.textContent = oldText || i18n('chat.copy');
              actionBtn.setAttribute('aria-label', i18n('chat.copy'));
            }, 1500);
          } catch (err) {
            if (chatStatus) chatStatus.textContent = `${i18n('errors.copy_failed')}: ${err.message}`;
          }
          return;
        }

        if (action === 'regenerate') {
          // Find the user turn that preceded this assistant article
          const all = Array.from(chatMessages.querySelectorAll('article[data-role]'));
          const idx = all.indexOf(article);
          let prevUser = null;
          for (let i = idx - 1; i >= 0; i--) {
            if (all[i].dataset.role === 'user') { prevUser = all[i]; break; }
          }
          if (!prevUser) return;
          const userText = prevUser.querySelector('[data-field="content"]')?.textContent || '';
          if (!userText.trim()) return;
          try {
            actionBtn.setAttribute('disabled', 'disabled');
            await sendMessage(userText, { regenerate: true, replaceArticle: article });
          } catch (err) {
            if (chatStatus) chatStatus.textContent = `${i18n('errors.regen_failed')}: ${err.message}`;
          } finally {
            actionBtn.removeAttribute('disabled');
          }
        }
      });

      // Click on a history item scrolls to that turn (if visible in main thread)
      const histList = $('#historyList');
      if (histList) {
        histList.addEventListener('click', (e) => {
          const item = e.target.closest('.history-item');
          if (!item) return;
          const tid = item.dataset.turnId;
          const target = chatMessages.querySelector(`[data-turn-id="${tid}"]`);
          if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
          if (isMobile()) closeMobileDrawer('history');
        });
      }
    }

    // Close mobile drawers on ESC
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        if (isMobile()) closeMobileDrawer();
        else openDesktopHistory(false);
      }
    });
  }
})();
