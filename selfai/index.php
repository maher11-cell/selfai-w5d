<?php
/** SelfAI — Front controller. Serves auth, dashboard, chat, docs. */
require __DIR__ . '/includes/db.php';
session_name('selfai_sid');
session_start();

$page = $_GET['p'] ?? 'home';
$user = selfai_current_user();

function selfai_layout(string $title, string $body, ?array $user = null): void {
    $brand    = selfai_config()['brand'];
    $bodySafe = $body;
    $userJson = $user ? json_encode(['id' => (int)$user['id'], 'email' => $user['email'], 'display_name' => $user['display_name']]) : 'null';

    // i18n bootstrap
    $locale     = selfai_resolve_locale();
    $localeInfo = selfai_locale_info($locale);
    $i18nAll    = selfai_i18n_load();
    $strings    = selfai_translations($locale);
    $dir        = $localeInfo['dir'] ?? 'ltr';
    $locales    = $i18nAll['locales_info'] ?? [];

    // Animation config (typewriter)
    $animCfgPath = __DIR__ . '/api/animation.json';
    $animCfg = is_file($animCfgPath) ? json_decode(file_get_contents($animCfgPath), true) : null;
    if (!is_array($animCfg)) $animCfg = ['enabled' => true, 'timing' => ['char_delay_ms' => 100, 'phrase_loader_ms' => 600, 'paragraph_pause_ms' => 1300]];
    ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale) ?>" dir="<?= htmlspecialchars($dir) ?>" class="dark scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($title) ?> · <?= htmlspecialchars(selfai_t('common.app_name')) ?> · <?= htmlspecialchars(selfai_t('common.ecosystem')) ?></title>
  <meta name="description" content="<?= htmlspecialchars(selfai_t('common.tagline')) ?> · DeepSeek V4 + free AI fallbacks." />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { darkMode: 'class', theme: { extend: {
      colors: { brand: { 50:'#f3f1ff',500:'#8b5cf6',600:'#7c3aed',700:'#6d28d9' },
                ink: { 950:'#070711',900:'#0b0b18',800:'#11111f',700:'#1a1a2e' } },
      fontFamily: { sans:['Inter','system-ui','sans-serif'], display:['"Space Grotesk"','Inter','sans-serif'] }
    }}}
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/selfai.css" />
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
  <script>
    window.__SELFAI__ = {
      user:    <?= $userJson ?>,
      brand:   <?= json_encode($brand, JSON_UNESCAPED_UNICODE) ?>,
      i18n: {
        locale:   <?= json_encode($locale) ?>,
        dir:      <?= json_encode($dir) ?>,
        info:     <?= json_encode($localeInfo, JSON_UNESCAPED_UNICODE) ?>,
        locales:  <?= json_encode($locales, JSON_UNESCAPED_UNICODE) ?>,
        strings:  <?= json_encode($strings, JSON_UNESCAPED_UNICODE) ?>
      },
      animation: <?= json_encode($animCfg, JSON_UNESCAPED_UNICODE) ?>
    };
  </script>
