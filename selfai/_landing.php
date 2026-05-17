<?php $cfg = selfai_config(); $clones = $cfg['clones']; ?>
<section class="relative overflow-hidden">
  <div class="absolute inset-0 -z-10 opacity-50">
    <div class="absolute -top-32 -left-32 w-[480px] h-[480px] rounded-full bg-brand-500/30 blur-3xl"></div>
    <div class="absolute top-20 right-0 w-[420px] h-[420px] rounded-full bg-cyan-400/20 blur-3xl"></div>
    <div class="absolute bottom-0 left-1/3 w-[380px] h-[380px] rounded-full bg-amber-400/20 blur-3xl"></div>
  </div>
  <div class="max-w-5xl mx-auto px-4 sm:px-6 py-20 text-center">
    <span class="inline-flex items-center gap-2 glass-card px-4 py-1.5 text-xs font-semibold">
      <span class="w-2 h-2 rounded-full bg-brand-500 animate-pulse"></span>
      First brand: 2030B Ecosystem · 10 dimensions + 1 integrator
    </span>
    <h1 class="font-display text-5xl sm:text-6xl lg:text-7xl font-bold mt-6 leading-[1.05] text-white">
      Eleven SelfAI personas.<br/>
      <span class="bg-gradient-to-r from-brand-500 via-cyan-400 to-amber-400 text-transparent bg-clip-text">One lifetime companion.</span>
    </h1>
    <p class="mt-6 text-lg text-slate-400 max-w-2xl mx-auto">
      SelfAI is a full-stack SaaS that grows with you across ten dimensions of human flourishing —
      mind, conscience, body, imagination, spirit, will, wisdom, justice, freedom, gratitude —
      orchestrated by the eleventh persona, <strong class="text-white">SelfAI-X</strong>. Powered by
      DeepSeek V4 with free fallbacks (Groq · Gemini · OpenRouter · Hugging Face).
    </p>
    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
      <a href="index.php?p=signup" class="btn btn-primary">Create your SelfAI →</a>
      <a href="index.php?p=docs" class="btn btn-ghost">Install docs · /selfai</a>
    </div>
    <p class="mt-6 text-xs text-slate-500"><?= htmlspecialchars($cfg['brand']['vision']) ?></p>
  </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16">
  <div class="text-center mb-10">
    <h2 class="font-display text-3xl text-white">The eleven personas</h2>
    <p class="text-sm text-slate-400 mt-2">Each persona = one 2030B project + one currency + one Qur'anic anchor.</p>
  </div>
  <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    <?php foreach ($clones as $c): ?>
      <article class="glass-card p-5 hover:border-brand-500/50 transition-colors">
        <div class="flex items-center gap-3 mb-2">
          <span class="text-2xl"><?= htmlspecialchars($c['icon']) ?></span>
          <div>
            <h3 class="font-display font-bold text-white text-base leading-tight"><?= htmlspecialchars($c['name']) ?></h3>
            <p class="text-[11px] text-slate-500"><?= htmlspecialchars($c['code']) ?> · <?= htmlspecialchars($c['english_name']) ?></p>
          </div>
        </div>
        <p class="text-xs text-slate-300/90 mb-3 leading-relaxed"><?= htmlspecialchars($c['description']) ?></p>
        <div class="flex items-center justify-between text-[11px]">
          <span class="px-2 py-0.5 rounded-full" style="background: <?= htmlspecialchars($c['color']) ?>22; color: <?= htmlspecialchars($c['color']) ?>; border:1px solid <?= htmlspecialchars($c['color']) ?>55">
            <?= htmlspecialchars($c['currency']) ?>
          </span>
          <span class="text-slate-500 truncate ml-2 text-right" dir="rtl"><?= htmlspecialchars($c['ayah']) ?></span>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="max-w-5xl mx-auto px-4 sm:px-6 py-16">
  <div class="glass-card p-8 text-center">
    <h3 class="font-display text-2xl text-white mb-3">The SelfAI-X composite score</h3>
    <p class="text-sm text-slate-400 max-w-2xl mx-auto">
      Each conversation earns you 1–5 units of the matching currency (CTC, TIC, VTC, INC, SCC, WPC, WDC, JEC, FLC, GRC).
      SelfAI-X reads all ten and produces a single 0–100 composite — a verifiable, tokenizable signal of your evolution.
    </p>
    <div class="mt-6 flex flex-wrap items-center justify-center gap-2 text-[11px]">
      <?php foreach (['CTC','TIC','VTC','INC','SCC','WPC','WDC','JEC','FLC','GRC'] as $cur): ?>
        <span class="px-2 py-1 rounded-full border border-white/10 text-slate-300"><?= $cur ?></span>
      <?php endforeach; ?>
      <span class="px-2 py-1 rounded-full bg-brand-500/20 text-brand-50 border border-brand-500/30 font-semibold">SAX</span>
    </div>
  </div>
</section>
