# PodCheck — Podcast RSS Feed Health Checker

## What It Is

A free web tool where podcasters paste their RSS feed URL and get an instant health report: validation against Apple/Spotify/Google specs, SEO scoring for titles and descriptions, artwork checks, and actionable fix suggestions.

Think of it as **Google PageSpeed Insights, but for podcast feeds.**

---

## Tech Stack

| Layer | Choice | Why |
|-------|--------|-----|
| Backend | Laravel 12 | Latest version, shows you stay current |
| Frontend | Blade + Alpine.js + Tailwind CSS 4 | Fast to build, no SPA complexity |
| XML Parsing | `simplepie/simplepie` or built-in PHP `SimpleXML` | Battle-tested RSS parsing |
| HTTP Client | Laravel HTTP facade (Guzzle) | Fetching remote feeds |
| Cache | File cache for MVP (Redis later) | Cache parsed results so repeat checks are instant |
| Testing | Pest PHP | Modern, expressive PHP testing |
| Deployment | Laravel Forge / DigitalOcean or Railway | Simple, cheap, fast |
| Domain | podcheck.dev or feedcheck.app (or similar) | Memorable, professional |

---

## Features (MVP — ship in ~2 weeks)

### 1. Feed Fetcher
- Accept URL input
- Fetch RSS XML with proper error handling (timeouts, redirects, SSL issues)
- Detect feed format (RSS 2.0, Atom, etc.)

### 2. Core Validation Engine
Run checks against Apple/Spotify/Google requirements:

**Channel-Level Checks:**
- `<itunes:image>` exists and artwork meets size requirements (min 1400x1400, max 3000x3000)
- `<itunes:category>` is present and uses valid Apple category taxonomy
- `<itunes:explicit>` tag present
- `<itunes:author>` present
- `<itunes:owner>` with `<itunes:email>` present
- `<language>` tag present and valid
- `<link>` to website present
- `<description>` / `<itunes:summary>` present and within length limits
- Feed title present and not excessively long

**Episode-Level Checks (sample first 10 episodes):**
- `<enclosure>` present with valid `type` (audio/mpeg, audio/x-m4a, etc.)
- `<enclosure>` URL is reachable (HEAD request)
- `<itunes:duration>` present
- `<guid>` present and unique across episodes
- `<pubDate>` present and valid RFC 2822 format
- `<itunes:title>` or `<title>` present
- Episode descriptions present
- `<itunes:episode>` and `<itunes:season>` (optional but noted)

### 3. SEO Scorer
- Title length analysis (too short / too long / optimal)
- Description keyword density and length
- Episode title patterns (are they descriptive or just "Episode 47"?)
- Show notes / description quality scoring

### 4. Results Dashboard
- Overall health score (0-100) with color badge
- Category breakdown: Compliance, SEO, Technical, Best Practices
- Each check as pass/warn/fail with explanation and fix suggestion
- Shareable results URL (e.g., podcheck.dev/report/abc123)

### 5. Artwork Analyzer
- Fetch artwork image from `<itunes:image>`
- Check dimensions (min/max)
- Check file size
- Check format (JPEG or PNG required by Apple)

---

## Folder Structure

```
podcheck/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── FeedCheckController.php        # Handle form submission + display results
│   ├── Services/
│   │   ├── FeedFetcher.php                    # Fetch and parse RSS XML
│   │   ├── FeedValidator.php                  # Orchestrator — runs all check groups
│   │   ├── Checks/
│   │   │   ├── CheckInterface.php             # Contract: name, run, severity
│   │   │   ├── CheckResult.php                # Value object: status, message, suggestion
│   │   │   ├── ChannelChecks/
│   │   │   │   ├── ArtworkCheck.php
│   │   │   │   ├── CategoryCheck.php
│   │   │   │   ├── ExplicitTagCheck.php
│   │   │   │   ├── AuthorCheck.php
│   │   │   │   ├── OwnerEmailCheck.php
│   │   │   │   ├── LanguageCheck.php
│   │   │   │   ├── WebsiteLinkCheck.php
│   │   │   │   └── DescriptionCheck.php
│   │   │   └── EpisodeChecks/
│   │   │       ├── EnclosureCheck.php
│   │   │       ├── GuidCheck.php
│   │   │       ├── PubDateCheck.php
│   │   │       ├── DurationCheck.php
│   │   │       ├── TitleCheck.php
│   │   │       └── DescriptionCheck.php
│   │   ├── Scoring/
│   │   │   ├── HealthScorer.php               # Calculate overall + category scores
│   │   │   └── SeoScorer.php                  # Title/description SEO analysis
│   │   └── ArtworkAnalyzer.php                # Image dimension/format/size checks
│   └── Models/
│       └── FeedReport.php                     # Store results for shareable URLs
├── resources/
│   └── views/
│       ├── layouts/
│       │   └── app.blade.php
│       ├── home.blade.php                     # Landing page with URL input
│       └── report.blade.php                   # Results dashboard
├── routes/
│   └── web.php
├── database/
│   └── migrations/
│       └── create_feed_reports_table.php
└── tests/
    ├── Unit/
    │   ├── FeedFetcherTest.php
    │   ├── ChannelChecks/                     # One test per check
    │   └── EpisodeChecks/
    └── Feature/
        └── FeedCheckFlowTest.php
```

