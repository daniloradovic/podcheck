# PodCheck — Specification

## What It Is

A free web tool where podcasters paste their RSS feed URL and get an instant health report: validation against Apple/Spotify/Google specs, SEO scoring for titles and descriptions, artwork checks, and actionable fix suggestions.

Think of it as **Google PageSpeed Insights, but for podcast feeds.**

---

## Tech Stack

| Layer | Choice |
|-------|--------|
| Backend | Laravel 12 (PHP 8.3+) |
| Frontend | Blade + Alpine.js + Tailwind CSS 4 |
| Database | MySQL (prod), SQLite (dev) |
| Cache | File-based |
| Testing | Pest PHP |
| Deployment | Docker + Railway |
| Domain | podcheck.dev |
| AI | Anthropic API (`claude-haiku-4-5`) via `anthropic-ai/sdk` |

---

## Phase 1 — Complete ✅

Live at [podcheck.dev](https://www.podcheck.dev). 335+ tests passing.

**What's shipped:**
- Feed fetching and XML parsing with error handling
- 14 validation checks (8 channel-level, 6 episode-level)
- SEO scoring for titles and descriptions
- Health scoring 0-100 with category breakdown
- Shareable report URLs
- Result caching (1 hour)
- Docker + Railway deployment

---

## Phase 2 — AI Coach Summary

### The Problem With Phase 1

Static fix suggestions tell users *what* is wrong. They don't explain *why it matters for their specific show*. A true crime podcast and a B2B SaaS podcast get identical suggestions for identical problems — but the context, audience, and stakes are completely different.

### The Solution

One AI call per report. After checks run, Claude reads the results and the actual feed content and returns a **3-sentence personalized summary** specific to that podcast.

**Before (static):**
> "Your description is 45 words. Aim for 150-300 words."

**After (AI):**
> "Your show is about B2B SaaS growth, but your description reads like a generic business podcast — Apple's algorithm can't tell you apart from 10,000 others. Fix your description first: lead with who this is for and what they'll walk away knowing. Your artwork and category are already solid, which puts you ahead of most new shows."

The personalization is the entire value. This is the only thing AI does here that a hardcoded string cannot.

### Scope

Deliberately minimal:
- One prompt class
- One service class
- One endpoint
- One UI element (card at top of report)
- No streaming — plain JSON response
- Fails silently if API is unavailable — core report unaffected

### Architecture

```
app/AI/
├── Prompts/
│   └── CoachSummaryPrompt.php
└── PodcastCoachService.php
```

**CoachSummaryPrompt**

Context passed to the prompt:
- Show name (`feed_title` model field)
- Show description and category (raw values stored in `results_json['metadata']` — added to `FeedCheckController@check` as part of Phase 2 Task 1)
- Overall health score (`results_json['health_score']['overall']`)
- Top 3 failing checks (name + current suggestion text, from `results_json['channel']`)

Output instruction: 3 sentences, plain text, no markdown, specific to this show.

Prompt versioning from day one: `version(): string` returns `v1`. Cache keys include the version. Bumping version auto-invalidates old cached responses without a cache flush.

**PodcastCoachService**

`getSummary(FeedReport $report): ?string`

Cache-first: key is `coach:{version}:{md5(show_title + failing_check_names)}`. TTL 7 days. Cache hit rate expected to be high — many podcasters have similar issues regardless of when they check.

Returns `null` on any API failure. Card is simply hidden. Core report continues to function 100%.

**Endpoint**

`POST /report/{report}/ai/summary` → `{ summary: "..." }` or `{ summary: null }`

Rate limited: 20 requests per IP per hour.

**UI**

Card at the top of the report page, above the score breakdown. Auto-fires on page load via Alpine.js. Three states: loading skeleton, summary text, hidden on failure. No user action required.

### Cost

~$0.0002 per cache miss (Haiku). At 1,000 daily checks with 70% cache hit rate: ~$0.06/day. Effectively free.

### Graceful Degradation

`AI_FEATURES_ENABLED` env flag. If false: card not rendered, no API calls made, zero impact on report functionality.

---

## Phase 3 — Candidates (Decided by Phase 2 Usage Data)

These are ideas, not commitments. Phase 2 usage data decides what's worth building next.

- Historical tracking — check same feed monthly, see score improve over time
- Podcast network bulk checker — check 10+ feeds at once
- Embeddable badge — "Feed Health: 95/100"
- PDF report export
- Compare two feeds side by side
- API endpoint for integrations
- Spotify-specific checks