</head>
<body class="bg-ink-950 text-slate-200 min-h-screen flex flex-col" data-locale="<?= htmlspecialchars($locale) ?>" data-dir="<?= htmlspecialchars($dir) ?>">

  <header class="border-b border-white/10 backdrop-blur sticky top-0 z-40 bg-ink-950/70">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex items-center gap-4">
      <a href="index.php" class="flex items-center gap-2">
        <span class="selfai-logo" aria-hidden="true">
          <svg viewBox="0 0 64 64" width="36" height="36">
            <defs>
              <linearGradient id="lgX" x1="0" x2="1" y1="0" y2="1">
                <stop offset="0" stop-color="#8b5cf6"/><stop offset=".5" stop-color="#06b6d4"/><stop offset="1" stop-color="#f59e0b"/>
              </linearGradient>
            </defs>
            <circle cx="32" cy="32" r="28" fill="none" stroke="url(#lgX)" stroke-width="3">
              <animate attributeName="r" values="28;30;28" dur="3s" repeatCount="indefinite"/>
            </circle>
            <path d="M32 12 L48 32 L32 52 L16 32 Z" fill="url(#lgX)" opacity=".8">
              <animateTransform attributeName="transform" type="rotate" from="0 32 32" to="360 32 32" dur="20s" repeatCount="indefinite"/>
            </path>
            <circle cx="32" cy="32" r="4" fill="#fff"/>
          </svg>
        </span>
        <span class="font-display font-bold tracking-tight text-lg text-white">SelfAI</span>
        <span class="hidden sm:inline text-xs px-2 py-0.5 rounded-full bg-brand-500/20 text-brand-50 border border-brand-500/30"><?= htmlspecialchars(selfai_t('common.ecosystem')) ?></span>
      </a>
      <nav class="ml-auto hidden md:flex items-center gap-5 text-sm">
        <a class="hover:text-white" href="index.php"><?= htmlspecialchars(selfai_t('nav.home')) ?></a>
        <a class="hover:text-white" href="index.php?p=dashboard"><?= htmlspecialchars(selfai_t('nav.dashboard')) ?></a>
        <a class="hover:text-white" href="index.php?p=docs"><?= htmlspecialchars(selfai_t('nav.docs')) ?></a>
        <a class="hover:text-white" href="../index.html"><?= htmlspecialchars(selfai_t('nav.w5d')) ?></a>
      </nav>
      <div class="ml-auto md:ml-0 flex items-center gap-2">
        <!-- Language switcher (semantic <details>+<menu>) -->
        <details class="lang-switcher" data-component="lang-switcher">
          <summary class="lang-switcher__btn" aria-label="<?= htmlspecialchars(selfai_t('common.language')) ?>" title="<?= htmlspecialchars(selfai_t('common.language')) ?>">
            <span class="lang-switcher__flag" aria-hidden="true"><?= htmlspecialchars($localeInfo['flag'] ?? '🌐') ?></span>
            <span class="lang-switcher__code"><?= htmlspecialchars(strtoupper($locale)) ?></span>
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
          </summary>
          <menu class="lang-switcher__menu" role="menu">
            <?php foreach ($locales as $code => $info): ?>
              <li role="none">
                <button type="button" role="menuitemradio"
                        aria-checked="<?= $code === $locale ? 'true' : 'false' ?>"
                        class="lang-switcher__item <?= $code === $locale ? 'is-active' : '' ?>"
                        data-lang="<?= htmlspecialchars($code) ?>">
                  <span class="lang-switcher__flag" aria-hidden="true"><?= htmlspecialchars($info['flag'] ?? '') ?></span>
                  <span class="lang-switcher__native"><?= htmlspecialchars($info['native']) ?></span>
                  <span class="lang-switcher__name"><?= htmlspecialchars($info['name']) ?></span>
                </button>
              </li>
            <?php endforeach; ?>
          </menu>
        </details>

        <?php if ($user): ?>
          <span class="text-xs text-slate-400 hidden sm:inline"><?= htmlspecialchars(selfai_t('nav.greeting')) ?> <?= htmlspecialchars($user['display_name'] ?: $user['email']) ?></span>
          <a href="index.php?p=dashboard" class="btn btn-primary text-xs"><?= htmlspecialchars(selfai_t('nav.dashboard')) ?></a>
          <a href="index.php?p=logout" class="btn btn-ghost text-xs"><?= htmlspecialchars(selfai_t('nav.sign_out')) ?></a>
        <?php else: ?>
          <a href="index.php?p=login" class="btn btn-ghost text-xs"><?= htmlspecialchars(selfai_t('nav.sign_in')) ?></a>
          <a href="index.php?p=signup" class="btn btn-primary text-xs"><?= htmlspecialchars(selfai_t('nav.get_started')) ?></a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main class="flex-1">
    <?= $bodySafe ?>
  </main>

  <footer class="border-t border-white/10 py-8 mt-12 text-center text-xs text-slate-500">
    SelfAI · <?= htmlspecialchars($brand['first_brand']) ?> · <?= htmlspecialchars($brand['ayah'] ?? '﴿لَقَدْ خَلَقْنَا الْإِنسَانَ فِي أَحْسَنِ تَقْوِيمٍ﴾') ?>
    <div class="mt-1 opacity-60">© 2030B — <?= htmlspecialchars(selfai_t('common.tagline')) ?></div>
  </footer>

  <script src="assets/js/idb.js" defer></script>
  <script src="assets/js/i18n.js" defer></script>
  <script src="assets/js/typewriter.js" defer></script>
  <script src="assets/js/chatboxes.js" defer></script>
  <script src="assets/js/selfai.js" defer></script>
  <script>document.addEventListener('DOMContentLoaded', () => { if (window.lucide) lucide.createIcons(); });</script>