---

## Database (minimal)

**feed_reports**
```
id               - ulid
feed_url         - string
feed_title       - string (nullable)
overall_score    - integer (0-100)
results_json     - json (full check results)
created_at       - timestamp
slug             - string (unique, for shareable URL)
```

One table. No auth, no users.

---

## TASK BOARD

Every task is 30-90 minutes of work. Do them in order. Each one = a commit.

### Phase 1: Project Setup (Day 1)

- [ ] **TASK 1** — Create fresh Laravel 12 project, push to public GitHub repo
  - `laravel new podcheck`
  - Init git, create GitHub repo, push initial commit
  - *Commit: "Initial Laravel 12 project scaffold"*

- [ ] **TASK 2** — Set up Tailwind CSS + basic layout
  - Install Tailwind via Vite
  - Create `layouts/app.blade.php` with basic shell (nav, footer, main content area)
  - Dark/professional color scheme
  - *Commit: "Add Tailwind and base layout"*

- [ ] **TASK 3** — Create landing page with URL input form
  - `home.blade.php` with centered input field and "Check Feed" button
  - Simple hero text: "Check your podcast RSS feed health"
  - Wire up route: `GET /` → shows form, `POST /check` → processes (just dd() for now)
  - *Commit: "Add landing page with feed URL input form"*

- [ ] **TASK 4** — Create FeedReport model + migration
  - Migration with the schema above
  - Model with `$casts = ['results_json' => 'array']`
  - Add slug generation (use Str::random or ulid)
  - *Commit: "Add FeedReport model and migration"*

---

### Phase 2: Feed Fetching & Parsing (Day 2-3)

- [ ] **TASK 5** — Create FeedFetcher service
  - `app/Services/FeedFetcher.php`
  - Method: `fetch(string $url): SimpleXMLElement`
  - Use Laravel HTTP client with 10s timeout
  - Handle errors: invalid URL, timeout, non-XML response, 404
  - Return parsed XML or throw descriptive exception
  - *Commit: "Add FeedFetcher service with error handling"*

- [ ] **TASK 6** — Write tests for FeedFetcher
  - Test with a valid RSS feed fixture (save a real feed XML as a test fixture)
  - Test timeout handling
  - Test invalid URL handling
  - Test non-XML response handling
  - *Commit: "Add FeedFetcher unit tests"*

- [ ] **TASK 7** — Wire up FeedCheckController basic flow
  - `POST /check` → validate URL → fetch feed → store basic report → redirect to `/report/{slug}`
  - `GET /report/{slug}` → show report page (just dump raw data for now)
  - *Commit: "Add basic check flow: fetch, store, redirect to report"*

---

### Phase 3: Validation Engine (Day 3-5)

- [ ] **TASK 8** — Create CheckInterface and FeedValidator orchestrator
  - `CheckInterface`: `name(): string`, `run(SimpleXMLElement $feed): CheckResult`, `severity(): string`
  - `CheckResult` value object: `status` (pass/warn/fail), `message`, `suggestion`
  - `FeedValidator`: accepts array of checks, runs all, collects results
  - *Commit: "Add check interface and validator orchestrator"*

- [ ] **TASK 9** — Implement ArtworkCheck
  - Check `<itunes:image>` href exists
  - Fetch image via HEAD request, check content-type
  - Check dimensions (use `getimagesize()` with the URL)
  - Pass: 1400x1400–3000x3000, JPEG or PNG. Warn: outside range. Fail: missing
  - *Commit: "Add channel artwork validation check"*

- [ ] **TASK 10** — Implement CategoryCheck
  - Check `<itunes:category>` exists
  - Validate against Apple's official category list (hardcode as config array)
  - Check for subcategory
  - *Commit: "Add iTunes category validation check"*

- [ ] **TASK 11** — Implement remaining channel checks (batch)
  - ExplicitTagCheck, AuthorCheck, OwnerEmailCheck, LanguageCheck, WebsiteLinkCheck, DescriptionCheck
  - These are all simple presence + format checks — batch them together
  - Write one test per check
  - *Commit: "Add all channel-level validation checks"*

- [ ] **TASK 12** — Implement episode-level checks
  - EnclosureCheck (present, valid type, URL reachable via HEAD)
  - GuidCheck (present, unique across episodes)
  - PubDateCheck (present, valid RFC 2822)
  - DurationCheck (present)
  - TitleCheck (present, not just "Episode N")
  - Episode DescriptionCheck (present, minimum length)
  - Run against first 10 episodes only
  - *Commit: "Add episode-level validation checks"*

- [ ] **TASK 13** — Wire validator into controller flow
  - After fetching feed, run FeedValidator with all checks
  - Store results in FeedReport `results_json`
  - *Commit: "Integrate validation engine into check flow"*

---

### Phase 4: Scoring (Day 5-6)

