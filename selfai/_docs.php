<section class="max-w-4xl mx-auto px-4 sm:px-6 py-12 prose-doc">
  <p class="text-xs text-slate-500">/selfai · Installation documentation</p>
  <h1 class="font-display text-4xl text-white mt-2">SelfAI on 2030B — Installation Guide</h1>
  <p class="text-slate-400 mt-3">
    SelfAI is a full-stack SaaS (PHP + SQLite + vanilla JS + IndexedDB cache) that ships eleven specialized digital personas
    aligned with the 2030B Ecosystem currencies. This guide walks you from a fresh server to a working install at
    <code class="kbd">https://2030b.com/selfai</code>, using <strong>DeepSeek V4</strong> as the primary engine and four free AI providers
    as fallbacks.
  </p>

  <h2>1. Architecture overview</h2>
  <ul>
    <li><strong>Backend:</strong> PHP 8.1+ with <code class="kbd">php-sqlite3</code>, <code class="kbd">php-curl</code>, <code class="kbd">php-pdo</code>.</li>
    <li><strong>Database:</strong> SQLite — one shared <code class="kbd">system.db</code> (users + sessions) plus one isolated <code class="kbd">user_&lt;id&gt;.db</code> per user.</li>
    <li><strong>Frontend:</strong> HTML5, Tailwind via CDN, vanilla JavaScript — no framework required.</li>
    <li><strong>Client cache:</strong> IndexedDB (<code class="kbd">selfai-cache</code>) for instant conversation rendering + offline read.</li>
    <li><strong>Visuals:</strong> Animated SVG logo + skeleton loaders.</li>
    <li><strong>AI engine:</strong> DeepSeek V4 (<code class="kbd">deepseek-chat</code>) primary; OpenRouter / Groq / Gemini / Hugging Face as free fallbacks; offline preview as last resort.</li>
  </ul>

  <h2>2. File structure</h2>
<pre class="code-block"><code>selfai/
├── index.php                 # Front controller (router)
├── _landing.php              # Landing template
├── _docs.php                 # This page
├── api/
│   ├── auth.php              # signup / login / logout / me
│   ├── chat.php              # AI chat — DeepSeek V4 + free fallbacks
│   ├── clones.php            # Public catalogue of 11 personas
│   ├── user_data.php         # dashboard / history / attribute / resume / feedback
│   ├── config.json           # 11 clones + admin prompts + currencies
│   └── .env                  # API keys (gitignored)
├── includes/
│   └── db.php                # SQLite + session helpers
├── assets/
│   ├── css/selfai.css        # Glass cards, skeleton loaders, animations
│   ├── js/idb.js             # IndexedDB cache layer
│   ├── js/selfai.js          # Dashboard + chat + auth UI logic
│   └── svg/                  # Animated logo + persona icons
└── database/                 # SQLite files (auto-created, gitignored)</code></pre>

  <h2>3. Install on a fresh server</h2>
<pre class="code-block"><code># Debian / Ubuntu
sudo apt-get update
sudo apt-get install -y php-cli php-sqlite3 php-curl

# Clone or copy this project to /var/www/2030b.com/
cd /var/www/2030b.com/

# Make the database directory writable by the web user
mkdir -p selfai/database
chown -R www-data:www-data selfai/database
chmod 775 selfai/database

# Copy the env template and add your DeepSeek key
cp selfai/api/.env.example selfai/api/.env
nano selfai/api/.env

