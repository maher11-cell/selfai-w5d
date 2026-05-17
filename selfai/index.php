<?php
/** SelfAI — Front controller. Serves auth, dashboard, chat, docs. */
require __DIR__ . '/includes/db.php';
session_name('selfai_sid');
session_start();

$page = $_GET['p'] ?? 'home';
$user = selfai_current_user();

function selfai_layout(string $title, string $body, ?array $user = null): void {
    $brand = selfai_config()['brand'];
    $bodySafe = $body;
    $userJson = $user ? json_encode(['id' => (int)$user['id'], 'email' => $user['email'], 'display_name' => $user['display_name']]) : 'null';
    ?>
<!DOCTYPE html>
<html lang="en" class="dark scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($title) ?> · SelfAI · 2030B Ecosystem</title>
  <meta name="description" content="SelfAI — 11 specialized digital personas powered by 2030B Ecosystem currencies. DeepSeek V4 + free AI fallbacks." />
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
    window.__SELFAI__ = { user: <?= $userJson ?>, brand: <?= json_encode($brand, JSON_UNESCAPED_UNICODE) ?> };
  </script>
</head>
<body class="bg-ink-950 text-slate-200 min-h-screen flex flex-col">

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
        <span class="hidden sm:inline text-xs px-2 py-0.5 rounded-full bg-brand-500/20 text-brand-50 border border-brand-500/30">2030B Ecosystem</span>
      </a>
      <nav class="ml-auto hidden md:flex items-center gap-5 text-sm">
        <a class="hover:text-white" href="index.php">Home</a>
        <a class="hover:text-white" href="index.php?p=dashboard">Dashboard</a>
        <a class="hover:text-white" href="index.php?p=docs">Install Docs</a>
        <a class="hover:text-white" href="../index.html">W5D</a>
      </nav>
      <div class="ml-auto md:ml-0 flex items-center gap-2">
        <?php if ($user): ?>
          <span class="text-xs text-slate-400 hidden sm:inline">Hi, <?= htmlspecialchars($user['display_name'] ?: $user['email']) ?></span>
          <a href="index.php?p=dashboard" class="btn btn-primary text-xs">Dashboard</a>
          <a href="index.php?p=logout" class="btn btn-ghost text-xs">Sign out</a>
        <?php else: ?>
          <a href="index.php?p=login" class="btn btn-ghost text-xs">Sign in</a>
          <a href="index.php?p=signup" class="btn btn-primary text-xs">Get started</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main class="flex-1">
    <?= $bodySafe ?>
  </main>

  <footer class="border-t border-white/10 py-8 mt-12 text-center text-xs text-slate-500">
    SelfAI · <?= htmlspecialchars($brand['first_brand']) ?> · <?= htmlspecialchars($brand['ayah'] ?? '﴿لَقَدْ خَلَقْنَا الْإِنسَانَ فِي أَحْسَنِ تَقْوِيمٍ﴾') ?>
    <div class="mt-1 opacity-60">© 2030B — eleven specialized digital personas, one lifetime companion.</div>
  </footer>

  <script src="assets/js/idb.js" defer></script>
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
        <h1 class="font-display text-2xl font-bold text-white mb-1"><?= $isSignup ? 'Create your SelfAI' : 'Welcome back' ?></h1>
        <p class="text-sm text-slate-400 mb-6"><?= $isSignup ? 'A lifetime companion across 10 dimensions + 1 integrator.' : 'Sign in to continue your evolution.' ?></p>
        <form id="authForm" data-action="<?= $isSignup ? 'signup' : 'login' ?>" class="space-y-4">
          <?php if ($isSignup): ?>
            <label class="block text-sm">Display name
              <input name="display_name" class="input" placeholder="Your name" />
            </label>
          <?php endif; ?>
          <label class="block text-sm">Email
            <input name="email" type="email" required class="input" placeholder="you@example.com" />
          </label>
          <label class="block text-sm">Password
            <input name="password" type="password" required minlength="6" class="input" placeholder="At least 6 characters" />
          </label>
          <button class="btn btn-primary w-full justify-center"><?= $isSignup ? 'Create account' : 'Sign in' ?></button>
          <p class="text-xs text-slate-400 text-center">
            <?= $isSignup ? 'Already have an account?' : 'New here?' ?>
            <a class="text-brand-500 hover:underline" href="index.php?p=<?= $isSignup ? 'login' : 'signup' ?>">
              <?= $isSignup ? 'Sign in' : 'Create one' ?>
            </a>
          </p>
          <p id="authStatus" class="text-sm hidden"></p>
        </form>
      </div>
    </section>
    <?php $body = ob_get_clean(); selfai_layout($isSignup ? 'Sign up' : 'Sign in', $body, $user); exit;
}

