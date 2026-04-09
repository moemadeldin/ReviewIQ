# ReviewIQ

<p align="center">
    <img src="https://img.shields.io/badge/PHP-8.5+-777BB4?style=flat&logo=php&logoColor=white" alt="PHP Version">
    <img src="https://img.shields.io/badge/Laravel-13-FF2D20?style=flat&logo=laravel&logoColor=white" alt="Laravel Version">
    <img src="https://img.shields.io/badge/React-18-61DAFB?style=flat&logo=react&logoColor=white" alt="React Version">
    <img src="https://img.shields.io/badge/Inertia-3-FFFFFF?style=flat&logoColor=white" alt="Inertia Version">
</p>

ReviewIQ is an AI-powered pull request review tool that leverages LLMs to analyze code changes and provide intelligent feedback in real-time.

## Features

- **AI Code Review** - Automated code analysis using Groq's LLM
- **Real-time Streaming** - Live review progress via WebSocket (Reverb)
- **GitHub Integration** - Webhook-based PR detection and diff fetching
- **Workspace Management** - Multi-tenant support with team collaboration
- **Structured Feedback** - Score-based reviews with issues, highlights, and recommendations
- **Automatic Retries** - Scheduled jobs for failed/pending reviews

## Tech Stack

- **Backend**: Laravel 13, PHP 8.5+
- **Frontend**: React 18, Inertia 3, TypeScript
- **UI**: Shadcn/UI, Tailwind CSS
- **AI**: Groq (Llama 3.3 70B)
- **Real-time**: Laravel Reverb (WebSocket)
- **Queue**: Laravel Queue with database driver
- **Database**: MySQL/PostgreSQL

## Getting Started

### Prerequisites

- PHP 8.5+
- Node.js 20+
- Composer
- MySQL or PostgreSQL
- Groq API key

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

# Configure your environment variables
# - Database credentials
# - Groq API key (GROQ_API_KEY)
# - GitHub OAuth tokens
# - Reverb credentials

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Build frontend
npm run build
```

### Running the Application

```bash
# Start all services (server, queue, reverb, vite)
composer dev

# Or manually:
php artisan serve
php artisan queue:listen
php artisan reverb:start
npm run dev
```

### Webhook Setup

Configure your GitHub repository webhook:
- URL: `https://your-domain.com/api/v1/webhooks/github`
- Secret: Set `GITHUB_WEBHOOK_SECRET` in `.env`
- Events: `pull_request`

## Usage

1. **Connect GitHub Account** - Sign in via GitHub OAuth
2. **Add Repository** - Enable a repository for reviews
3. **Configure Rules** - Set custom review rules per repository
4. **Create PR** - Open a pull request to trigger automatic review
5. **View Results** - See real-time streaming analysis and final review

## Configuration

### Environment Variables

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=reviewiq
DB_USERNAME=root
DB_PASSWORD=

# Groq AI
GROQ_API_KEY=your_groq_api_key
GROQ_MODEL=llama-3.3-70b-versatile

# GitHub
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_WEBHOOK_SECRET=

# Reverb (WebSocket)
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=
REVERB_PORT=
```

### Custom Review Rules

Repositories can have custom review rules configured via the UI or API.

## Commands

```bash
# Retry failed/pending reviews
php artisan reviews:retry

# Process a specific PR review
php artisan tinker --execute="App\Jobs\ProcessPullRequestReview::dispatch(\$pr);"
```

## API Endpoints

- `POST /api/v1/webhooks/github` - GitHub webhook receiver
- `POST /api/v1/auth/github` - GitHub OAuth login
- `GET /broadcasting/auth` - WebSocket authentication

## License

MIT License - see [LICENSE](LICENSE) for details.