# Quick start with PHP's built-in server (development only)
php -S 0.0.0.0:8080 -t /var/www/2030b.com</code></pre>

  <h2>4. Configure AI providers</h2>
  <p>Open <code class="kbd">selfai/api/.env</code> and set <strong>at least one</strong> of the keys below. SelfAI will try them in this exact order:</p>
  <table class="doc-table">
    <thead><tr><th>Provider</th><th>Env variable</th><th>Model</th><th>Where to get a key</th></tr></thead>
    <tbody>
      <tr><td><strong>DeepSeek V4 (primary)</strong></td><td><code class="kbd">DEEPSEEK_API_KEY</code></td><td><code class="kbd">deepseek-chat</code></td><td><a class="link" href="https://platform.deepseek.com/" target="_blank" rel="noopener">platform.deepseek.com</a></td></tr>
      <tr><td>OpenRouter (free Llama)</td><td><code class="kbd">OPENROUTER_API_KEY</code></td><td><code class="kbd">meta-llama/llama-3.1-8b-instruct:free</code></td><td><a class="link" href="https://openrouter.ai/" target="_blank" rel="noopener">openrouter.ai</a></td></tr>
      <tr><td>Groq (free tier)</td><td><code class="kbd">GROQ_API_KEY</code></td><td><code class="kbd">llama-3.3-70b-versatile</code></td><td><a class="link" href="https://console.groq.com/" target="_blank" rel="noopener">console.groq.com</a></td></tr>
      <tr><td>Google Gemini (free)</td><td><code class="kbd">GEMINI_API_KEY</code></td><td><code class="kbd">gemini-2.0-flash-exp</code></td><td><a class="link" href="https://aistudio.google.com/" target="_blank" rel="noopener">aistudio.google.com</a></td></tr>
      <tr><td>Hugging Face Router</td><td><code class="kbd">HF_TOKEN</code></td><td><code class="kbd">meta-llama/Llama-3.2-3B-Instruct</code></td><td><a class="link" href="https://huggingface.co/settings/tokens" target="_blank" rel="noopener">huggingface.co/settings/tokens</a></td></tr>
    </tbody>
  </table>
  <p class="text-xs text-slate-400">No keys at all? SelfAI ships a structured <em>offline preview mode</em> so the UX never breaks during demos.</p>

  <h2>5. The eleven SelfAI personas</h2>
  <p>Configured in <code class="kbd">selfai/api/config.json</code>. Each persona maps to one 2030B project + one currency:</p>
  <table class="doc-table">
    <thead><tr><th>#</th><th>Code</th><th>Project</th><th>Currency</th><th>Verse</th></tr></thead>
    <tbody>
      <tr><td>1</td><td>SelfAI-S</td><td>Be Smarter — كن أذكى</td><td>CTC</td><td dir="rtl">﴿أَفَلَا يَعْقِلُونَ﴾</td></tr>
      <tr><td>2</td><td>SelfAI-H</td><td>Be Honester — كن أصدق</td><td>TIC</td><td dir="rtl">﴿كُونُوا مَعَ الصَّادِقِينَ﴾</td></tr>
      <tr><td>3</td><td>SelfAI-V</td><td>Be Healthier — كن أصح</td><td>VTC</td><td dir="rtl">﴿وَلَا تُسْرِفُوا﴾</td></tr>
      <tr><td>4</td><td>SelfAI-C</td><td>Be Creater — كن أبدع</td><td>INC</td><td dir="rtl">﴿أَحْسَنَ كُلَّ شَيْءٍ خَلَقَهُ﴾</td></tr>
      <tr><td>5</td><td>SelfAI-K</td><td>Be Kinder — كن أرحم</td><td>SCC</td><td dir="rtl">﴿وَتَعَاوَنُوا عَلَى الْبِرِّ﴾</td></tr>
      <tr><td>6</td><td>SelfAI-B</td><td>Be Braver — كن أشجع</td><td>WPC</td><td dir="rtl">﴿وَلَا تَهِنُوا وَلَا تَحْزَنُوا﴾</td></tr>
      <tr><td>7</td><td>SelfAI-W</td><td>Be Wiser — كن أحكم</td><td>WDC</td><td dir="rtl">﴿وَمَن يُؤْتَ الْحِكْمَةَ﴾</td></tr>
      <tr><td>8</td><td>SelfAI-J</td><td>Be Fairer — كن أعدل</td><td>JEC</td><td dir="rtl">﴿اعْدِلُوا هُوَ أَقْرَبُ لِلتَّقْوَىٰ﴾</td></tr>
      <tr><td>9</td><td>SelfAI-F</td><td>Be Freer — كن أحرر</td><td>FLC</td><td dir="rtl">﴿لَا إِكْرَاهَ فِي الدِّينِ﴾</td></tr>
      <tr><td>10</td><td>SelfAI-G</td><td>Be Grateful — كن أشكر</td><td>GRC</td><td dir="rtl">﴿لَئِن شَكَرْتُمْ لَأَزِيدَنَّكُمْ﴾</td></tr>
      <tr><td>11</td><td>SelfAI-X</td><td>Integrator (Whole Human)</td><td>SAX</td><td dir="rtl">﴿فِي أَحْسَنِ تَقْوِيمٍ﴾</td></tr>
    </tbody>
  </table>

  <h2>6. SelfAI-X scoring engine</h2>
  <p>Each AI reply ends with a line such as <code class="kbd">CTC earned this turn: 3</code>. The chat endpoint parses that line and writes the credit to <code class="kbd">currency_ledger</code>. The dashboard composes a 0–100 SelfAI-X score using:</p>
  <ul>
    <li><strong>30 %</strong> Interactions — total user messages (50 ≈ full).</li>
    <li><strong>20 %</strong> Data richness — saved attributes (<code class="kbd">data_selfai</code> rows).</li>
    <li><strong>10 %</strong> Resume uploaded for SelfAI-Career analysis.</li>
    <li><strong>20 %</strong> Clone mastery — distinct personas engaged (10 = full).</li>
    <li><strong>20 %</strong> Feedback — average thumbs ±1 over assistant turns.</li>
  </ul>

  <h2>7. Database schema (per-user SQLite)</h2>