- [ ] **TASK 14** — Build HealthScorer
  - Input: array of CheckResults
  - Calculate overall score: 100 minus weighted deductions (fail = -10, warn = -3)
  - Calculate category scores: Compliance, Technical, Best Practices
  - Return structured score object
  - *Commit: "Add health scoring engine"*

- [ ] **TASK 15** — Build SeoScorer
  - Analyze show title: length (sweet spot 30-60 chars), keyword stuffing detection
  - Analyze show description: length, keyword presence
  - Analyze episode titles: descriptive vs generic pattern detection
  - Return SEO score + specific suggestions
  - *Commit: "Add SEO scoring for titles and descriptions"*

- [ ] **TASK 16** — Store scores in report and test full pipeline
  - Update FeedReport to include `overall_score` from HealthScorer
  - Write a feature test: submit URL → get report → verify scores make sense
  - *Commit: "Integrate scoring into report pipeline with feature test"*

---

### Phase 5: Results UI (Day 7-9)

- [ ] **TASK 17** — Design the report page header
  - Show podcast title, artwork thumbnail (if available), feed URL
  - Big circular score badge (color-coded: green 80+, yellow 50-79, red <50)
  - Overall status message
  - *Commit: "Add report page header with score badge"*

- [ ] **TASK 18** — Build check results list UI
  - Group by category (Compliance, SEO, Technical, Best Practices)
  - Each check shows: status icon, check name, message
  - Expandable detail with fix suggestion (Alpine.js)
  - *Commit: "Add check results list with expandable details"*

- [ ] **TASK 19** — Add category score cards
  - Row of 4 cards: Compliance, SEO, Technical, Best Practices
  - Each with mini score and pass/warn/fail count
  - *Commit: "Add category score breakdown cards"*

- [ ] **TASK 20** — Add episode sampling summary
  - Section showing which episodes were checked
  - Common issues across episodes
  - *Commit: "Add episode check summary section"*

- [ ] **TASK 21** — Polish landing page
  - "How it works" section (3 steps)
  - Example report link
  - Professional design
  - *Commit: "Polish landing page with how-it-works and example"*

---

### Phase 6: Polish & Ship (Day 10-12)

- [ ] **TASK 22** — Add loading state
  - After form submit, show progress indicator (Alpine.js)
  - *Commit: "Add loading state for feed checking"*

- [ ] **TASK 23** — Add error handling UI
  - Invalid URL → inline form error
  - Feed unreachable → friendly error page
  - Non-podcast feed → clear message
  - *Commit: "Add user-facing error handling"*

- [ ] **TASK 24** — Add caching
  - Cache feed results by URL for 1 hour
  - If cached report exists, redirect to it
  - *Commit: "Add result caching by feed URL"*

- [ ] **TASK 25** — Add basic meta tags + OG image
  - Dynamic OG title: "PodCheck Report: [Podcast Name] — Score: 85/100"
  - *Commit: "Add meta tags and OG data for shareable reports"*

- [ ] **TASK 26** — Write README
  - What it is, tech stack, how to run locally, screenshots
  - Include "Live Demo" link
  - *Commit: "Add comprehensive README with screenshots"*

- [ ] **TASK 27** — Deploy
  - Set up on Railway / Forge / DigitalOcean
  - Point domain, verify production works
  - *Commit: "Production deployment"*

- [ ] **TASK 28** — Launch post
  - LinkedIn post (tie to Castos experience)
  - Share in Laravel Discord, Laracasts, r/podcasting, r/laravel
  - Post on Twitter/X

---

## After MVP (nice-to-haves for v1.1)

- [ ] PDF export of report
- [ ] Compare two feeds side by side
- [ ] Historical tracking (check same feed over time)
- [ ] Spotify-specific checks
- [ ] YouTube Podcasts compatibility checks
- [ ] API endpoint for integrations
- [ ] Embeddable badge: "Feed Health: 95/100"

---

## Daily Build Plan

| Day | Focus | Tasks | End-of-day goal |
|-----|-------|-------|-----------------|
| Mon (Day 1) | Setup | Tasks 1-4 | Project exists, deploys locally, has landing page |
| Tue (Day 2) | Fetching | Tasks 5-7 | Can paste URL and get parsed feed data |
| Wed (Day 3) | Checks pt.1 | Tasks 8-10 | Validation engine running with first checks |
| Thu (Day 4) | Checks pt.2 | Tasks 11-12 | All channel + episode checks working |
| Fri (Day 5) | Integration | Tasks 13-14 | Full pipeline: URL → checks → scores → stored |
| Mon (Day 6) | Scoring | Tasks 15-16 | SEO scoring done, full pipeline tested |
| Tue (Day 7) | UI pt.1 | Tasks 17-18 | Report page looks good with real data |
| Wed (Day 8) | UI pt.2 | Tasks 19-21 | Full results UI + polished landing page |
| Thu (Day 9) | Polish | Tasks 22-24 | Loading states, errors, caching |
| Fri (Day 10) | Ship | Tasks 25-28 | Deployed, README done, launch post out |

---

## The Rule

**Every day, at least one commit gets pushed.** The GitHub contribution graph tells a story. Make it a green one.
