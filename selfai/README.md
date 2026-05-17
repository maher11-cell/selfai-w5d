# SelfAI — 2030B Ecosystem (eleven specialized personas)

A full-stack PHP + SQLite SaaS that ships **11 SelfAI clones** aligned with the **2030B Ecosystem** currencies and Qur'anic anchors.

The first brand SelfAI serves is **2030B**: ten human dimensions (mind, conscience, body, imagination, spirit, will, wisdom, justice, freedom, gratitude) plus the eleventh integrator persona, **SelfAI-X**.

| #  | Persona  | 2030B project      | Currency |
|----|----------|--------------------|----------|
| 1  | SelfAI-S | Be Smarter — كن أذكى   | CTC |
| 2  | SelfAI-H | Be Honester — كن أصدق  | TIC |
| 3  | SelfAI-V | Be Healthier — كن أصح  | VTC |
| 4  | SelfAI-C | Be Creater — كن أبدع   | INC |
| 5  | SelfAI-K | Be Kinder — كن أرحم    | SCC |
| 6  | SelfAI-B | Be Braver — كن أشجع    | WPC |
| 7  | SelfAI-W | Be Wiser — كن أحكم     | WDC |
| 8  | SelfAI-J | Be Fairer — كن أعدل    | JEC |
| 9  | SelfAI-F | Be Freer — كن أحرر     | FLC |
| 10 | SelfAI-G | Be Grateful — كن أشكر  | GRC |
| 11 | **SelfAI-X** | **Integrator (Whole Human)** | **SAX** |

## Run locally

```bash
sudo apt-get install -y php-cli php-sqlite3 php-curl
cp selfai/api/.env.example selfai/api/.env
# fill in DEEPSEEK_API_KEY (or any free fallback) in .env
php -S 0.0.0.0:8080 -t .
# open http://localhost:8080/selfai/
```

Visit `index.php?p=docs` for the full installation guide that will live at
**https://2030b.com/selfai**.

## AI providers (in priority order)

1. **DeepSeek V4** — primary (`DEEPSEEK_API_KEY`, model `deepseek-chat`).
2. **OpenRouter** free Llama (`OPENROUTER_API_KEY`).
3. **Groq** free 70B (`GROQ_API_KEY`).
4. **Google Gemini** free (`GEMINI_API_KEY`).
5. **Hugging Face Router** (`HF_TOKEN`).
6. Offline preview mode — kicks in if no keys are configured.

## SelfAI-X score (0–100, tokenizable)

Composite of: interactions 30 %, data richness 20 %, resume uploaded 10 %, clone mastery 20 %, feedback ±1 average 20 %.

Configure all of the above in `selfai/api/config.json`.
