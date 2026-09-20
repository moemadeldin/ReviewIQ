# ReviewIQ

<p align="center">
    <img src="https://img.shields.io/badge/PHP-8.5+-777BB4?style=flat&logo=php&logoColor=white" alt="PHP Version">
    <img src="https://img.shields.io/badge/Laravel-13-FF2D20?style=flat&logo=laravel&logoColor=white" alt="Laravel Version">
    <img src="https://img.shields.io/badge/React-19-61DAFB?style=flat&logo=react&logoColor=white" alt="React Version">
    <img src="https://img.shields.io/badge/Inertia-3-FFFFFF?style=flat&logoColor=white" alt="Inertia Version">
    <img src="https://img.shields.io/badge/TypeScript-6-3178C6?style=flat&logo=typescript&logoColor=white" alt="TypeScript Version">
    <img src="https://img.shields.io/badge/License-MIT-28A745?style=flat" alt="License">
</p>

ReviewIQ is an AI-powered pull request review tool. It watches your GitHub repositories via webhooks, analyzes code changes with an LLM, and posts structured feedback back onto the PR — score, per-issue severity and suggestions, highlights, and a merge recommendation.

## Features

- **AI Code Review** — Structured JSON review via OpenRouter (DeepSeek V4 Flash by default, with automatic fallback models): score + rationale, categorized issues (severity, category, suggestion), highlights, and recommendation
- **Smart Diff Analysis** — Diffs are line-annotated before prompting; every issue is validated against the diff map so inline comments always point at real changed lines (invalid ones fall back to the summary)
- **Inline Comments on GitHub** — Issues are posted as review comments on the PR, with automatic individual-comment fallback if a batched post is rejected (HTTP 422)
- **Webhook Integration** — Auto-detects opened and updated PRs via `/api/v1/webhooks/github` (HMAC-verified); a push arriving mid-review immediately re-queues a fresh review of the new head
- **GitHub App Authentication** — Fetches diffs and posts comments as the bot using JWT + installation tokens, with automatic token refresh on 401
- **GitHub OAuth Sign-In** — Login and workspace account linking through GitHub OAuth (Socialite)
- **Manual & Automatic Re-review** — One-click re-review from the review page, plus a `reviews:retry` scheduler that retries pending/failed reviews and resets stale "reviewing" PRs (every 5 minutes)
- **Custom Review Rules** — Per-repository custom rules injected into the AI prompt
- **Multi-tenant Workspaces** — Team workspaces with roles, member management, and email invitations
- **In-App Notifications** — Real-time notification bell (Laravel Reveb + Echo) when a review completes, with deep links to the review page
- **Full Authentication** — Fortify-based headless auth: registration, login, password reset, email verification, and two-factor authentication

## Tech Stack

- **Backend**: Laravel 13, PHP 8.5+, Laravel Fortify (auth), Laravel Socialite (GitHub OAuth), Laravel Wayfinder (typed routes)
- **Frontend**: React 19, Inertia 3, TypeScript, Tailwind CSS 4, shadcn/ui (Radix UI), lucide-react
- **Build tooling**: Vite (via vite-plus / `vp`), Bun
- **AI**: OpenRouter — DeepSeek V4 Flash with configurable fallback models
- **Real-time**: Laravel Reverb (WebSocket) + Laravel Echo (react)
- **Queue**: Laravel Queue (database driver) with retries and backoff
- **Database**: SQLite by default; PostgreSQL supported
- **Testing**: Pest 5, Larastan (PHPStan), Pint, Rector, `tsc --noEmit`, enhanced-compiler/oxc linting

## Getting Started

### Prerequisites

- PHP 8.5+
- Composer
- Bun (or Node.js 20+ / npm)
- OpenRouter API key
- GitHub OAuth App (Client ID, Client Secret, redirect URI)
- GitHub App (App ID, Installation ID, and a PEM private key) — used to read diffs and post comments as the bot

### Installation

Fastest path (uses the `composer setup` script, which assumes Bun):

```bash
git clone https://github.com/moemadeldin/ReviewIQ.git
cd ReviewIQ

composer setup
# or, manually:
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
bun install
bun run build
```

### Running the Application