if ($page === 'dashboard' || $page === 'chat') {
    if (!$user) { header('Location: index.php?p=login'); exit; }
    if ($page === 'dashboard') {
        ob_start(); ?>
        <section class="max-w-7xl mx-auto px-4 sm:px-6 py-10">
          <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
            <div>
              <h1 class="font-display text-3xl sm:text-4xl font-bold text-white">
                Hello, <?= htmlspecialchars($user['display_name'] ?: $user['email']) ?> <span class="text-brand-500">·</span> welcome to your SelfAI
              </h1>
              <p class="text-sm text-slate-400 mt-2">Eleven specialized personas, one lifetime companion — powered by 2030B currencies.</p>
            </div>
            <div class="text-right">
              <div class="text-xs uppercase text-slate-500 tracking-widest">SelfAI-X score</div>
              <div id="saxScore" class="font-display text-5xl text-brand-500 leading-none">—</div>
              <div class="text-xs text-slate-500">tokenizable composite of 10 dimensions</div>
            </div>
          </div>

          <div id="ledgerGrid" class="grid grid-cols-2 sm:grid-cols-5 lg:grid-cols-10 gap-2 mb-8 sk-area"></div>

          <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
            <h2 class="font-display text-xl text-white">Your 11 clones</h2>
            <p class="text-xs text-slate-500">Click any persona to start a conversation.</p>
          </div>
          <div id="cloneGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 sk-area"></div>

          <div class="mt-12 grid lg:grid-cols-2 gap-6">
            <div class="glass-card p-6">
              <h3 class="font-display text-lg text-white mb-3">Stats</h3>
              <ul id="statsList" class="text-sm text-slate-300 space-y-2"></ul>
            </div>
            <div class="glass-card p-6">
              <h3 class="font-display text-lg text-white mb-3">Recent activity</h3>
              <ul id="recentList" class="text-sm text-slate-300 space-y-2 max-h-72 overflow-auto"></ul>
            </div>
          </div>
        </section>
        <?php $body = ob_get_clean(); selfai_layout('Dashboard', $body, $user); exit;
    }
    // chat
    $cloneId = $_GET['clone'] ?? 'selfai_x_master';
    $clone = selfai_clone($cloneId);
    if (!$clone) { header('Location: index.php?p=dashboard'); exit; }
    ob_start(); ?>
    <section class="max-w-5xl mx-auto px-4 sm:px-6 py-8">
      <a href="index.php?p=dashboard" class="text-xs text-slate-400 hover:text-white">← Back to dashboard</a>
      <header class="mt-4 mb-6 flex flex-wrap items-center gap-3">
        <span class="text-3xl"><?= htmlspecialchars($clone['icon']) ?></span>
        <div>
          <h1 class="font-display text-2xl sm:text-3xl font-bold text-white"><?= htmlspecialchars($clone['name']) ?></h1>
          <p class="text-xs text-slate-400">
            <?= htmlspecialchars($clone['code']) ?> · <?= htmlspecialchars($clone['english_name']) ?> ·
            <span style="color: <?= htmlspecialchars($clone['color']) ?>"><?= htmlspecialchars($clone['currency']) ?></span> currency ·
            <?= htmlspecialchars($clone['ayah']) ?>
          </p>
        </div>
      </header>
      <div class="glass-card p-4 sm:p-6">
        <div id="chatMessages" class="space-y-3 max-h-[60vh] overflow-y-auto pr-1">
          <div class="text-xs text-slate-500 italic sk-line">Loading history…</div>
        </div>
        <form id="chatForm" class="mt-4 flex gap-2" data-clone-id="<?= htmlspecialchars($cloneId) ?>">
          <input id="chatInput" class="input flex-1" placeholder="Talk to <?= htmlspecialchars($clone['code']) ?>…" autocomplete="off" />
          <button class="btn btn-primary">Send</button>
        </form>
        <p id="chatStatus" class="text-xs text-slate-500 mt-2"></p>
      </div>
    </section>
    <?php $body = ob_get_clean(); selfai_layout('Chat · ' . $clone['code'], $body, $user); exit;
}

if ($page === 'docs') {
    ob_start();
    require __DIR__ . '/_docs.php';
    $body = ob_get_clean();
    selfai_layout('Install Docs', $body, $user); exit;
}

// Home (landing for /selfai)
ob_start();
require __DIR__ . '/_landing.php';
$body = ob_get_clean();
selfai_layout('SelfAI · 2030B Ecosystem', $body, $user);
