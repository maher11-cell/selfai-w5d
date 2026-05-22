/**
 * SelfAI — Chatboxes controller (right drawer).
 *
 * Each clone hosts many parallel chatboxes. The active one is stored on
 * .chat-shell[data-chatbox-id]. Switching a chatbox:
 *   - updates the URL (?chatbox=N) via history.pushState
 *   - re-fetches scoped history via api/user_data.php?action=history&chatbox_id=...
 *   - SelfAI.switchPersona NOT called (clone unchanged)
 *
 * Exposes window.SelfAIChatboxes = { refresh, list, create, switchTo, getActive }.
 */
(function () {
  'use strict';

  function $(sel, root = document) { return root.querySelector(sel); }
  const i18n   = (k) => (window.SelfAII18n ? window.SelfAII18n.t(k) : k);
  const fmtTime= (ts) => (window.SelfAII18n ? window.SelfAII18n.formatTime(ts) : (ts || ''));

  async function apiJson(url, body) {
    const opts = { credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
    if (body !== undefined) {
      opts.method = 'POST';
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    const r = await fetch(url, opts);
    const data = await r.json().catch(() => ({}));
    if (!r.ok || data.error) throw new Error(data.error || data.message || ('HTTP ' + r.status));
    return data;
  }

  function shell() { return $('.chat-shell'); }
  function getActive() {
    const s = shell();
    return s ? parseInt(s.dataset.chatboxId || '0', 10) || 0 : 0;
  }
  function setActive(id) {
    const s = shell();
    if (!s) return;
    s.dataset.chatboxId = String(id);
    // Sync URL
    const url = new URL(window.location.href);
    if (id) url.searchParams.set('chatbox', String(id));
    else url.searchParams.delete('chatbox');
    history.replaceState(history.state || {}, '', url.toString());
  }

  /** Fetch chatbox list for the given clone, then render into #chatboxList. */
  async function refresh(cloneId) {
    cloneId = cloneId || shell()?.dataset.cloneId;
    if (!cloneId) return [];
    let data;
    try {
      data = await apiJson('api/chatboxes.php?action=list&clone_id=' + encodeURIComponent(cloneId));
    } catch (err) {
      console.warn('Chatbox list failed', err);
      return [];
    }
    const list = data.chatboxes || [];
    const ul = $('#chatboxList');
    if (!ul) return list;

    // Resolve current active id (auto-create if needed)
    let active = getActive();
    if (!active && list.length) {
      active = list[0].id;
      setActive(active);
    }

    ul.replaceChildren();
    if (!list.length) {
      const li = document.createElement('li');
      li.className = 'text-xs text-slate-500 italic';
      li.textContent = i18n('chat.no_memory_yet');
      ul.appendChild(li);
      return list;
    }
    for (const box of list) {
      const li = document.createElement('li');
      li.className = 'chatbox-item' + (box.id === active ? ' is-active' : '');
      li.setAttribute('role', 'listitem');
      li.dataset.chatboxId = String(box.id);
      li.tabIndex = 0;

      const title = document.createElement('span');
      title.className = 'chatbox-item__title';
      title.textContent = box.title || i18n('chat.main_chatbox');

      const meta = document.createElement('time');
      meta.className = 'chatbox-item__time';
      const ts = box.last_timestamp || box.updated_at;
      meta.setAttribute('datetime', ts || '');
      meta.textContent = ts ? fmtTime(ts) : '';

      const menu = document.createElement('button');
      menu.type = 'button';
      menu.className = 'chatbox-item__menu';
      menu.dataset.action = 'chatbox-menu';
      menu.dataset.chatboxId = String(box.id);
      menu.setAttribute('aria-label', i18n('common.menu') || 'Menu');
      menu.textContent = '⋯';

      li.append(title, meta, menu);
      ul.appendChild(li);
    }
    return list;
  }

  async function create(cloneId, title) {
    cloneId = cloneId || shell()?.dataset.cloneId;
    if (!cloneId) throw new Error('no_clone');
    const r = await apiJson('api/chatboxes.php', {
      action: 'create', clone_id: cloneId, title: title || ''
    });
    setActive(r.id);
    await refresh(cloneId);
    // Clear messages region
    const msgs = $('#chatMessages');
    if (msgs) {
      msgs.replaceChildren();
      const p = document.createElement('p');
      p.className = 'text-xs text-slate-500 italic';
      p.textContent = i18n('chat.say_hello');
      msgs.appendChild(p);
    }
    return r.id;
  }

  async function switchTo(chatboxId) {
    setActive(chatboxId);
    // Update active class
    document.querySelectorAll('#chatboxList .chatbox-item').forEach(el => {
      el.classList.toggle('is-active', parseInt(el.dataset.chatboxId, 10) === chatboxId);
    });
    // Reload messages scoped to this chatbox
    const cloneId = shell()?.dataset.cloneId;
    if (!cloneId) return;
    const r = await apiJson('api/user_data.php?action=history'
      + '&clone_id=' + encodeURIComponent(cloneId)
      + '&chatbox_id=' + encodeURIComponent(chatboxId));
    if (r && r.ok && window.SelfAIRenderHistory) {
      window.SelfAIRenderHistory(r.messages, r.memory_summary || '');
    } else if (r && r.ok) {
      // Fallback: dispatch a custom event the main script listens to
      document.dispatchEvent(new CustomEvent('selfai:history-loaded', {
        detail: { messages: r.messages, memory_summary: r.memory_summary }
      }));
    }
  }

  async function rename(chatboxId, newTitle) {
    if (!chatboxId || !newTitle) return;
    await apiJson('api/chatboxes.php', { action: 'rename', id: chatboxId, title: newTitle });
    const cloneId = shell()?.dataset.cloneId;
    await refresh(cloneId);
  }

  async function archive(chatboxId) {
    if (!chatboxId) return;
    await apiJson('api/chatboxes.php', { action: 'archive', id: chatboxId });
    if (getActive() === chatboxId) setActive(0);
    const cloneId = shell()?.dataset.cloneId;
    await refresh(cloneId);
    if (!getActive()) {
      // After archive, active might be reset → load first chatbox in list
      const list = await refresh(cloneId);
      if (list.length) await switchTo(list[0].id);
    }
  }

  // ── Wire UI events ─────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    const drawer = $('#historyDrawer');
    if (!drawer) return;
    drawer.addEventListener('click', async (e) => {
      const newBtn = e.target.closest('[data-action="new-chatbox"]');
      if (newBtn) {
        e.preventDefault();
        try {
          await create();
        } catch (err) {
          console.warn(err);
          alert(i18n('errors.chatbox_failed') + ': ' + err.message);
        }
        return;
      }
      const menuBtn = e.target.closest('[data-action="chatbox-menu"]');
      if (menuBtn) {
        e.preventDefault();
        e.stopPropagation();
        const id = parseInt(menuBtn.dataset.chatboxId, 10);
        const choice = prompt(
          i18n('chat.rename') + ' / ' + i18n('chat.delete') + '?\n'
          + '→ ' + i18n('chat.rename') + ': type new title\n'
          + '→ ' + i18n('chat.delete') + ': type "DELETE"',
          ''
        );
        if (choice == null) return;
        const v = choice.trim();
        try {
          if (v.toUpperCase() === 'DELETE') {
            await archive(id);
          } else if (v !== '') {
            await rename(id, v);
          }
        } catch (err) {
          alert(i18n('errors.chatbox_failed') + ': ' + err.message);
        }
        return;
      }
      const item = e.target.closest('.chatbox-item');
      if (item) {
        const id = parseInt(item.dataset.chatboxId, 10);
        if (id && id !== getActive()) {
          try { await switchTo(id); }
          catch (err) { console.warn(err); }
        }
      }
    });

    // Initial load (once the clone is known on chat-shell)
    const s = shell();
    if (s && s.dataset.cloneId) {
      refresh(s.dataset.cloneId).catch(()=>{});
    }
  });

  window.SelfAIChatboxes = { refresh, create, switchTo, rename, archive, getActive };
})();
