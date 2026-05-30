# ReviewIQ

<p align="center">
    <img src="https://img.shields.io/badge/PHP-8.5+-777BB4?style=flat&logo=php&logoColor=white" alt="PHP Version">
    <img src="https://img.shields.io/badge/Laravel-13-FF2D20?style=flat&logo=laravel&logoColor=white" alt="Laravel Version">
    <img src="https://img.shields.io/badge/React-18-61DAFB?style=flat&logo=react&logoColor=white" alt="React Version">
    <img src="https://img.shields.io/badge/Inertia-3-FFFFFF?style=flat&logoColor=white" alt="Inertia Version">
    <img src="https://img.shields.io/badge/CI-Passing-28A745?style=flat&logo=githubactions&logoColor=white" alt="CI">
</p>

<p align="center">
    <a href="https://github.com/moemadeldin/ReviewIQ/actions/workflows/ci.yml">
        <img src="https://github.com/moemadeldin/ReviewIQ/actions/workflows/ci.yml/badge.svg" alt="CI">
    </a>
</p>

ReviewIQ is an AI-powered pull request review tool that leverages LLMs to analyze code changes and provide intelligent feedback in real-time.

## Features

- **AI Code Review** — Automated code analysis using DeepSeek V4 Flash via OpenRouter with structured JSON output (score, issues, highlights, recommendation)
- **Real-time Streaming** — Live review progress via WebSocket (Reverb) with per-chunk streaming
- **GitHub App Authentication** — Authenticates as a GitHub App bot using JWT + installation tokens with automatic refresh on 401
- **Webhook Integration** — Auto-detects new and updated PRs via GitHub webhooks (opened/synchronize)
- **PR Description Context** — AI reviews include PR description for richer analysis
- **Manual Re-review** — One-click re-review button from the review detail page
- **In-App Notifications** — Get notified in real-time when a review completes
- **Workspace Management** — Multi-tenant support with team collaboration, roles, and invitations
- **Individual Comment Fallback** — Falls back to posting comments one-by-one if batch review is rejected (422), skipping problematic paths
- **Automatic Retry Scheduler** — Retries failed/pending reviews every 5 minutes with a 10-minute cooldown
- **JSON Repair & Sanitization** — Handles malformed AI responses (trailing commas, missing quotes, stray characters) and validates severity levels

## Tech Stack

- **Backend**: Laravel 13, PHP 8.5+
- **Frontend**: React 18, Inertia 3, TypeScript
- **UI**: Shadcn/UI, Tailwind CSS
- **AI**: DeepSeek V4 Flash (via OpenRouter)
- **Real-time**: Laravel Reverb (WebSocket)
- **Queue**: Laravel Queue with database driver
- **Cache**: Redis (via predis)
- **Database**: PostgreSQL

## Getting Started

### Prerequisites

- PHP 8.5+
- Node.js 20+
- Composer
- Redis 6+
- PostgreSQL
- OpenRouter API key (free DeepSeek V4 Flash)
- GitHub OAuth App (Client ID + Secret)
- GitHub App (App ID, Installation ID, and private key)

### Installation

```bash
# Clone the repository
git clone https://github.com/yourusername/ReviewIQ.git
cd ReviewIQ

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy environment file
cp .env.example .env

# Configure your environment variables (see Configuration section below)

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Build frontend
npm run build
```

### Running the Application

```bash
# Start all services (servers, queue, reverb, vite)
composer dev

# Or manually:
php artisan serve
php artisan queue:listen
php artisan reverb:start
npm run dev
```

### Testing

```bash
# Run the full test suite
php artisan test

# Run a specific test file
php artisan test --filter=RepositoryControllerTest
```

## Configuration

### Environment Variables

```env
# Application
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=reviewiq
DB_USERNAME=postgres
DB_PASSWORD=

# Cache (Redis)
CACHE_STORE=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

# OpenRouter AI (DeepSeek V4 Flash)
OPENROUTER_API_KEY=your_openrouter_api_key
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1/
OPENROUTER_MODEL=deepseek/deepseek-v4-flash:free

# GitHub OAuth
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_WEBHOOK_SECRET=

# GitHub App (for PR review bot authentication)
GITHUB_APP_ID=3912217
GITHUB_APP_INSTALLATION_ID=
GITHUB_APP_PRIVATE_KEY_PATH=storage/oauth/reviewiq-pr-reviewer.pem

# GitHub Webhook URL (use ngrok URL for local development)
GITHUB_WEBHOOK_URL=https://your-domain.com

# Reverb (WebSocket)
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=
REVERB_PORT=
```

### Webhook Setup

For GitHub to send pull request events to your local environment, you need a public URL. Use [ngrok](https://ngrok.com) or a similar tunnel:

```bash
ngrok http 8000
```

Set the resulting URL as `GITHUB_WEBHOOK_URL` in `.env`:

```env
GITHUB_WEBHOOK_URL=https://your-ngrok-id.ngrok-free.dev
```

Configure your GitHub repository webhook:
- **Payload URL**: `https://your-domain.com/api/v1/webhooks/github`
- **Content type**: `application/json`
- **Secret**: Set `GITHUB_WEBHOOK_SECRET` in `.env`
- **Events**: `Pull requests`

### Custom Review Rules

Repositories can have custom review rules configured via the UI or API.

## Usage

1. **Connect GitHub Account** - Sign in via GitHub OAuth (`/auth/github`)
2. **Create a Workspace** - Set up a workspace for your team
3. **Add Repository** - Connect a GitHub repo from the Repositories page
4. **Configure Rules** - Set custom review rules per repository
5. **Create PR** - Open a pull request to trigger automatic review
6. **View Results** - See real-time streaming analysis and final review

## Commands

```bash
# Retry failed/pending reviews (runs automatically every 5 minutes)
php artisan reviews:retry

# Clear stale reviewing PRs stuck longer than 10 minutes
php artisan reviews:retry

# Inspect or run ad-hoc code
php artisan tinker
```

## API Endpoints

- `POST /api/v1/webhooks/github` - GitHub webhook receiver (PR events)
- `GET /auth/github` - Redirect to GitHub OAuth authorization
- `GET /auth/github/callback` - GitHub OAuth callback handler
- `GET /broadcasting/auth` - WebSocket authentication

## License

MIT License - see [LICENSE](LICENSE) for details.
