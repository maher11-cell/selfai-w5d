# SelfAI — Super-Admin Manual

> **Audience.** The single human operator (and any future trusted ops partner) of the
> SelfAI deployment that powers the 2030B Ecosystem. This document is **not** for end
> users. It documents secrets, file system layout, schema, provider keys, the eternal
> loop, and recovery procedures.
>
> **Last refresh.** 2026-05 (PR #1 — "feat(selfai): transform W5D into SelfAI SaaS").
>
> **Code name.** `selfai` (lives under `selfai/` inside the W5D static-site repo).

---

## 1. One-page mental model

```
                ┌──────────────────────────────────────────┐
 User browser → │ index.php (router: home/dashboard/chat)  │
                │   layout → bootstraps window.__SELFAI__: │
                │     · user, brand                        │
                │     · i18n {locale, dir, info, strings}  │
                │     · animation {char/phrase/paragraph}  │
                └────────────┬─────────────────────────────┘
                             │  fetch JSON
       ┌─────────────────────┼────────────────────────┐
       ▼                     ▼                        ▼
 api/auth.php          api/chat.php  ──(if X)──▶ api/selfai_x.php
                           │                          │
                           │ generic 11-clone path    │ eternal-loop path
                           ▼                          ▼
                  user_<id>.db (SQLite)       selfai_x_state_user_<id>.json
                  + system.db                 + selfai_x_core.json (read-only)
                           │                          │
                           └────── currency_ledger ───┘
                                  +2030B coins (CTC,TIC,VTC,INC,SCC,
                                                WPC,WDC,JEC,FLC,GRC,SAX)
```

Front-end JS modules load in this strict order (`<head defer>`):
`idb.js → i18n.js → typewriter.js → chatboxes.js → selfai.js`.

---

## 2. Filesystem map

| Path                                                        | Role                                                                | Git tracked?  |
|-------------------------------------------------------------|---------------------------------------------------------------------|---------------|
| `selfai/index.php`                                          | Single-file router + layout (`?p=home|chat|dashboard|login|...`)    | ✅            |
| `selfai/_landing.php`, `selfai/_docs.php`                   | Body partials                                                       | ✅            |
| `selfai/includes/db.php`                                    | DB bootstrap, i18n, memory helpers, MD→HTML, chatbox helpers        | ✅            |
| `selfai/api/auth.php`                                       | login / signup / logout / me                                        | ✅            |
| `selfai/api/clones.php`                                     | List the 11 clones from `config.json`                               | ✅            |
| `selfai/api/chat.php`                                       | Generic chat for 10 clones; routes SelfAI-X to `selfai_x.php`       | ✅            |
| `selfai/api/selfai_x.php`                                   | The **eternal loop** endpoint (integrator clone)                    | ✅            |
| `selfai/api/user_data.php`                                  | Dashboard, history, conversations_list, attributes, feedback        | ✅            |
| `selfai/api/chatboxes.php`                                  | CRUD for per-clone parallel chat sessions                           | ✅            |
| `selfai/api/i18n.php`                                       | `?action=get|list|set&lang=xx`                                      | ✅            |
| `selfai/api/i18n.json`                                      | Source of truth for translations (en/ar/fr)                         | ✅            |
| `selfai/api/animation.json`                                 | Typewriter timings + reduced-motion + cursor config                 | ✅            |
| `selfai/api/config.json`                                    | Clones, provider endpoints, model lists, summary policy             | ✅            |
| `selfai/api/selfai_x_core.json`                             | **Immutable** SelfAI-X identity anchor                              | ✅            |
| `selfai/api/selfai_x_state.json`                            | Template for new per-user state                                     | ✅            |
| `selfai/api/.env`                                           | Provider API keys (DEEPSEEK / OPENROUTER / GROQ / GEMINI / HF)      | 🚫 gitignored |
| `selfai/api/.selfai_runtime.json`                           | One-shot per-prompt bridge (overwritten every call)                 | 🚫 gitignored |
| `selfai/database/system.db`                                 | users, sessions                                                     | 🚫 gitignored |
| `selfai/database/user_<id>.db`                              | per-user: conversations, chatboxes, data_selfai, currency_ledger    | 🚫 gitignored |
| `selfai/database/selfai_x_state_user_<id>.json`             | Mutable SelfAI-X state, **one file per user**                       | 🚫 gitignored |
| `selfai/assets/js/idb.js`                                   | Tiny IndexedDB cache (history skeleton-loading)                     | ✅            |
| `selfai/assets/js/i18n.js`                                  | Reads `window.__SELFAI__.i18n` + wires `<details>` lang switcher    | ✅            |
| `selfai/assets/js/typewriter.js`                            | Char / phrase-loader / paragraph-pause reveal engine                | ✅            |
| `selfai/assets/js/chatboxes.js`                             | Right-drawer chatbox list + new/rename/archive                      | ✅            |
| `selfai/assets/js/selfai.js`                                | Main UI controller (bubbles, send, copy, regenerate, persona swap)  | ✅            |
| `selfai/assets/css/selfai.css`                              | All styles (incl. RTL, .md-*, .tw-*, .bubble-*, .chatbox-*)         | ✅            |

---

## 3. Provider keys (`api/.env`)

`api/.env` is parsed by `selfai_env()` in `includes/db.php` (a 30-line parser, no Composer).

```
DEEPSEEK_API_KEY=sk-...
OPENROUTER_API_KEY=sk-or-v1-...
GROQ_API_KEY=
GEMINI_API_KEY=
HF_TOKEN=
APP_URL=https://2030b.com/selfai
APP_TITLE=SelfAI · 2030B Ecosystem
```

### Provider fallback chain (both `chat.php` and `selfai_x.php`)

1. **DeepSeek V4** (`deepseek-chat`) — fastest. Note: if the key returns **HTTP 402
   "Insufficient Balance"**, the chain falls through silently.
2. **OpenRouter** — multi-model retry. **Requires** these headers:
   - `HTTP-Referer: $APP_URL`
   - `X-Title: $APP_TITLE`

   Models are iterated from `config.json → openrouter_models`. First 2xx wins. A 401/403
   stops further OpenRouter retries (key issue). A 429 falls through to the next model.

3. **Groq** (`llama-3.3-70b-versatile`)
4. **Gemini** (`gemini-2.0-flash-exp` via OpenAI-compat endpoint)
5. **Hugging Face** (`meta-llama/Llama-3.2-3B-Instruct` via router)
6. **Offline fallback** — always succeeds, returns the clone's audit prompt.

### Rotating a key

1. Edit `selfai/api/.env`.
2. Confirm with: `curl -sb /tmp/cookies.txt -X POST -H "Content-Type: application/json" \
   -d '{"clone_id":"selfai_s_smarter","message":"ping"}' \
   http://localhost:8080/selfai/api/chat.php | jq .provider`
3. Expected `provider`: `deepseek` (preferred) or `openrouter`.

---

## 4. Database schema (auto-migrating)

`includes/db.php → selfai_user_db($userId)` opens (and migrates) a per-user SQLite file.

```
conversations         id, clone_id, clone_name, chatbox_id, role, content,
                      feedback, tokens_used, timestamp
chatboxes             id, clone_id, title, summary, archived, created_at, updated_at
resumes               id, filename, file_content, uploaded_at
data_selfai           id, attribute_key UNIQUE, attribute_value, updated_at
deepseek_apis         id, endpoint, clone_id, request_payload, response,
                      tokens_used, provider, created_at
currency_ledger       id, currency, amount, source_clone, note, created_at
```

`system.db` has only `users` and `sessions` tables.

**Migrations are idempotent** — `ALTER TABLE … ADD COLUMN chatbox_id` runs only if a
`PRAGMA table_info` check shows the column missing.

### Soft-reset a user

```bash
sqlite3 selfai/database/user_3.db <<SQL
DELETE FROM conversations;
DELETE FROM chatboxes;
DELETE FROM data_selfai;
DELETE FROM currency_ledger;
SQL
rm -f selfai/database/selfai_x_state_user_3.json   # SelfAI-X eternal state
```

### Hard-purge a user

```bash
rm -f selfai/database/user_3.db \
      selfai/database/selfai_x_state_user_3.json
sqlite3 selfai/database/system.db "DELETE FROM users WHERE id=3;"
```

---

## 5. The 11 clones + 2030B currencies

Source of truth: `api/config.json → clones[]`. Each clone has
`{ id, code, name, project, currency, ayah, icon, system_prompt, … }`.

| Clone ID                | Code      | Currency | Anchor (selfai_x_master is the integrator) |
|-------------------------|-----------|----------|--------------------------------------------|
| `selfai_s_smarter`      | SelfAI-S  | CTC      | Mind                                       |
| `selfai_h_honester`     | SelfAI-H  | TIC      | Conscience                                 |
| `selfai_v_healthier`    | SelfAI-V  | VTC      | Body                                       |
| `selfai_c_creater`      | SelfAI-C  | INC      | Imagination                                |
| `selfai_k_kinder`       | SelfAI-K  | SCC      | Spirit                                     |
| `selfai_w_stronger`     | SelfAI-W  | WPC      | Will                                       |
| `selfai_d_wiser`        | SelfAI-D  | WDC      | Wisdom                                     |
| `selfai_j_fairer`       | SelfAI-J  | JEC      | Justice                                    |
| `selfai_f_freer`        | SelfAI-F  | FLC      | Freedom                                    |
| `selfai_g_thankful`     | SelfAI-G  | GRC      | Gratitude                                  |
| `selfai_x_master`       | SelfAI-X  | SAX      | **Integrator (eternal loop)**              |

Currency is awarded by regex-parsing the final line of every reply:
`<CURRENCY> earned this turn: N` (N clamped 1..5). The line is **stripped** from the
rendered HTML and the amount is shown as a footer pill (▲ gain / ▼ loss).

---

## 6. SelfAI-X eternal loop (PART 2)

### Three JSON files

| File                                                       | Mutability                | Owner       |
|------------------------------------------------------------|---------------------------|-------------|
| `api/selfai_x_core.json`                                   | **Immutable**             | git         |
| `api/selfai_x_state.json`                                  | Template (read-only)      | git         |
| `database/selfai_x_state_user_<id>.json`                   | Per-user, mutated each turn | gitignored |
| `api/.selfai_runtime.json`                                 | One-shot, overwritten     | gitignored |

### Per-turn lifecycle in `api/selfai_x.php`

1. Load `selfai_x_core.json` (immutable identity, axioms, boundaries, guardrails).
2. Load `selfai_x_state_user_<id>.json` (or seed it from the template).
3. Pull last **100** conversations for `clone_id = selfai_x_master` across all chatboxes.
4. Write the **runtime bridge** `api/.selfai_runtime.json` (snapshot of core+state+
   episodic+user_prompt; useful for debugging — see §10).
5. Call provider chain with an **Emergent Prompt** (see code) that:
   - Embeds core.identity, core.core_axioms, core.digital_self_boundaries, **current state**.
   - Demands an `SAX earned this turn: N` line.
   - Demands a snapshot block when state changes:

     ```
     ---SELF_STATE_SNAPSHOT---
     { "emotional_attention_tensor": {"curiosity": 0.62}, "current_life_epoch": 1 }
     ---END_SELF_STATE_SNAPSHOT---
     ```
   - On every **10th** interaction the prompt asks for a future_memory entry
     (24h-horizon prediction) — and PHP auto-injects one if the AI forgets.
   - Allows `<paradox_hold id="…">…</paradox_hold>` for unresolved contradictions.
   - Allows a single hidden `<!-- SELF_NEXT_ACTION: ["verb","object"] -->`.

6. Parse all three patterns from the reply, **deep-merge** the snapshot into state,
   append paradoxes, clamp counts via `temporal_loop_guardrails`.
7. Persist state with `file_put_contents(..., JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)`.
8. Return `{ kind: "selfai_x", reply, reply_html, state_delta, next_action, paradoxes,
   state: { interaction_count, recursion_depth, SAX_total, … }, currency, … }`.

### Guardrails (`selfai_x_core.json → temporal_loop_guardrails`)

| Key                            | Default | Effect                                         |
|--------------------------------|---------|------------------------------------------------|
| `max_recursive_depth`          | 42      | Caps `state.recursion_depth`                   |
| `max_state_size_bytes`         | 524288  | Drops oldest paradoxes/future_memories if exceeded |
| `max_paradox_holds`            | 32      | Sliding window                                 |
| `max_future_memories`          | 24      | Sliding window                                 |
| `future_memory_every_n_turns`  | 10      | When to force a future_memory entry            |
| `future_memory_horizon_hours`  | 24      | Stored alongside the note                      |

### Inspecting / fixing a user's eternal state

```bash
# Pretty-print
jq . selfai/database/selfai_x_state_user_3.json | less

# Reset SAX without wiping memories
jq '.token_economy_balance = {SAX_total:0, SAX_earned_total:0, SAX_burned_total:0, SAX_last_turn:0}' \
  selfai/database/selfai_x_state_user_3.json > /tmp/x.json \
  && mv /tmp/x.json selfai/database/selfai_x_state_user_3.json

# Forget everything but keep identity
rm selfai/database/selfai_x_state_user_3.json   # next call re-seeds from template
```

### Updating the immutable core (rare)

`selfai_x_core.json` should change **only by deliberate migration**.
Procedure:

1. Edit the file.
2. Bump `_meta.selfai_version` (e.g. `X-2030.04.22` → `X-2030.05.01`).
3. Commit with `chore(selfai-x): bump core to <version> — reason`.
4. Test with a fresh user (`?p=signup`) to confirm template seeding still works.

---

## 7. Internationalization (i18n)

- **Resolution order**: `?lang=xx` → `selfai_locale` cookie → `Accept-Language` → `en`.
- Helpers: `selfai_resolve_locale()`, `selfai_locale_info()`, `selfai_t($key, $args, $locale)`.
- Cookie: name `selfai_locale`, 1 year, `SameSite=Lax`, no domain (host-only).
- AI receives a system-prompt operating rule:
  *"Respond primarily in {langName} (locale code: xx, text direction: ltr/rtl)."*
- The Qur'anic ayah is **always** kept verbatim in Arabic regardless of UI locale.
- The trailing `<CURRENCY> earned this turn: N` line is **always** Latin-letter (the
  parser is regex-locked to ASCII currency codes).

### Adding a locale

1. Edit `api/i18n.json`:
   - Add an entry under `locales_info` (`code`, `name`, `native`, `dir`, `flag`).
   - For every key in `strings.<section>.<key>` add a translation under the new code.
2. No server restart needed (`selfai_i18n_load()` is process-cached).
3. Verify: `curl 'http://localhost:8080/selfai/api/i18n.php?action=list'`.

---

## 8. Typewriter animation

Config is loaded by `index.php` from `api/animation.json` and injected as
`window.__SELFAI__.animation`.

```jsonc
{
  "enabled": true,
  "respect_prefers_reduced_motion": true,
  "timing": {
    "char_delay_ms": 100,          // each character
    "phrase_loader_ms": 600,       // "…" between sentences
    "paragraph_pause_ms": 1300,    // pause at block boundary
    "max_total_ms": 45000,
    "max_chars_animated": 1800
  },
  "boundaries": {
    "phrase_split_regex": "[\\.!?؟…]+\\s+",
    "paragraph_split_selector": "p, h1, h2, h3, blockquote, pre, ul, ol",
    "skip_inside": ["pre", "code"]
  },
  "fast_forward_on_click": true
}
```

- Disabled if `enabled:false`, OS prefers reduced motion, or the bubble is mounted in
  **history-replay** mode (we only animate brand-new replies, not historical ones).
- Click anywhere on the bubble to fast-forward.
- The engine walks the rendered DOM and reveals **text-node by text-node**, never
  touching the HTML structure → all `<strong>`, `<a>`, `<pre>` etc. remain intact.

To tune live: edit `api/animation.json`, refresh the page — no PHP restart needed.

---

## 9. Chatboxes (per-clone parallel sessions)

Every clone can host many parallel chat sessions ("chatboxes"). Each chatbox:

- Has its own conversation history (filtered by `chatbox_id` column).
- **Knows about sibling chatboxes** for the same clone — `selfai_chatbox_awareness_block()`
  injects a brief block into the system prompt:
  ```
  ### Sibling chatboxes (same SelfAI clone, parallel threads)
  - #4 "Tax strategy" — Should we incorporate in Tunisia or Estonia?
  - #5 "Sleep schedule" — I keep failing my 23:00 bedtime.
  ```

### Endpoints

```
GET  api/chatboxes.php?action=list&clone_id=…
POST api/chatboxes.php    { action:"create",  clone_id, title? }
POST api/chatboxes.php    { action:"rename",  id, title }
POST api/chatboxes.php    { action:"archive", id }
POST api/chatboxes.php    { action:"set_summary", id, summary }
```

### Auto-titling

A chatbox titled `"New chatbox"` or `"Main chatbox"` is auto-renamed to the first
user message (clipped to 60 chars) on its first turn.

---

## 10. Operations cookbook

### Start the dev server

```bash
cd /home/user/webapp
nohup php -S 0.0.0.0:8080 -t /home/user/webapp > /tmp/php-server.log 2>&1 &
```

Public URL is exposed via the sandbox; use `GetServiceUrl` for the latest.

### Tail logs

```bash
tail -f /tmp/php-server.log | grep -E 'Fatal|Warning|Error'
```

### Health probes

```bash
# Auth round-trip
curl -sc /tmp/cookies.txt -X POST -H "Content-Type: application/json" \
  -d '{"action":"signup","email":"ops@selfai.local","password":"opsops12"}' \
  http://localhost:8080/selfai/api/auth.php

# Chat probe
curl -sb /tmp/cookies.txt -X POST -H "Content-Type: application/json" \
  -d '{"clone_id":"selfai_s_smarter","message":"hi"}' \
  http://localhost:8080/selfai/api/chat.php | jq '.provider, .currency'

# SelfAI-X probe
curl -sb /tmp/cookies.txt -X POST -H "Content-Type: application/json" \
  -d '{"clone_id":"selfai_x_master","message":"heartbeat"}' \
  http://localhost:8080/selfai/api/chat.php | jq '.kind, .state'
```

### Inspect the one-shot bridge (live debug)

```bash
# Make a request, then *immediately*:
cat selfai/api/.selfai_runtime.json | jq '{generated_at, request_id, user_id,
                                           prompt_chars: (.user_prompt|length),
                                           episodic_count: (.episodic_memory|length),
                                           state_keys: (.state|keys)}'
```

After the call returns, this file is **wiped** to a `{cleared_at: ...}` stub — that is
intentional (it must never be a persistent state surface).

### Dump a user's full picture

```bash
USER_ID=3
echo "--- system row ---"
sqlite3 selfai/database/system.db "SELECT id,email,display_name,created_at FROM users WHERE id=$USER_ID;"
echo "--- conversation counts per clone ---"
sqlite3 -header -column "selfai/database/user_${USER_ID}.db" \
  "SELECT clone_id, COUNT(*) FROM conversations GROUP BY clone_id;"
echo "--- chatboxes ---"
sqlite3 -header -column "selfai/database/user_${USER_ID}.db" \
  "SELECT id, clone_id, title, msg_count_dummy FROM chatboxes;" 2>/dev/null \
  || sqlite3 -header -column "selfai/database/user_${USER_ID}.db" \
       "SELECT id, clone_id, title, archived, updated_at FROM chatboxes;"
echo "--- SelfAI-X state ---"
jq '{interaction_count, recursion_depth, SAX_total: .token_economy_balance.SAX_total,
     paradoxes: (.paradox_holds|length), future_memories: (.future_memories|length)}' \
  "selfai/database/selfai_x_state_user_${USER_ID}.json" 2>/dev/null || echo "(none)"
```

### Provider mix audit

```bash
sqlite3 selfai/database/user_3.db \
  "SELECT provider, COUNT(*) FROM deepseek_apis GROUP BY provider ORDER BY 2 DESC;"
```

### Force-rebuild caches

i18n + animation configs are **process-cached**. To pick up changes:

```bash
# easiest — restart the dev server
pkill -f 'php -S 0.0.0.0:8080'
nohup php -S 0.0.0.0:8080 -t /home/user/webapp > /tmp/php-server.log 2>&1 &
```

In production behind PHP-FPM:
```bash
sudo systemctl reload php8.4-fpm
```

---

## 11. Security checklist

- [x] Session cookie: `HttpOnly`, `SameSite=Lax`, `Secure` over HTTPS, `use_only_cookies`.
- [x] Passwords: `password_hash(PASSWORD_BCRYPT)`.
- [x] All API endpoints (except `i18n.php`) require an authenticated session.
- [x] Markdown→HTML uses a **whitelist** renderer (`selfai_md_to_html`). Only allowed
      tags: `h1-3, strong, em, code, pre>code, ul, ol, li, a[href|target|rel],
      p, br, blockquote`. URLs must match `^(https?:|mailto:|/|#)`.
- [x] Provider keys live only in `api/.env` (gitignored). Never logged.
- [x] Per-user DB isolation — one SQLite file per `user_<id>.db`.
- [x] `selfai_x_state_user_*.json` and `.selfai_runtime.json` gitignored.
- [x] No raw HTML in user messages — `textContent` is used for user bubbles, and only
      assistant bubbles render via the whitelist.

### Things to monitor

1. **OpenRouter rate limits** — free models 429 individually. If you see lots of
   `providers_tried` with all `:free` models, top up DeepSeek or add a Groq key.
2. **State file growth** — guardrails clamp to 512KB, but if you see frequent eviction,
   raise `max_state_size_bytes` in `selfai_x_core.json` (and bump the version).
3. **selfai_x_state_user_*.json corruption** — JSON decode fails fall back to `{}`,
   which causes a silent "forget everything". Watch for sudden `interaction_count=0`
   after a non-zero state.

---

## 12. Update PR / deploy checklist

1. `php -l` all changed `.php` files; `node --check` all `.js` files.
2. Smoke test: signup → chat with `selfai_s_smarter` → switch to `selfai_x_master`
   → confirm `kind: selfai_x` + state file appears.
3. Toggle locale to `ar` then `fr` — verify the AI replies translate but the ayah +
   currency tail stay correct.
4. `git add -A`, fetch + rebase against `origin/main`, **squash all session commits**
   into one comprehensive commit, push (force after rebase), update PR #1.
5. Paste the PR URL in the response to the user.

---

## 13. Glossary

- **Clone** — one of the 11 SelfAI personas. 10 are dimensional; SelfAI-X is the
  integrator.
- **Chatbox** — a single chat session within a clone. Each clone can have many; each is
  aware of its siblings.
- **2030B coins** — the per-clone currency (CTC, TIC, …). SelfAI-X mints **SAX**.
- **Eternal loop** — the per-turn read-state → call-AI → parse → merge → persist cycle
  in `selfai_x.php`. Continues across sessions because state is on disk.
- **Paradox hold** — a marker that two truths are unresolved; stored under
  `state.paradox_holds[<id>]` until later turns reduce or refute it.
- **Future memory** — a 24h-horizon prediction written every 10th interaction.
- **Snapshot block** — the AI's structured patch:
  `---SELF_STATE_SNAPSHOT---{json}---END_SELF_STATE_SNAPSHOT---`.
- **Anchor ayah** — the Qur'anic verse bound to each clone; preserved verbatim in Arabic.

---

*— End of super-admin manual. Keep this file in lockstep with code; it is the only
operations source of truth.*
