# Japa Coach

**AI IELTS practice for Nigerians preparing to study or work abroad.**

Candidates practise all four IELTS modules and get an estimated band score with specific feedback in minutes, from an AI examiner that marks against the public IELTS band descriptors.

> Japa Coach is not affiliated with IELTS, the British Council, IDP or Cambridge. Band scores are estimates.

---

## Features

**For learners**
- **Speaking**: full mock test (Part 1 → Part 2 cue card with 1-minute prep → linked Part 3) or single-part drills. Answers are recorded in the browser, transcribed, and scored on all four criteria with corrections, better answers and pronunciation tips.
- **Writing**: Academic Task 1 (charts and tables), General Training Task 1 (letters) and Task 2 essays, with timers, live word count, criteria bands, inline corrections and a model answer.
- **Reading & Listening**: full 40-question tests (multiple choice, TRUE/FALSE/NOT GIVEN, YES/NO/NOT GIVEN, gap fill, matching), auto-marked and converted to a band, with explanations. Listening audio is generated with natural British and American voices.
- **Full mock exam**: Listening → Reading → Writing → Speaking, with an overall band.
- **Dashboard**: band per module, progress chart, streaks, days to test date.
- **Study plan**: week-by-week plan built from the test date, target band and weakest modules.
- **Referrals**: invite friends and earn extra speaking mocks.

**For the business**
- **Bachs payments in naira**: Weekly, Monthly and 90-day Exam Sprint plans. Each payment buys days of access, and renewing early stacks the days.
- **Usage limits per plan**, so AI costs stay below revenue.
- **Admin panel**: users, payments, revenue, content management and one-click AI content generation.
- **Emails**: result-ready notifications, study reminders and plan-expiry warnings.

---

## How it works

```
                    ┌─────────────────────────── VPS (Docker) ───────────────────────────┐
 Browser ──HTTPS──▶ │  Caddy ── /            → Vue app (static)                          │
                    │        ── /api, /auth  → Laravel (PHP-FPM) ──────▶ Supabase Postgres │
                    │        ── /storage     → generated audio                            │
                    │                                                                     │
                    │  Queue workers ── default: AI scoring, emails ──────▶ DeepSeek API  │
                    │                └─ speech:  transcription, TTS ──┐                    │
                    │  Speech service (faster-whisper + Kokoro) ◀─────┘                    │
                    │  Scheduler: reminders, audio clean-up                               │
                    └─────────────────────────────────────────────────────────────────────┘
                                         Bachs ──webhook──▶ /api/webhooks/bachs
```

**Speaking pipeline**: each answer is uploaded as soon as it is recorded and transcribed in the background (word timestamps + confidence). Fluency metrics such as speaking rate, long pauses, fillers and unclear words are computed from the timestamps, then DeepSeek scores the transcript against the band descriptors once every answer is ready.

Everything runs on one domain: the frontend and API are separate folders but are served together by Caddy.

---

## Tech stack