<pre class="code-block"><code>conversations(id, clone_id, clone_name, role, content, feedback, tokens_used, timestamp)
resumes(id, filename, file_content, uploaded_at)
data_selfai(id, attribute_key UNIQUE, attribute_value, updated_at)
deepseek_apis(id, endpoint, clone_id, request_payload, response, tokens_used, provider, created_at)
currency_ledger(id, currency, amount, source_clone, note, created_at)</code></pre>

  <h2>8. REST endpoints</h2>
  <table class="doc-table">
    <thead><tr><th>Method</th><th>URL</th><th>Body / params</th><th>Returns</th></tr></thead>
    <tbody>
      <tr><td>POST</td><td><code class="kbd">api/auth.php?action=signup</code></td><td><code>{email,password,display_name}</code></td><td>session cookie</td></tr>
      <tr><td>POST</td><td><code class="kbd">api/auth.php?action=login</code></td><td><code>{email,password}</code></td><td>session cookie</td></tr>
      <tr><td>POST</td><td><code class="kbd">api/auth.php?action=logout</code></td><td>—</td><td><code>{ok:true}</code></td></tr>
      <tr><td>GET</td><td><code class="kbd">api/auth.php?action=me</code></td><td>—</td><td>current user</td></tr>
      <tr><td>GET</td><td><code class="kbd">api/clones.php</code></td><td>—</td><td>11 personas (public subset)</td></tr>
      <tr><td>POST</td><td><code class="kbd">api/chat.php</code></td><td><code>{clone_id,message}</code></td><td>reply + currency awarded + provider used</td></tr>
      <tr><td>GET</td><td><code class="kbd">api/user_data.php?action=dashboard</code></td><td>—</td><td>SAX score + ledger + recent</td></tr>
      <tr><td>GET</td><td><code class="kbd">api/user_data.php?action=history&amp;clone_id=…</code></td><td>—</td><td>full chat with one persona</td></tr>
      <tr><td>POST</td><td><code class="kbd">api/user_data.php?action=attribute</code></td><td><code>{attribute_key,attribute_value}</code></td><td>upsert profile field</td></tr>
      <tr><td>POST</td><td><code class="kbd">api/user_data.php?action=resume</code></td><td><code>{filename,file_content}</code></td><td>store resume text</td></tr>
      <tr><td>POST</td><td><code class="kbd">api/user_data.php?action=feedback</code></td><td><code>{turn_id,feedback:-1|0|1}</code></td><td>—</td></tr>
    </tbody>
  </table>

  <h2>9. Nginx vhost (production)</h2>
<pre class="code-block"><code>server {
    listen 443 ssl http2;
    server_name 2030b.com;
    root /var/www/2030b.com;
    index index.html index.php;

    # Static site (W5D + 2030B docs)
    location / { try_files $uri $uri/ /index.html; }

    # SelfAI SaaS
    location /selfai/ {
        try_files $uri $uri/ /selfai/index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Protect secrets and DB
    location ~ /selfai/(database|api/\.env) { deny all; return 404; }
}</code></pre>

  <h2>10. Quick smoke test</h2>
<pre class="code-block"><code># 1. Sign up
curl -c jar -X POST https://2030b.com/selfai/api/auth.php?action=signup \
     -H "Content-Type: application/json" \
     -d '{"email":"demo@2030b.com","password":"changeme","display_name":"Demo"}'

# 2. Talk to SelfAI-W (wisdom)
curl -b jar -X POST https://2030b.com/selfai/api/chat.php \
     -H "Content-Type: application/json" \
     -d '{"clone_id":"selfai_w_wiser","message":"Should I take this job offer?"}'

# 3. Get dashboard
curl -b jar "https://2030b.com/selfai/api/user_data.php?action=dashboard"</code></pre>

  <h2>11. Customizing personas</h2>
  <p>Edit <code class="kbd">selfai/api/config.json</code>. Each entry needs:
  <code class="kbd">id, code, name, project, dimension, currency, currency_long, ayah, color, icon, description, admin_prompt, system_prompt</code>.
  The chat endpoint will pick up changes immediately — no rebuild step.</p>

  <h2>12. Security notes</h2>
  <ul>
    <li>Sessions are PHP cookies named <code class="kbd">selfai_sid</code>; serve only over HTTPS in production.</li>
    <li>Passwords are stored as bcrypt hashes (<code class="kbd">password_hash</code> / <code class="kbd">password_verify</code>).</li>
    <li>Each user's data lives in <em>their own</em> SQLite file — cross-user reads are physically impossible at the storage layer.</li>
    <li>API keys are read from <code class="kbd">selfai/api/.env</code> only; never embed them in JS bundles.</li>
    <li>Deny <code class="kbd">/selfai/database</code> and <code class="kbd">/selfai/api/.env</code> at the web-server layer.</li>
  </ul>

  <h2>13. License & credit</h2>
  <p class="text-sm text-slate-400">SelfAI is the application layer of the 2030B Ecosystem. The first brand it serves is <strong>2030B</strong>. The vision and seven principles of stewardship are credited to Maher and to God first.</p>
  <blockquote class="quote">
    "I am a servant — not a fixer. I do not judge anyone. I do not classify anyone. I serve every one. Credit is to God first — then to every human who decides to take their own hand toward what God created them to be." — Maher
  </blockquote>
</section>