```bash
# Start everything: web server, queue worker, Vite, Reverb, and the scheduler
composer dev

# Or manually, in separate terminals:
php artisan serve
php artisan queue:listen --tries=3 --timeout=600 --sleep=3
php artisan reverb:start
php artisan schedule:work
bun run dev
```

### Testing

```bash
# Run the full Pest suite
php artisan test

# Run a single test file
php artisan test --filter=GitHubControllerTest

# Static analysis + type checks
composer test:types        # PHPStan + tsc --noEmit

# Lint (Pint, Rector, vp fmt, vp lint)
composer test:lint

# Type coverage and unit coverage gates
composer test:type-coverage
composer test:unit
```

## Configuration

### Environment Variables

```env
# Application
APP_URL=https://your-domain.com
APP_NAME=ReviewIQ

# Database (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=reviewiq
DB_USERNAME=
DB_PASSWORD=

# Queue, cache, session
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# OpenRouter (DeepSeek V4 Flash via OpenRouter)
OPENROUTER_API_KEY=your_openrouter_api_key
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1/
OPENROUTER_MODEL=deepseek/deepseek-v4-flash-0731:free
OPENROUTER_TEMPERATURE=0.2
OPENROUTER_MAX_TOKENS=12000
OPENROUTER_TIMEOUT=600
OPENROUTER_FALLBACK_MODELS=qwen/qwen3.8-27b:free,nvidia/nemotron-3-super-120b-a12b:free

# Prompt tuning
PROMPT_MAX_DIFF_CHARS=100000
PROMPT_IGNORE_PATTERNS=

# GitHub OAuth
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URI=https://your-domain.com/auth/github/callback
GITHUB_WEBHOOK_SECRET=

# GitHub App (for PR review bot authentication)
GITHUB_APP_ID=
GITHUB_APP_INSTALLATION_ID=
GITHUB_APP_PRIVATE_KEY_PATH=

# GitHub Webhook payload URL (use an ngrok URL for local development)
GITHUB_WEBHOOK_URL=https://your-domain.com

# Reverb (WebSocket real-time notifications)
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

### Webhook Setup

For GitHub to send pull request events to your local machine, expose the app with a tunnel:

```bash
ngrok http 8000
```

Set `GITHUB_WEBHOOK_URL` in `.env` to the resulting URL, then configure your repository webhook on GitHub:

- **Payload URL**: `https://your-domain.com/api/v1/webhooks/github`
- **Content type**: `application/json`
- **Secret**: set `GITHUB_WEBHOOK_SECRET` in `.env`
- **Events**: `Pull requests` (opened / synchronize)

### Custom Review Rules

Repositories can have custom review rules configured from the UI; they are injected into the review prompt.

## Usage

1. **Sign in** — Create an account or sign in via GitHub OAuth (`/auth/github`)
2. **Create a Workspace** — Set up a workspace for your team and invite members
3. **Connect a Repository** — Add a GitHub repo from the Repositories page
4. **Configure Rules** — Optional per-repo custom review rules
5. **Open a Pull Request** — The webhook queues an automatic review
6. **View Results** — See the score, issues, and highlights on the review page; issues are also posted inline on the PR
7. **Re-review** — Click “Re-review” if you push more commits

## Commands

```bash
# Retries pending/failed reviews and resets stale "reviewing" PRs (>10 min)
# Runs automatically every 5 minutes via the scheduler
php artisan reviews:retry

# Inspect or run ad-hoc code
php artisan tinker
```

## API Endpoints

- `POST /api/v1/webhooks/github` — GitHub webhook receiver (HMAC-verified pull request events)
- `GET /auth/github` — Redirect to GitHub OAuth authorization
- `GET /auth/github/callback` — GitHub OAuth callback handler
- `GET /broadcasting/auth` — WebSocket (Reverb) authentication
- `GET /notifications`, `PATCH /notifications/{notification}/read`, `PATCH /notifications/read-all` — in-app notifications
- `GET /invitations/{token}/accept`, `POST /invitations/{token}/accept` — workspace invitation acceptance
- `GET /workspaces/{workspace}/reviews/{pullRequest}/data` — review details

## License

MIT License — see [LICENSE](LICENSE) for details.