# PodCheck — Podcast RSS Feed Health Checker

**Google PageSpeed Insights, but for podcast feeds.**

PodCheck is a free web tool where podcasters paste their RSS feed URL and get an instant health report: validation against Apple/Spotify/Google specs, SEO scoring for titles and descriptions, artwork checks, and actionable fix suggestions.

> **Live Demo:** [www.podcheck.dev](https://www.podcheck.dev)

---

## Features

- **Feed Validation** — Checks your RSS feed against Apple Podcasts, Spotify, and Google Podcasts directory requirements
- **Health Score** — Overall 0–100 health score with category breakdowns (Compliance, Technical, Best Practices)
- **SEO Analysis** — Title length analysis, description quality scoring, episode title pattern detection
- **Artwork Analyzer** — Validates image dimensions (1400×1400 to 3000×3000), format (JPEG/PNG), and reachability
- **Episode Sampling** — Checks the first 10 episodes for enclosures, GUIDs, pub dates, durations, and more
- **Shareable Reports** — Every report gets a unique URL you can bookmark or share
- **Result Caching** — Repeat checks are instant (cached for 1 hour, with force re-check option)

### What Gets Checked

**Channel-Level (8 checks):**
Artwork, iTunes Category, Explicit Tag, Author, Owner Email, Language, Website Link, Description

**Episode-Level (6 checks per episode):**
Enclosure (media file), GUID uniqueness, Pub Date format, Duration, Title quality, Description presence

**SEO (3 areas):**
Show title length & keyword stuffing, show description quality, episode title descriptiveness

---

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | [Laravel 12](https://laravel.com) (PHP 8.3+) |
| Frontend | [Blade](https://laravel.com/docs/blade) + [Alpine.js](https://alpinejs.dev) + [Tailwind CSS 4](https://tailwindcss.com) |
| Build | [Vite](https://vitejs.dev) |
| Database | SQLite (dev) / MySQL (prod) |
| Cache | File-based (MVP) |
| Testing | [Pest PHP](https://pestphp.com) (335+ tests) |
| Deployment | [Docker](https://www.docker.com) + [Railway](https://railway.com) |

---

## Screenshots

> Visit [www.podcheck.dev](https://www.podcheck.dev) to see it live, or add your own screenshots to `docs/screenshots/` and reference them here.

---

## Deployment

PodCheck is deployed on [Railway](https://railway.com) using a multi-stage Docker build.

**Infrastructure:**
- **App**: Docker container (PHP 8.3 FPM + nginx) on Railway
- **Database**: MySQL on Railway
- **Domain**: [www.podcheck.dev](https://www.podcheck.dev) via GoDaddy DNS

**How it works:**
- The `Dockerfile` uses a two-stage build: Node.js 20 compiles Vite assets, then the PHP 8.3 FPM image serves the app with nginx
- The `docker/entrypoint.sh` runs migrations and caches config/routes/views on each deploy
- Railway environment variables configure the database, logging, and app settings

To deploy your own instance, see the Railway production overrides documented in [`.env.example`](.env.example).

---

## Getting Started

### Prerequisites

- PHP 8.3+
- Composer
- Node.js 20+ and npm
- SQLite (included with most PHP installations)

### Installation

```bash
# Clone the repository
git clone https://github.com/daniloradovic/podcheck.git
cd podcheck

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Set up environment
cp .env.example .env
php artisan key:generate

# Create the SQLite database and run migrations
touch database/database.sqlite
php artisan migrate

# Build frontend assets
npm run build
```

### Running Locally

**Option 1 — Quick start (two terminals):**

```bash
# Terminal 1: Laravel dev server
php artisan serve

# Terminal 2: Vite dev server (for hot-reload)
npm run dev
```

**Option 2 — All-in-one with Composer:**

```bash
composer dev
```

Then open [http://localhost:8000](http://localhost:8000) in your browser.

### Running Tests

```bash
# Run the full test suite
php artisan test

# Run a specific test group
php artisan test --filter=ChannelChecks
php artisan test --filter=EpisodeChecks
php artisan test --filter=FeedCheckFlowTest

# Check code style
./vendor/bin/pint --test
```

---

## Architecture

```
app/
├── Http/Controllers/
│   └── FeedCheckController.php        # Thin controller — form, check, report display
├── Services/
│   ├── FeedFetcher.php                # Fetches and parses RSS XML (with error handling)
│   ├── FeedValidator.php              # Orchestrates all validation checks
│   ├── Checks/
│   │   ├── CheckInterface.php         # Contract: name(), run(), severity()
│   │   ├── CheckResult.php            # Value object: status, message, suggestion
│   │   ├── CheckStatus.php            # Enum: pass, warn, fail
│   │   ├── ChannelChecks/             # 8 channel-level checks
│   │   └── EpisodeChecks/             # 6 episode-level checks
│   └── Scoring/
│       ├── HealthScorer.php           # Overall + category health scores
│       ├── HealthScore.php            # Health score value object
│       ├── SeoScorer.php              # Title/description SEO analysis
│       └── SeoScore.php              # SEO score value object
└── Models/
    └── FeedReport.php                 # Stored report with results JSON + slug
```

### How It Works

1. **Fetch** — User submits an RSS feed URL. `FeedFetcher` retrieves and parses the XML with timeout handling, SSL validation, and format detection.
2. **Validate** — `FeedValidator` runs all 14 checks (8 channel + 6 episode) against the parsed feed. Episode checks run on the first 10 episodes.
3. **Score** — `HealthScorer` calculates a 0–100 health score with weighted deductions (fail = −10, warn = −3) across three categories. `SeoScorer` analyzes title/description quality separately.
4. **Store** — Results are saved as a `FeedReport` with a unique slug for shareable URLs.
5. **Display** — The report page shows an animated score ring, category breakdowns, grouped check results with expandable fix suggestions, and episode sampling details.

---

## Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/your-feature`)
3. Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards
4. Add `declare(strict_types=1);` to every PHP file
5. Write Pest tests for any new functionality
6. Ensure all tests pass (`php artisan test`) and code style is clean (`./vendor/bin/pint --test`)
7. Submit a pull request

---

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
