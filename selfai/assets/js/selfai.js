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
      status.classList.remove('hidden');
      status.className = 'text-sm text-slate-400';
      status.textContent = action === 'signup' ? 'Creating your SelfAI…' : 'Signing in…';
      try {
        await api('api/auth.php?action=' + action, body);
        status.className = 'text-sm text-emerald-400';
        status.textContent = 'Welcome — redirecting…';
        location.href = 'index.php?p=dashboard';
      } catch (err) {
        status.className = 'text-sm text-rose-400';
        status.textContent = (err && err.message) || 'Could not complete';
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
    const lines = [
      ['Total messages',           m.total_conversations ?? 0],
      ['Distinct personas used',   (m.distinct_clones ?? 0) + ' / 11'],
      ['Profile attributes saved', m.attributes ?? 0],
      ['Resumes uploaded',         m.resumes ?? 0],
      ['Feedback signal (avg)',    (m.feedback_avg ?? 0).toFixed(2)],
      ['Top persona',              m.top_clone ? `${m.top_clone.clone_name} (${m.top_clone.c} turns)` : '—'],
    ];
    statsList.innerHTML = lines.map(([k, v]) =>
      `<li class="flex justify-between border-b border-white/5 pb-1">
         <span class="text-slate-400">${esc(k)}</span><span class="text-white">${esc(v)}</span>
       </li>`).join('');
  }

  function renderRecent(recent) {
    if (!recentList) return;
    if (!recent || !recent.length) {
      recentList.innerHTML = '<li class="text-slate-500 italic">No conversations yet — pick a persona to begin.</li>';
      return;
    }
    recentList.innerHTML = recent.map(r => `
      <li class="border-b border-white/5 pb-1">
        <a class="block hover:bg-white/5 rounded px-2 py-1" href="index.php?p=chat&clone=${encodeURIComponent(r.clone_id)}">
          <div class="text-xs text-slate-500">${esc(r.timestamp)} · ${esc(r.role)} · ${esc(r.clone_name)}</div>
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

  // ===== Chat =====
  const chatForm = $('#chatForm');
  const chatMessages = $('#chatMessages');
  const chatInput = $('#chatInput');
  const chatStatus = $('#chatStatus');

  function bubble(role, content, meta) {
    const el = document.createElement('div');
    el.className = 'space-y-1';
    el.innerHTML = `
      <div class="bubble ${role}">${esc(content)}</div>
      ${meta ? `<div class="bubble-meta ${role === 'user' ? 'text-right pr-1' : 'pl-1'}">${meta}</div>` : ''}`;
    return el;
  }

  function feedbackControls(turnId) {
    return `
      <button class="fb-btn up" data-fb="1"  data-tid="${turnId}" aria-label="Helpful">▲ helpful</button>
      <button class="fb-btn down" data-fb="-1" data-tid="${turnId}" aria-label="Not helpful">▼ not helpful</button>
    `;
  }

  function renderHistory(messages) {
    chatMessages.innerHTML = '';
    if (!messages.length) {
      chatMessages.innerHTML = '<div class="text-xs text-slate-500 italic">Say hello — your persona is ready.</div>';
      return;
    }
    messages.forEach(m => {
      const meta = m.role === 'assistant'
        ? `<span class="text-slate-500">${esc(m.timestamp || '')}</span> ${feedbackControls(m.id)}`
        : `<span class="text-slate-500">${esc(m.timestamp || '')}</span>`;
      chatMessages.appendChild(bubble(m.role, m.content, meta));
    });
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  if (chatForm) {
    const cloneId = chatForm.dataset.cloneId;

    // 1) Try IndexedDB
    SelfAICache.getHistory(cloneId).then(c => {
      if (c && c.messages) renderHistory(c.messages);
    });

    // 2) Live history
    api('api/user_data.php?action=history&clone_id=' + encodeURIComponent(cloneId))
      .then(async r => {
        if (r && r.ok) {
          await SelfAICache.setHistory(cloneId, r.messages);
          renderHistory(r.messages);
        }
      })
      .catch(err => {
        chatStatus.textContent = 'Could not load history: ' + err.message;
      });

    chatForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const msg = chatInput.value.trim();
      if (!msg) return;
      chatInput.value = '';
      chatMessages.appendChild(bubble('user', msg, '<span class="text-slate-500">just now</span>'));
      const pending = bubble('assistant', '…thinking…', '<span class="sk-line" style="width:5rem;display:inline-block"></span>');
      chatMessages.appendChild(pending);
      chatMessages.scrollTop = chatMessages.scrollHeight;
      chatStatus.textContent = 'Talking to your persona…';

      try {
        const r = await api('api/chat.php', { clone_id: cloneId, message: msg });
        pending.remove();
        const meta = `<span class="text-slate-500">via ${esc(r.provider)} · earned <strong style="color:#a78bfa">+${r.currency.earned} ${esc(r.currency.code)}</strong></span> ${feedbackControls(r.turn_ids.assistant)}`;
        chatMessages.appendChild(bubble('assistant', r.reply, meta));
        chatMessages.scrollTop = chatMessages.scrollHeight;
        chatStatus.textContent = '';

        // refresh cache
        api('api/user_data.php?action=history&clone_id=' + encodeURIComponent(cloneId))
          .then(rr => rr && rr.ok && SelfAICache.setHistory(cloneId, rr.messages)).catch(()=>{});
      } catch (err) {
        pending.remove();
        chatStatus.textContent = 'Error: ' + err.message;
      }
    });

    chatMessages.addEventListener('click', async (e) => {
      const btn = e.target.closest('.fb-btn');
      if (!btn) return;
      const tid = parseInt(btn.dataset.tid, 10);
      const fb  = parseInt(btn.dataset.fb,  10);
      try {
        await api('api/user_data.php?action=feedback', { turn_id: tid, feedback: fb });
        btn.parentElement.querySelectorAll('.fb-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
      } catch (err) {
        chatStatus.textContent = 'Feedback error: ' + err.message;
      }
    });
  }
})();