</body>
</html>
<?php }

// --- Route handling
if ($page === 'logout') {
    $_SESSION = [];
    if (session_id()) session_destroy();
    header('Location: index.php');
    exit;
}

if ($page === 'login' || $page === 'signup') {
    $isSignup = ($page === 'signup');
    ob_start(); ?>
    <section class="max-w-md mx-auto px-4 py-16">
      <div class="glass-card p-8">
        <h1 class="font-display text-2xl font-bold text-white mb-1"><?= htmlspecialchars($isSignup ? selfai_t('auth.create_title') : selfai_t('auth.welcome_back')) ?></h1>
        <p class="text-sm text-slate-400 mb-6"><?= htmlspecialchars($isSignup ? selfai_t('auth.create_sub') : selfai_t('auth.welcome_sub')) ?></p>
        <form id="authForm" data-action="<?= $isSignup ? 'signup' : 'login' ?>" class="space-y-4">
          <?php if ($isSignup): ?>
            <label class="block text-sm"><?= htmlspecialchars(selfai_t('auth.display_name')) ?>
              <input name="display_name" class="input" placeholder="<?= htmlspecialchars(selfai_t('auth.display_name_ph')) ?>" autocomplete="name" />
            </label>
          <?php endif; ?>
          <label class="block text-sm"><?= htmlspecialchars(selfai_t('auth.email')) ?>
            <input name="email" type="email" required class="input" placeholder="<?= htmlspecialchars(selfai_t('auth.email_ph')) ?>" autocomplete="email" />
          </label>
          <label class="block text-sm"><?= htmlspecialchars(selfai_t('auth.password')) ?>
            <input name="password" type="password" required minlength="6" class="input" placeholder="<?= htmlspecialchars(selfai_t('auth.password_ph')) ?>" autocomplete="<?= $isSignup ? 'new-password' : 'current-password' ?>" />
          </label>
          <button class="btn btn-primary w-full justify-center"><?= htmlspecialchars($isSignup ? selfai_t('auth.create_btn') : selfai_t('auth.sign_in_btn')) ?></button>
          <p class="text-xs text-slate-400 text-center">
            <?= htmlspecialchars($isSignup ? selfai_t('auth.already_have') : selfai_t('auth.new_here')) ?>
            <a class="text-brand-500 hover:underline" href="index.php?p=<?= $isSignup ? 'login' : 'signup' ?>">
              <?= htmlspecialchars($isSignup ? selfai_t('auth.go_signin') : selfai_t('auth.go_signup')) ?>
            </a>
          </p>
          <p id="authStatus" class="text-sm hidden" role="status" aria-live="polite"></p>
        </form>
      </div>
    </section>
    <?php $body = ob_get_clean(); selfai_layout($isSignup ? selfai_t('auth.create_btn') : selfai_t('auth.sign_in_btn'), $body, $user); exit;
}