| Part | Technology |
|---|---|
| Frontend | Vue 3, TypeScript, Vite, Tailwind CSS v4, Pinia, Chart.js, GSAP |
| API | Laravel 13 (PHP 8.4), Sanctum cookie auth, Socialite (Google), database queue |
| Database | PostgreSQL on Supabase |
| AI scoring & content | DeepSeek (OpenAI-compatible API, JSON mode) |
| Speech | Self-hosted [faster-whisper](https://github.com/SYSTRAN/faster-whisper) (speech-to-text) and [Kokoro](https://github.com/thewh1teagle/kokoro-onnx) ONNX (text-to-speech), FastAPI |
| Payments | Bachs hosted checkout |
| Email | Resend |
| Hosting | Docker Compose + Caddy (automatic HTTPS), sized for a 1 vCPU / 4 GB VPS |

---

## Repository layout

```
japa-coach/
├── api/                  Laravel API
│   ├── app/Services/     Scoring, payments, AI client, speech client, content generation
│   ├── app/Jobs/         Transcription, scoring, audio rendering, content generation
│   ├── config/japa.php   Plans, prices, usage limits, voices
│   ├── database/seeders/content/   Starter questions and tests (JSON)
│   └── tests/            Feature and unit tests
├── frontend/             Vue single-page app (landing page + learner app + admin)
├── speech/               Speech service (FastAPI, faster-whisper, Kokoro)
├── docker/caddy/         Web server config and image (builds the frontend)
├── docker-compose.yml        Production stack
├── docker-compose.local.yml  Local overrides (http://localhost:8088)
├── dev.sh                Run everything locally
└── deploy.sh             Deploy on the VPS
```

---

## Running locally

Requirements: Docker with Compose, plus a Supabase project.

1. Create `api/.env` from the example and fill in your Supabase details (use the **session pooler, port 5432**):
   ```bash
   cp api/.env.example api/.env
   ```
   Set at least `APP_KEY` (`php artisan key:generate` or any `base64:` 32-byte key), the `DB_*` values and `DB_SSLMODE=require`. Add `DEEPSEEK_API_KEY` to enable Speaking/Writing scoring.

2. Start everything:
   ```bash
   ./dev.sh
   ```
   The first build downloads about 2 GB (speech models) and takes a while. The script runs migrations, secures the database, loads the starter content and queues audio rendering.

3. Open **http://localhost:8088**, sign up, then make yourself an admin:
   ```bash
   docker compose -f docker-compose.yml -f docker-compose.local.yml exec app php artisan user:make-admin you@example.com
   ```

Stop with `docker compose -f docker-compose.yml -f docker-compose.local.yml down`.

> `api/.env` points at your real Supabase database, so local sign-ups and attempts are real data.

---

## Deploying (VPS)

1. Point your domain's DNS **A record** at the server and install Docker.
2. Clone the repository and create the two env files:
   ```bash
   cp .env.example .env                              # DOMAIN, SPEECH_SERVICE_TOKEN, WHISPER_MODEL
   cp api/.env.production.example api/.env           # Supabase, Bachs, DeepSeek, Resend, Google
   ```
   `SPEECH_SERVICE_TOKEN` is any random secret (`openssl rand -hex 32`) and must be the same in both files.
   Generate `APP_KEY` with `docker compose run --rm app php artisan key:generate --show`.
3. Deploy:
   ```bash
   ./deploy.sh
   ```
   Caddy obtains an HTTPS certificate automatically. Migrations and database hardening run on start.
4. First time only:
   ```bash
   docker compose exec app php artisan db:seed --force      # starter content
   docker compose exec app php artisan audio:render         # examiner and listening audio
   docker compose exec app php artisan user:make-admin you@example.com
   ```
5. In the Bachs dashboard, set the webhook URL to `https://YOUR_DOMAIN/api/webhooks/bachs`.

Run `./deploy.sh` again for every update.

---

## Configuration

| Variable | Where | Purpose |
|---|---|---|
| `DOMAIN` | `.env` | Public domain; Caddy issues HTTPS for it |
| `SPEECH_SERVICE_TOKEN` | `.env` + `api/.env` | Shared secret between the app and the speech service |
| `WHISPER_MODEL` | `.env` | `base.en` (fast) or `small.en` (more accurate, ~2–3× slower) |
| `DB_*`, `DB_SSLMODE` | `api/.env` | Supabase session pooler connection |
| `DEEPSEEK_API_KEY` | `api/.env` | AI scoring, study plans, content generation |
| `BACHS_SECRET_KEY`, `BACHS_WEBHOOK_SECRET`, `BACHS_CURRENCY` | `api/.env` | Payments (`sk_sandbox_…` keys use the Bachs sandbox) |
| `PLAN_WEEKLY_PRICE`, `PLAN_MONTHLY_PRICE`, `PLAN_SPRINT_PRICE` | `api/.env` | Plan prices |
| `RESEND_API_KEY`, `MAIL_FROM_ADDRESS` | `api/.env` | Email |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | `api/.env` | Google sign-in |

Plan features, usage limits and referral rewards are in `api/config/japa.php`.

---

## Useful commands

Run inside the app container (`docker compose exec app …`) or in `api/`:

| Command | What it does |
|---|---|
| `php artisan content:import` | Load the starter content from `database/seeders/content` |
| `php artisan content:generate reading --count=2 --test-type=general` | Generate new content with DeepSeek (`speaking`, `writing`, `reading`, `listening`) |
| `php artisan audio:render` | Render missing listening and examiner audio |
| `php artisan user:make-admin EMAIL` | Grant admin access |
| `php artisan db:secure` | Enable row level security and block Supabase API roles (runs on every start) |
| `php artisan reminders:send` | Study reminders and plan-expiry emails (scheduled daily) |
| `php artisan speaking:prune-audio` | Delete recordings older than 30 days (scheduled daily) |

---

## Testing

The test suite uses a separate local Postgres database and never touches Supabase:

```bash
docker run -d --name japa-pg -e POSTGRES_USER=japa -e POSTGRES_PASSWORD=japa -e POSTGRES_DB=japa -p 55432:5432 postgres:17-alpine
docker exec japa-pg psql -U japa -c "CREATE DATABASE japa_test;"
cd api && composer install && php artisan test
```

Frontend type-check and build: `cd frontend && npm ci && npm run build`.

---

## Security notes

- Payments are only fulfilled after Bachs' own API confirms them. Callback URL parameters are ignored, and webhooks must carry a valid, recent signature.
- Supabase's auto-generated API cannot read app tables (`php artisan db:secure`).
- Speaking recordings are private to their owner and deleted after 30 days. Transcripts are kept for the report.
- Rate limits protect sign-in, AI scoring, payments and uploads.

---

© Japa Coach. All rights reserved.
