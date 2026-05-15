# W5D — AI Workspace Marketing Site

A production-quality, multi-page marketing site for the **W5D** brand, built fully in **HTML + Tailwind CSS (CDN) + vanilla JS**. Three distinct homepage variants, a complete inner-pages library, working forms, dark/light mode, and a reusable Blocks library.

> **Original brand**: All copy, logos and visuals are original to W5D — no third-party brand, asset or markup is reused.

---

## ✅ Currently completed features

### Pages (3 homes + 14 inner pages)
| Path | Purpose |
|---|---|
| `index.html` | **Home 01** — Classic SaaS, centered hero, animated gradient orbs |
| `home-2.html` | **Home 02** — Product-led, split hero with mockup, bento features |
| `home-3.html` | **Home 03** — Editorial, bold display type, manifesto + marquee |
| `pages/about.html` | Story, mission, values, team, milestones |
| `pages/pricing.html` | 3 plans + monthly/annual toggle, comparison table, add-ons, FAQ |
| `pages/features.html` | Deep feature index with anchors (#agents, #knowledge, #workflows, #api…) |
| `pages/integrations.html` | 24+ logo grid, categories, “request integration” |
| `pages/contact.html` | **Wired contact form** + **wired demo-request form** |
| `pages/blog.html` | Blog index with categories and newsletter signup |
| `pages/blog-post.html` | Long-form article template |
| `pages/careers.html` | Roles + **wired application form** |
| `pages/changelog.html` | Versioned release timeline |
| `pages/security.html` | SOC 2, GDPR, sub-processors, controls |
| `pages/privacy.html` | Privacy policy |
| `pages/terms.html` | Terms of service |
| `pages/404.html` | Friendly not-found |
| `pages/blocks.html` | **Blocks library** — 30+ ready-to-copy sections |
| `pages/heros.html` | **Heroes library** — 12 animated hero variants |
| `pages/sections.html` | **Sections library** — 28 animated content sections |
| `pages/documentations.html` | **Developer docs** — full kit reuse + rebrand guide |

### Shared infrastructure
- **Mega-menu** (`js/megamenu.js`) — Product / Solutions / Resources / Pages, each with two-column item grid + featured promo card. Hover- and click-open with a 120ms close-delay and an internal padding bridge so the cursor can travel from the trigger to the panel without the menu disappearing. Keyboard-accessible (`aria-haspopup`, `aria-expanded`, focus-within open, Escape closes). Mobile accordion auto-closes on link tap.
- **Shell renderer** (`js/shell.js`) — Auto-injects the **same nav + footer** into every inner page from a single source of truth.
- **Theme system** (`js/main.js`) — Dark mode default. Respects `prefers-color-scheme`, persists in `localStorage` (`w5d-theme`), sun/moon toggle on every navbar.
- **Forms wiring** (`js/api.js`) — Newsletter, contact, demo, and careers forms all post to the Table API with inline success/error states and busy/disabled buttons.
- **Logos** (`js/logos.js`) — Inline SVG wordmarks for partner logo strips.
- **Custom CSS** (`css/style.css`) — Glass surfaces, gradient orbs, grid-bg, marquee, gradient borders.

### Forms (wired to RESTful Table API)
| Form | Selector | Table |
|---|---|---|
| Newsletter (footer + blog + blocks) | `form[data-newsletter]` | `subscribers` |
| Contact (`/pages/contact.html`) | `form[data-contact]` | `contact_messages` |
| Demo request (`/pages/contact.html`) | `form[data-demo]` | `demo_requests` |
| Careers application (`/pages/careers.html`) | `form[data-careers]` | `careers_applications` |

### Animation library (in `css/style.css` + `js/main.js`)
Drop-in utilities used across the heroes/sections libraries:
- Backdrops: `aurora`, `conic-spin`, `stars`, `mesh-bg`, `beam`, `noise`, `grid-bg`, `orb orb-1/2/3`
- Surfaces: `glass`, `grad-border`, `gradient-text`, `gradient-shift`, `wave-text`
- Motion: `tilt`, `shine`, `float-y`, `breath`, `bounce-y`, `pulse-ring`, `typing-dot`
- Marquees: `marquee`, `marquee-y`, `headline-marquee`
- Scroll reveal: `data-rv="up|down|left|right|zoom|rotate"` (+ `.stagger` parent)
- Cursor follow: `.spotlight` (uses `--mx`/`--my` set by `main.js`)
- Tabs: `[data-tabs]` + `[data-tab]` triggers + `[data-tab-panel]` panes
- Copy buttons: `[data-copy="#selector"]`
- All animations honour `prefers-reduced-motion`.

### Tested
All seven critical pages (`index.html`, `pages/contact.html`, `pages/pricing.html`, `pages/blocks.html`, `pages/heros.html`, `pages/sections.html`, `pages/documentations.html`) render with **zero JS errors** in headless Chromium. The only console message is the Tailwind CDN production warning, which is expected and harmless for a static demo.

### 🆕 Mega-menu position fix (W5D, 2030B, WebBook, Ontology)
The hidden desktop fragment of every mega-menu now uses `left-1/2 -translate-x-1/2` (instead of `left-0 right-0 mx-auto`) plus a viewport-aware `max-w` so the panel is always centred under its trigger and never clips on narrow desktops. Same fix has been applied to `js/megamenu.js`, `2030b-ecosystem/js/megamenu.js`, `webbook-saas/js/megamenu.js`, and `ontology/js/megamenu.js`.

### 🆕 WebBook reader page
- `/pages/read-webbook.html` — Responsive left-fixed aside (TOC, progress, reading mode, AI companion, quick actions) + iframe to `https://2030b.com/webbook/webbook-v6.html`. Five additional variants live under `/webbook-saas/pages/` (`v7` minimal · `v8` immersive · `v9` classroom · `v10` cinematic · `v11` focus), and Ontology hosts its own variants (`v2`–`v6`) in `/ontology/pages/`.

---

## 🔗 Functional entry URIs

### Public pages
- `/index.html` — Home 01
- `/home-2.html` — Home 02
- `/home-3.html` — Home 03
- `/pages/about.html`
- `/pages/pricing.html`
- `/pages/features.html` (`#agents`, `#knowledge`, `#workflows`, `#api`, `#marketing`, `#engineering`, `#operations`, `#support`)
- `/pages/integrations.html`
- `/pages/contact.html` (`#demo` for the demo form)
- `/pages/blog.html`, `/pages/blog-post.html`
- `/pages/careers.html`
- `/pages/changelog.html`
- `/pages/security.html`, `/pages/privacy.html`, `/pages/terms.html`
- `/pages/404.html`
- `/pages/blocks.html` — Blocks library
- `/pages/heros.html` — Heroes library (12 hero sections)
- `/pages/sections.html` — Sections library (28 content sections)
- `/pages/documentations.html` — Developer documentation

### REST endpoints (RESTful Table API)
| Endpoint | Used by |
|---|---|
| `POST /tables/subscribers` `{email, source_page, status}` | Newsletter forms |
| `POST /tables/contact_messages` `{name, email, company, topic, message, status}` | Contact form |
| `POST /tables/demo_requests` `{name, work_email, company, team_size, use_case, status}` | Demo form |
| `POST /tables/careers_applications` `{name, email, role, portfolio_url, cover_letter, status}` | Careers form |
| `GET /tables/{table}` (admin/data export) | All four tables |

---

## 🗄️ Data models (Table API schemas)

### `subscribers`
`id, email (text), source_page (text), status (text — active/unsubscribed)`

### `contact_messages`
`id, name (text), email (text), company (text), topic (text — sales/support/partnership/press/other), message (rich_text), status (text — new/read/replied)`

### `demo_requests`
`id, name (text), work_email (text), company (text), team_size (text — 1-10/11-50/51-200/201-1000/1000+), use_case (rich_text), status (text)`

### `careers_applications`
`id, name (text), email (text), role (text), portfolio_url (text), cover_letter (rich_text), status (text)`

---

## 🎨 Design tokens

- **Brand**: indigo → cyan gradient (`from-brand-500 via-indigo-500 to-cyan-400`)
- **Ink (dark)**: custom `ink-900` / `ink-950`
- **Display font**: `Space Grotesk`  •  **Body font**: `Inter`
- **Surfaces**: glass with backdrop blur, gradient borders, animated grid backgrounds, gradient orb blooms
- **Icons**: Lucide via UMD
- **Motion**: AOS-style scroll reveals (custom IntersectionObserver fallback in `js/main.js`)

---

## 📁 File structure
```
index.html                   home-2.html              home-3.html
README.md
css/style.css
js/
  ├── main.js          (theme, mobile nav, FAQ, pricing toggle, counters)
  ├── shell.js         (auto-injects nav + footer on inner pages)
  ├── megamenu.js      (mega-menu data + render)
  ├── api.js           (form wiring → Table API)
  └── logos.js         (inline SVG partner wordmarks)
partials/_head.html
pages/
  ├── about.html         pricing.html         features.html
  ├── integrations.html  contact.html         blog.html
  ├── blog-post.html     careers.html         changelog.html
  ├── security.html      privacy.html         terms.html
  ├── 404.html           blocks.html
```

---

## 🚧 Features not yet implemented

- Real backend email delivery (forms only persist to Table API; no SMTP/transactional email)
- Authenticated admin/inbox UI for reading the Table data
- True i18n / multi-language toggle
- Dynamic blog (currently file-based)
- A/B testing or analytics integration
- Cookie-consent banner
- Sitemap.xml / robots.txt / OG-image generation per page

---

## ⏭️ Recommended next steps

1. **Plug in transactional email** — webhook the Table API (`subscribers`, `contact_messages`, `demo_requests`, `careers_applications`) into Resend/Postmark/SES so the team is notified.
2. **Build an `/admin/inbox.html`** that lists Table API rows with filters + status updates (PATCH `/tables/{table}/{id}`).
3. **Convert blog to data-driven** — define a `posts` table (slug, title, excerpt, body, hero, tags, published_at) and have `pages/blog.html` + `pages/blog-post.html` render from it.
4. **Compile Tailwind via CLI** to remove the production CDN warning and shrink CSS to ~20 KB.
5. **Add OG/Twitter meta + per-page `<title>`** (currently uses brand defaults).
6. **Hook analytics** (Plausible/Umami) and conversion goals on each form.

---

## 🧪 Local preview

Open `index.html` directly in a browser. Forms require the project's RESTful Table API to be reachable at relative `tables/...` URLs (already provided in this environment). No build step required.

---

## 🆕 Final-pass UX upgrades (latest)

### Navbar
- **Brand-ID text hidden on desktop** (`>=1024px`) via `[data-brand-id-hide]` attribute + CSS rule.
- **Active-state highlighting** on top-level mega-menu triggers and inner sub-menu links (matched against `location.pathname`).
- **User-profile toggle** + **Language toggle** in the header — both open animated slide-in aside panels (`#w5d-aside-user`, `#w5d-aside-lang`) with backdrop, Escape-to-close, and `localStorage` persistence for language (RTL auto-applied for Arabic).
- Animated nav-icon buttons with hover-lift and pulsing-ring animation (respects `prefers-reduced-motion`).

### Animated logo page loader
- A page loader with the W5 mark, gradient sliding text and animated bar is injected **synchronously and very early** (boot section in `js/logos.js` runs from `<head>`), so it appears **before** the page paints — not after.
- Hides on `window.load` + 400 ms; hard 6-second safety timeout.

### Mega-menu (race-condition fix)
- `js/megamenu.js` now retries the build up to 20× and **also rebuilds on the `shell:rendered` event** dispatched by `js/shell.js`, so the menu appears reliably on every page regardless of script-load order.
- The same fix is applied across the W5D root, `2030b-ecosystem/`, and `webbook-saas/` sites.

### Animated SVG icons / logos
- Logos & icons rendered through the brand JS libraries (`window.WBLogos`, `window.B30Logos`) get a `wb-svg-anim` / `b30-svg-anim` class plus stable animation hooks (`data-page-flip`, `data-spine-pulse`, etc.).
- All animations honour `prefers-reduced-motion`.

### Sister sites in this project
- **2030B Ecosystem** → `/2030b-ecosystem/` (10 projects, 30 books, 10 currencies).
- **WebBook SaaS** → `/webbook-saas/` (author-first SaaS, AI-generated WebBooks via the WebBook Agent, 40 inner pages, dedicated `pages/webbook-agent.html`).

### Bugs fixed in this pass
| Bug | Root cause | Fix |
| --- | ---------- | --- |
| Loader appears **after** page paints | `enhancements.js` was loaded async on `DOMContentLoaded` | Boot loader CSS + DOM moved into the head-early `js/logos.js` |
| Mega-menu hidden on many pages | `megamenu.js` ran before `shell.js` injected the host element | Retry loop (20×) + `shell:rendered` event listener |
| Console errors on animated SVGs | CSS targeted classes that JS did not produce | Stable `data-*` hooks + matching CSS rules added |

### Verification
Playwright console capture run across all three sites (W5D root, 2030B Ecosystem, WebBook SaaS) on both top-level and deep inner pages: only the harmless **Tailwind CDN production warning** remains; **no JavaScript errors**, and the `[data-mega-host] [data-mega-trigger]` selector resolves on every page tested.

---

## 🆕 2030B Entry-Point Site (`/2030b-site/`) — © 2026 Maher

A brand-new, self-contained sub-site that serves as the **public entry point** to the **2030B Organizational Departments Registry** — a coordinated stack of **sixteen departments** responsible for the critical infrastructure of being, knowing, ethics, time, memory, and survival. Authored, designed, and copyrighted by **Maher**.

### Design fixes addressed in this pass

| Issue raised | Resolution |
| --- | --- |
| **SVG logo should be animated** | New `2030b-site/js/logos.js` ships an animated compass-mark SVG (`master()`) with three counter-rotating rings, eight breathing rays, and a glowing core, all driven by stable `data-b-ring` / `data-b-ray` / `data-b-core` hooks paired with matching CSS keyframes (`bLogoSpin`, `bLogoRay`, `bLogoCore`, `bLogoFloat`). Used in the navbar, in the loader, on the About page, on the 404 page, and inline anywhere via `<span data-b-logo="master" data-b-size="…"></span>`. |
| **Loader must cover the whole page, hide content, then load and animate (no blur effect)** | The same `logos.js` injects a `html.b-booting body { visibility:hidden }` rule and an opaque full-screen `#b-loader` (z-index `2147483647`, solid `#04030a` background, **no `backdrop-filter` / no blur**). Reveal happens on `DOMContentLoaded + 250 ms`, with a hard 2.5 s cap. Honours `prefers-reduced-motion`. |
| **WebBook reader: iframe src is hidden** | The reader now renders the iframe URL **visibly** in two places: a monospace source bar above the frame, and a tools-tab readout in the aside, plus a one-click "Open source in new tab" link. |
| **WebBook reader: different variants require different layouts** | Six layout variants are now shipped (see table below) — each truly different in shape, not just colour. |
| **The aside may contain fixed navigation tabs** | Default reader's left aside has a sticky four-tab strip (**Contents · Info · Related · Tools**) that stays pinned while the body scrolls. |

### Pages (entry point + 16 departments + 6 reader variants)

| Path | Purpose |
|---|---|
| `2030b-site/index.html` | **Entry point** — animated compass logo, hero, levels legend, the sixteen-grid, WebBook strip, About-Maher block. |
| `2030b-site/pages/departments.html` | All sixteen department cards with priority-level filter (All / Critical / High / Medium / Standard / Low). |
| `2030b-site/pages/registry.html` | The canonical short-table from the official registry — five-detail mandate per department. |
| `2030b-site/pages/levels.html` | The five priority levels explained, with departments grouped by level. |
| `2030b-site/pages/standard.html` | The WebBook standard — the eight required sections of every department's WebBook. |
| `2030b-site/pages/about.html` | About Maher — founder, framework, anchor (Al-Ḥaqq). |
| `2030b-site/pages/contact.html` | Wired contact form → `tables/contact_messages`. |
| `2030b-site/pages/copyright.html` | Full copyright notice — what may and may not be done with this work. |
| `2030b-site/pages/department.html` | **Generic department template** — picks `?slug=…` from the URL and renders identity, full description, foundations, boundaries, five-detail mandate sidebar, related departments. |
| `2030b-site/pages/dept-{slug}.html` × 16 | One per department, each redirects to `department.html?slug=…`. Slugs: `ecosystem`, `ontology`, `csl`, `quantum-ethics`, `temporal-architecture`, `memetic-engineering`, `synthetic-empathy`, `dimensional-cartography`, `collective-memory`, `neural-sovereignty`, `existential-risk`, `interspecies-communication`, `reality-verification`, `post-biological`, `cosmic-heritage`, `paradox-resolution`. |
| `2030b-site/pages/404.html` | Not-found with the animated logo. |

### WebBook reader — six truly different layouts

| Variant | Path | Layout shape | Aside style |
|---|---|---|---|
| **v1 · Default** | `read-webbook.html` | Sticky 300 px **left aside** + scrolling main with iframe | **Fixed 4-tab strip** (Contents / Info / Related / Tools) |
| **v2 · Focus** | `read-webbook-v2.html` | No aside — minimal **horizontal department-tab bar** at top, iframe takes ~95 % | Top tab strip, all 16 depts inline scrollable |
| **v3 · Wide** | `read-webbook-v3.html` | Iframe on **left**, information-rich **420 px aside on the right** | Right-side fixed 4-tab strip (Overview / Mandate / All 16 / Meta) |
| **v4 · Split** | `read-webbook-v4.html` | **Side-by-side comparison** — two iframes, each with its own department selector | No aside; per-pane `<select>` headers |
| **v5 · Tabbed** | `read-webbook-v5.html` | **Browser-style multi-tab** — open many WebBooks as tabs, switch with a click, ✕ to close, ＋ to add | Tab bar across the top |
| **v6 · Theatre** | `read-webbook-v6.html` | **Cinematic centred card** with traffic-light bar, dimmed audience around it | Bottom pill-rail with all 16 departments |

In all six variants the **iframe source URL is visible** (no longer hidden), and each variant cross-links to all the others through a top-right version chip strip.

### Animated SVG logo — usage

```html
<!-- Inline (auto-renders on DOMContentLoaded and on 'b:shell') -->
<span data-b-logo="master" data-b-size="80"></span>
<span data-b-logo="wordmark"></span>
<span data-b-dept="ontology" data-b-size="36"></span>

<!-- Or programmatically -->
<script>document.body.innerHTML += B_LOGOS.master(140);</script>
```

### Loader contract

```js
// Boots automatically the moment logos.js evaluates:
document.documentElement.classList.add('b-booting');   // hides body via CSS
//   ↓ DOMContentLoaded + 250 ms (or hard cap 2.5 s)
B_LOGOS.reveal();                                       // removes class, fades #b-loader
document.dispatchEvent(new CustomEvent('b:revealed'));  // hook for downstream code
```

### Departments registry (`2030b-site/js/registry.js`)

Single source of truth. Each entry: `{ slug, name, level, icon, color, short, full, details[5] }`. Levels are **Critical (5)**, **High (4)**, **Medium (3)**, **Standard (2)**, **Low (2)** — totalling **sixteen** departments exactly as in the official Registry by Maher.

### Functional URIs (entry-point site)

- `/2030b-site/index.html`
- `/2030b-site/pages/departments.html`
- `/2030b-site/pages/registry.html`
- `/2030b-site/pages/levels.html`
- `/2030b-site/pages/standard.html`
- `/2030b-site/pages/about.html`
- `/2030b-site/pages/contact.html` (POST → `tables/contact_messages`)
- `/2030b-site/pages/copyright.html`
- `/2030b-site/pages/department.html?slug={slug}` — generic charter
- `/2030b-site/pages/dept-{slug}.html` × 16 — pretty URLs (redirect to the generic template)
- `/2030b-site/pages/read-webbook.html?dept={slug}` — v1 reader (default)
- `/2030b-site/pages/read-webbook-v2.html?dept={slug}` — v2 Focus
- `/2030b-site/pages/read-webbook-v3.html?dept={slug}` — v3 Wide
- `/2030b-site/pages/read-webbook-v4.html?a={slug}&b={slug}` — v4 Split
- `/2030b-site/pages/read-webbook-v5.html?depts=slug,slug,slug` — v5 Tabbed
- `/2030b-site/pages/read-webbook-v6.html?dept={slug}` — v6 Theatre
- `/2030b-site/pages/404.html`

### File structure (`2030b-site/`)
```
2030b-site/
├── index.html
├── css/
│   └── style.css                 (design tokens, surfaces, dep-cards, reveal-on-scroll — no blur)
├── js/
│   ├── logos.js                  (animated compass SVG + opaque page loader)
│   ├── registry.js               (the 16-department single source of truth)
│   ├── shell.js                  (nav + footer renderer with logo slot)
│   └── main.js                   (cards, filters, reveals, mobile menu)
└── pages/
    ├── departments.html  registry.html  levels.html  standard.html
    ├── about.html        contact.html   copyright.html  404.html
    ├── department.html                  (generic charter template)
    ├── dept-ecosystem.html  …  dept-paradox-resolution.html   (16 pretty URLs)
    └── read-webbook.html       read-webbook-v2.html  …  read-webbook-v6.html
```

### Verification
Playwright console-capture on `2030b-site/index.html`, `pages/departments.html`, `pages/department.html`, and `pages/read-webbook.html` returns **zero JavaScript errors** — only the harmless Tailwind-CDN production warning. The reader successfully embeds `https://2030b.com/webbook/webbook-v6.html` and reports `161 slides | 6/6 files loaded`.

### Copyright

> © 2026 **Maher**. The 2030B framework, the registry of sixteen departments, the WebBook standard, and all writing, naming, structure, and design on this site are the original intellectual work of Maher. **All rights reserved.**

This notice is reflected in the footer of every page, in `pages/copyright.html`, and in the source-file headers of `js/logos.js`, `js/registry.js`, `js/shell.js`, `js/main.js`, and `css/style.css`.

---

## 🆕 Latest pass — four adjustments

| # | Adjustment | Resolution |
|---|---|---|
| 1 | **2030B Entry-Point logo as a 4-cell matrix** | New `B_LOGOS.square()` renders a **2×2 matrix**: `┌ 2 │ 0 ┐` on top, `└ 3 │ 0 ┘` on bottom. The two `0`s in the right column share an animated **vertical "spine bar"** so the right half visually reads as a stylised **B** — making the whole mark spell "2030B" through composition. Each cell pulses in sequence (`bCellPulse`), each glyph breathes (`bGlyphBreath`), the spine shines (`bBridgeShine`), and an outer dashed frame slowly rotates (`bFrameRot`). |
| 2 | **Rectangular logo for any department** | New `B_LOGOS.rect({ size, slug, label, sub, color })` produces a **wordmark variant**: a miniature 2×2 mark on the left + a coloured wordmark + an animated underline bar — used as the "any department" rectangular signature. Auto-renders from `<span data-b-logo="rect" data-b-slug="ontology" data-b-label="Ontology" data-b-color="#d4a857"></span>`. |
| 3 | **Ontology animated SVG + loader + post-load animations** | Two-stage handoff: <br>• `html.onto-booting` keeps body content invisible while the loader runs its **own** keyframes (`ontoSpin/ontoRing/ontoRay/ontoBreath`). <br>• On `onto:revealed`, `html.onto-revealed` is added — only **then** do the gated CSS rules `html.onto-revealed [data-onto-anim][data-anim-in]` and the page-logo animations (`ontoLogoRing/Ray/Core/Float`) fire. <br>• `kickLogoAnimations()` forces a reflow so any animation that was suspended during the loader's lifetime restarts cleanly. <br>• `animation-play-state: running !important` on every animated SVG hook protects against pause-on-hidden quirks. |
| 4 | **2030B Entry-Point animated SVG + loader + post-load animations** | Same architectural pattern as Ontology, but with the new matrix logo: <br>• `html.b-booting body { visibility: hidden }` while `#b-loader` is on top (opaque `#04030a`, **no blur**). <br>• Reveal flips `html.b-booting → html.b-revealed`, dispatches `b:revealed`. <br>• `js/main.js` now waits for `b:revealed` before starting `IntersectionObserver` — so post-load reveals fire **after** the loader fades. <br>• CSS is gated on `html.b-revealed [data-b-anim][data-b-in]` so even already-tagged elements stay invisible until the handoff. <br>• Hard-cap fallbacks at 2.6 s (loader) and 3.2 s (reveal) so the page can never get stuck. |
| 5 | **Mega-menu with animated icons (2030B Entry-Point)** | New `2030b-site/js/megamenu.js`. Four top-level triggers — **Departments · Levels · WebBook · Project** — each opens a hidden desktop panel (`left:50% / translate-x:-50%`, `max-width: min(960px, calc(100vw - 2rem))`, never clips). Every menu item has a **`.b-mm-glyph`** with: <br>• an idle scale-pulse (`bMmIdle`, 4 s) so icons subtly breathe; <br>• a hover conic-gradient halo (`bMmSpin`, 4.5 s) that fades in only on hover; <br>• a hover glyph rotation/scale on the inner Lucide icon. <br>Mobile drawer renders the same data as collapsible `<details>` accordions. |

### New entry-point logo placeholders

```html
<!-- Square 2x2 matrix logo (default) -->
<span data-b-logo="square" data-b-size="160"></span>

<!-- Rectangular department wordmark -->
<span data-b-logo="rect"
      data-b-size="240"
      data-b-slug="ontology"
      data-b-label="Ontology"
      data-b-sub="2030B Department"
      data-b-color="#d4a857"></span>

<!-- Small department glyph (mini matrix in dept colour) -->
<span data-b-dept="quantum-ethics" data-b-size="36"></span>
```

### New mega-menu mount points

`js/shell.js` now renders two empty hosts that `js/megamenu.js` automatically populates:

```html
<div data-b-megamenu></div>            <!-- desktop -->
<div data-b-megamenu-mobile></div>     <!-- mobile drawer -->
```

### Verification (this pass)

Playwright console-capture run on:
- `2030b-site/index.html` — **zero JS errors**
- `2030b-site/pages/departments.html` — **zero JS errors**
- `2030b-site/pages/read-webbook.html` — **zero JS errors** (WebBook v6 reports `161 slides | 6/6 files`)
- `ontology/index.html` — **zero JS errors**

Only the harmless Tailwind-CDN production warning is logged. Mega-menu mounts on every entry-point page; logo animations begin during the loader and continue cleanly after the reveal handoff on both Ontology and 2030B Entry-Point sites.