if ($page === 'dashboard' || $page === 'chat') {
    if (!$user) { header('Location: index.php?p=login'); exit; }
    if ($page === 'dashboard') {
        ob_start(); ?>
        <section class="max-w-7xl mx-auto px-4 sm:px-6 py-10">
          <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
            <div>
              <h1 class="font-display text-3xl sm:text-4xl font-bold text-white">
                <?= htmlspecialchars(selfai_t('dashboard.hello_prefix')) ?> <?= htmlspecialchars($user['display_name'] ?: $user['email']) ?> <span class="text-brand-500">·</span> <?= htmlspecialchars(selfai_t('dashboard.welcome_to')) ?>
              </h1>
              <p class="text-sm text-slate-400 mt-2"><?= htmlspecialchars(selfai_t('dashboard.sub')) ?></p>
            </div>
            <div class="text-right">
              <div class="text-xs uppercase text-slate-500 tracking-widest"><?= htmlspecialchars(selfai_t('dashboard.sax_score')) ?></div>
              <output id="saxScore" class="font-display text-5xl text-brand-500 leading-none block">—</output>
              <div class="text-xs text-slate-500"><?= htmlspecialchars(selfai_t('dashboard.sax_sub')) ?></div>
            </div>
          </div>

          <div id="ledgerGrid" class="grid grid-cols-2 sm:grid-cols-5 lg:grid-cols-10 gap-2 mb-8 sk-area" role="list" aria-label="2030B currencies"></div>

          <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
            <h2 class="font-display text-xl text-white"><?= htmlspecialchars(selfai_t('dashboard.your_clones')) ?></h2>
            <p class="text-xs text-slate-500"><?= htmlspecialchars(selfai_t('dashboard.click_persona')) ?></p>
          </div>
          <div id="cloneGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 sk-area" role="list"></div>

          <div class="mt-12 grid lg:grid-cols-2 gap-6">
            <section class="glass-card p-6" aria-labelledby="statsTitle">
              <h3 id="statsTitle" class="font-display text-lg text-white mb-3"><?= htmlspecialchars(selfai_t('dashboard.stats')) ?></h3>
              <ul id="statsList" class="text-sm text-slate-300 space-y-2"></ul>
            </section>
            <section class="glass-card p-6" aria-labelledby="recentTitle">
              <h3 id="recentTitle" class="font-display text-lg text-white mb-3"><?= htmlspecialchars(selfai_t('dashboard.recent_activity')) ?></h3>
              <ul id="recentList" class="text-sm text-slate-300 space-y-2 max-h-72 overflow-auto"></ul>
            </section>
          </div>
        </section>
        <?php $body = ob_get_clean(); selfai_layout(selfai_t('nav.dashboard'), $body, $user); exit;
    }
    // chat — semantic 3-pane responsive shell:
    //   • <aside> (left)   → persona switcher  — collapses to drawer on mobile
    //   • <main> (center)  → conversation log + composer
    //   • <aside> (right)  → history drawer — toggled by navbar icon
    // Uses native HTML elements: <menu>, <article>, <output>, <time>, <header>, <footer>, <dialog>.
    $cloneId      = $_GET['clone']       ?? 'selfai_x_master';
    $chatboxIdReq = (int)($_GET['chatbox'] ?? 0);
    $clone = selfai_clone($cloneId);
    if (!$clone) { header('Location: index.php?p=dashboard'); exit; }
    $allClones = selfai_config()['clones'] ?? [];
    ob_start(); ?>
    <section class="chat-shell"
             data-clone-id="<?= htmlspecialchars($cloneId) ?>"
             data-chatbox-id="<?= (int)$chatboxIdReq ?>"
             data-i18n-root="chat"
             aria-label="<?= htmlspecialchars(selfai_t('chat.personas')) ?>">

      <!-- ============================================================
           LEFT ASIDE — Persona vertical menu (icons + text)
           Semantic: <aside><header><menu role="menu"><li><a role="menuitemradio">…
           ============================================================ -->
      <aside id="personaNav" class="persona-nav" aria-label="<?= htmlspecialchars(selfai_t('chat.personas')) ?>">
        <header class="persona-nav__head">
          <h2 class="text-xs uppercase tracking-widest text-slate-500 m-0"><?= htmlspecialchars(selfai_t('chat.personas')) ?></h2>
          <button type="button" class="persona-nav__close md:hidden" data-action="close-personas"
                  aria-label="<?= htmlspecialchars(selfai_t('common.close')) ?>" data-i18n-aria="common.close">
            <i data-lucide="x" class="w-4 h-4"></i>
          </button>
        </header>
        <menu class="persona-nav__list" role="menu" aria-label="<?= htmlspecialchars(selfai_t('chat.personas')) ?>">
          <?php foreach ($allClones as $c): ?>
            <li role="none">
              <a href="index.php?p=chat&clone=<?= urlencode($c['id']) ?>"
                 role="menuitemradio"
                 aria-checked="<?= $c['id'] === $cloneId ? 'true' : 'false' ?>"
                 class="persona-item <?= $c['id'] === $cloneId ? 'is-active' : '' ?>"
                 data-clone-id="<?= htmlspecialchars($c['id']) ?>"
                 style="--persona-color: <?= htmlspecialchars($c['color']) ?>"
                 title="<?= htmlspecialchars($c['english_name']) ?>">
                <span class="persona-item__icon" aria-hidden="true"><?= htmlspecialchars($c['icon']) ?></span>
                <span class="persona-item__body">
                  <span class="persona-item__name"><?= htmlspecialchars($c['code']) ?></span>
                  <span class="persona-item__sub"><?= htmlspecialchars($c['project']) ?></span>
                </span>
                <span class="persona-item__currency" aria-label="<?= htmlspecialchars($c['currency']) ?>"><?= htmlspecialchars($c['currency']) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </menu>
      </aside>

      <!-- ============================================================
           CENTER — Conversation column
           Semantic: <main><header role=toolbar><section role=log><form><footer>
           ============================================================ -->
      <main class="chat-main" aria-label="Conversation">
        <header class="chat-toolbar" role="toolbar" aria-label="Chat toolbar">
          <button type="button" class="chat-toolbar__btn md:hidden" data-action="open-personas"
                  aria-label="<?= htmlspecialchars(selfai_t('chat.open_personas')) ?>" data-i18n-aria="chat.open_personas">
            <i data-lucide="menu" class="w-4 h-4"></i>
            <span class="sr-only" data-i18n="chat.personas"><?= htmlspecialchars(selfai_t('chat.personas')) ?></span>
          </button>
          <div class="chat-toolbar__title">
            <span class="text-2xl leading-none" aria-hidden="true" id="chatCloneIcon"><?= htmlspecialchars($clone['icon']) ?></span>
            <hgroup class="min-w-0">
              <h1 class="font-display text-base sm:text-lg text-white truncate m-0" id="chatCloneName"><?= htmlspecialchars($clone['name']) ?></h1>
              <p class="text-[11px] text-slate-400 truncate m-0">
                <span id="chatCloneCode"><?= htmlspecialchars($clone['code']) ?></span> ·
                <span id="chatCloneEnglish"><?= htmlspecialchars($clone['english_name']) ?></span> ·
                <span id="chatCloneCurrency" style="color: <?= htmlspecialchars($clone['color']) ?>"><?= htmlspecialchars($clone['currency']) ?></span>
              </p>
            </hgroup>
          </div>
          <div class="chat-toolbar__actions" role="group" aria-label="Actions">
            <a href="index.php?p=dashboard" class="chat-toolbar__btn"
               title="<?= htmlspecialchars(selfai_t('chat.back_to_dashboard')) ?>"
               aria-label="<?= htmlspecialchars(selfai_t('chat.back_to_dashboard')) ?>" data-i18n-aria="chat.back_to_dashboard">
              <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
            </a>
            <button type="button" class="chat-toolbar__btn" data-action="clear-cache"
                    title="<?= htmlspecialchars(selfai_t('chat.refresh_history')) ?>"
                    aria-label="<?= htmlspecialchars(selfai_t('chat.refresh_history')) ?>" data-i18n-aria="chat.refresh_history">
              <i data-lucide="refresh-cw" class="w-4 h-4"></i>
            </button>
            <button type="button" id="historyToggle"
                    class="chat-toolbar__btn chat-toolbar__btn--accent"
                    data-action="open-history"
                    aria-controls="historyDrawer"
                    aria-expanded="false"
                    aria-label="<?= htmlspecialchars(selfai_t('chat.open_history')) ?>" data-i18n-aria="chat.open_history">
              <i data-lucide="history" class="w-4 h-4"></i>
              <span class="hidden sm:inline ml-1 text-xs" data-i18n="chat.history"><?= htmlspecialchars(selfai_t('chat.history')) ?></span>
            </button>
          </div>
        </header>

        <p id="chatAyah" class="chat-ayah text-xs text-slate-500 italic px-4 py-1 border-b border-white/5" dir="rtl" lang="ar">
          <?= htmlspecialchars($clone['ayah']) ?>
        </p>

        <section id="chatMessages" class="chat-messages" role="log" aria-live="polite" aria-relevant="additions" aria-atomic="false" aria-label="<?= htmlspecialchars(selfai_t('chat.history')) ?>">
          <p class="text-xs text-slate-500 italic sk-line" data-i18n="chat.loading_history"><?= htmlspecialchars(selfai_t('chat.loading_history')) ?></p>
        </section>

        <form id="chatForm" class="chat-composer" data-clone-id="<?= htmlspecialchars($cloneId) ?>" autocomplete="off">
          <label for="chatInput" class="sr-only" data-i18n="chat.input_label"><?= htmlspecialchars(selfai_t('chat.input_label')) ?></label>
          <input id="chatInput" name="message" class="input flex-1"
                 placeholder="<?= htmlspecialchars(selfai_t('chat.talk_to') . ' ' . $clone['code']) ?>…"
                 data-i18n-placeholder-prefix="chat.talk_to"
                 data-clone-code="<?= htmlspecialchars($clone['code']) ?>"
                 autocomplete="off" enterkeyhint="send" />
          <button class="btn btn-primary" type="submit">
            <span class="hidden sm:inline" data-i18n="common.send"><?= htmlspecialchars(selfai_t('common.send')) ?></span>
            <i data-lucide="send" class="w-4 h-4 sm:ml-1"></i>
          </button>
        </form>
        <output id="chatStatus" for="chatForm" class="text-[11px] text-slate-500 px-4 pb-2 block" aria-live="polite"></output>
      </main>

      <!-- ============================================================
           RIGHT ASIDE — Conversation history drawer
           Semantic: <aside><header><section.memory><ol role=list>
           ============================================================ -->
      <aside id="historyDrawer" class="history-drawer" aria-label="<?= htmlspecialchars(selfai_t('chat.history')) ?>" aria-hidden="true">
        <header class="history-drawer__head">
          <hgroup>
            <h2 class="text-xs uppercase tracking-widest text-slate-500 m-0" data-i18n="chat.history"><?= htmlspecialchars(selfai_t('chat.history')) ?></h2>
            <p id="historyCloneLabel" class="text-sm text-white m-0"><?= htmlspecialchars($clone['code']) ?></p>
          </hgroup>
          <button type="button" class="persona-nav__close" data-action="close-history"
                  aria-label="<?= htmlspecialchars(selfai_t('common.close')) ?>" data-i18n-aria="common.close">
            <i data-lucide="x" class="w-4 h-4"></i>
          </button>
        </header>
        <section class="history-drawer__memory" id="historyMemory" aria-labelledby="memTitle">
          <h3 id="memTitle" class="text-[11px] uppercase tracking-widest text-slate-500 mb-1 m-0" data-i18n="chat.long_term_memory"><?= htmlspecialchars(selfai_t('chat.long_term_memory')) ?></h3>
          <p class="text-xs text-slate-400 italic m-0" id="historyMemoryBody" data-i18n="common.loading"><?= htmlspecialchars(selfai_t('common.loading')) ?></p>
        </section>

        <section class="history-drawer__chatboxes" id="chatboxesSection" aria-labelledby="chatboxesTitle">
          <h3 id="chatboxesTitle" class="text-[11px] uppercase tracking-widest text-slate-500 mb-1 m-0" data-i18n="chat.chatboxes"><?= htmlspecialchars(selfai_t('chat.chatboxes')) ?></h3>
          <button type="button" class="chatbox-new-btn" data-action="new-chatbox"
                  aria-label="<?= htmlspecialchars(selfai_t('chat.new_chatbox')) ?>" data-i18n-aria="chat.new_chatbox">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span data-i18n="chat.new_chatbox"><?= htmlspecialchars(selfai_t('chat.new_chatbox')) ?></span>
          </button>
          <ul id="chatboxList" class="chatbox-list" role="list">
            <li class="text-xs text-slate-500 italic sk-line" data-i18n="common.loading"><?= htmlspecialchars(selfai_t('common.loading')) ?></li>
          </ul>
        </section>

        <section class="history-drawer__history-section" aria-labelledby="historyTitle">
          <h3 id="historyTitle" class="text-[11px] uppercase tracking-widest text-slate-500 mb-1 m-0" data-i18n="chat.history"><?= htmlspecialchars(selfai_t('chat.history')) ?></h3>
          <ol id="historyList" class="history-drawer__list" role="list">
            <li class="text-xs text-slate-500 italic sk-line" data-i18n="chat.loading_history"><?= htmlspecialchars(selfai_t('chat.loading_history')) ?></li>
          </ol>
        </section>
      </aside>

      <!-- Scrim for mobile drawers -->
      <div class="chat-scrim" data-action="close-all" aria-hidden="true"></div>

      <!-- ============================================================
           Reusable <template> elements for JS rendering
           These keep the chat bubble markup declarative and i18n-aware.
           ============================================================ -->
      <template id="tplBubbleUser">
        <article class="bubble-row bubble-row--user" data-role="user">
          <div class="bubble user" data-field="content"></div>
          <footer class="bubble-meta text-right pr-1">
            <time data-field="time"></time>
          </footer>
        </article>
      </template>

      <template id="tplBubbleAssistant">
        <article class="bubble-row bubble-row--assistant" data-role="assistant">
          <div class="bubble assistant md-body" data-field="content"></div>
          <footer class="bubble-meta bubble-meta--assistant pl-1">
            <span class="bubble-meta__line">
              <time data-field="time"></time>
              <span class="bubble-meta__sep" aria-hidden="true">·</span>
              <span data-field="provider"></span>
              <span class="bubble-meta__sep" aria-hidden="true">·</span>
              <span data-field="memory"></span>
            </span>
            <span class="bubble-meta__line bubble-meta__actions">
              <span class="currency-badge" data-field="currency" data-delta=""></span>
              <button type="button" class="bubble-action-btn" data-action="copy" aria-label="<?= htmlspecialchars(selfai_t('chat.copy')) ?>" data-i18n-aria="chat.copy" title="<?= htmlspecialchars(selfai_t('chat.copy')) ?>" data-i18n-title="chat.copy">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                <span class="sr-only" data-i18n="chat.copy"><?= htmlspecialchars(selfai_t('chat.copy')) ?></span>
              </button>
              <button type="button" class="bubble-action-btn" data-action="regenerate" aria-label="<?= htmlspecialchars(selfai_t('chat.regenerate')) ?>" data-i18n-aria="chat.regenerate" title="<?= htmlspecialchars(selfai_t('chat.regenerate')) ?>" data-i18n-title="chat.regenerate">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                <span class="sr-only" data-i18n="chat.regenerate"><?= htmlspecialchars(selfai_t('chat.regenerate')) ?></span>
              </button>
              <span class="bubble-fb" role="group" aria-label="Feedback">
                <button type="button" class="fb-btn up" data-fb="1" aria-label="<?= htmlspecialchars(selfai_t('chat.helpful')) ?>" data-i18n-aria="chat.helpful" aria-pressed="false">▲</button>
                <button type="button" class="fb-btn down" data-fb="-1" aria-label="<?= htmlspecialchars(selfai_t('chat.not_helpful')) ?>" data-i18n-aria="chat.not_helpful" aria-pressed="false">▼</button>
              </span>
            </span>
          </footer>
        </article>
      </template>

      <template id="tplHistoryItem">
        <li class="history-item" role="listitem">
          <header class="history-item__role">
            <span data-field="role"></span>
            <time data-field="time"></time>
          </header>
          <p class="history-item__preview m-0" data-field="preview"></p>
        </li>
      </template>
    </section>
    <?php $body = ob_get_clean(); selfai_layout(selfai_t('chat.history') . ' · ' . $clone['code'], $body, $user); exit;
}

if ($page === 'docs') {
    ob_start();
    require __DIR__ . '/_docs.php';
    $body = ob_get_clean();
    selfai_layout(selfai_t('nav.docs'), $body, $user); exit;
}

// Home (landing for /selfai)
ob_start();
require __DIR__ . '/_landing.php';
$body = ob_get_clean();
selfai_layout(selfai_t('common.app_name') . ' · ' . selfai_t('common.ecosystem'), $body, $user);
