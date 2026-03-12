# PodCheck — Build Plan

## The Rule

**Every day, at least one commit gets pushed.** The GitHub contribution graph tells a story. Make it a green one.

---

## Phase 1 — Complete ✅

MVP shipped at [podcheck.dev](https://www.podcheck.dev). 335+ tests passing.

---

## Phase 2 — AI Layer (Lean Scope)

### What We're Actually Building

One AI call per report. After the health check runs, Claude reads the full results and the actual feed content, then writes a **personalized 3-sentence coach summary** specific to that podcast:

1. What's most broken and why it matters *for this show specifically*
2. The single highest-impact fix to do first
3. One encouraging/contextual observation about what's already working

**What bad looks like (what we already have):**
> "Your description is 45 words. Aim for 150-300 words."

**What good looks like (what we're building):**
> "Your show is about B2B SaaS growth, but your description reads like a generic business podcast — Apple's algorithm can't tell you apart from 10,000 others. Fix your description first: lead with who this is for and what they'll walk away knowing. Your artwork and category are already solid, which puts you ahead of most new shows."

That's the whole feature. One prompt, one endpoint, one UI element. No streaming, no panels, no three prompt types.

---

### Why This Is The Right Scope

- Static suggestions already tell users *what* to fix
- This tells them *why it matters for their show* — context static strings can never have
- Personalization is the only thing AI does here that a hardcoded string cannot
- Ships in 3 days, not 10
- One focused LinkedIn post with a clear before/after

---

### Architecture

```
app/AI/
├── Prompts/
│   └── CoachSummaryPrompt.php    # version(), system(), build(array $context)
└── PodcastCoachService.php       # getSummary(FeedReport): ?string
```

One new endpoint: `POST /report/{slug}/ai/summary`
One new UI element: card at top of report page
No streaming. JSON response. Cache-first.

---

### Tasks

#### Day 1 — Backend

- [x] **TASK 1** — Install Anthropic PHP client and configure
  - `composer require "anthropic-ai/sdk"`
  - Add `ANTHROPIC_API_KEY` to `.env.example`
  - Add `ai.php` config: model (`claude-haiku-4-5`), max_tokens, cache_ttl
  - Bind client in `AppServiceProvider`
  - Extend `results_json` in `FeedCheckController@check` to include a `metadata` block with raw feed values needed for the AI prompt:
    ```php
    'metadata' => [
        'show_description' => (string) $channel->description ?? null,
        'show_category'    => (string) $channel->children('itunes', true)->category->attributes()['text'] ?? null,
    ]
    ```
    No migration needed (JSON column). Old reports without the key return `null` from `PodcastCoachService` gracefully.
  - *Commit: "Add Anthropic client configuration"*

- [x] **TASK 2** — Build CoachSummaryPrompt
  - `app/AI/Prompts/CoachSummaryPrompt.php`
  - `version(): string` — start at `v1`
  - `system(): string` — "You are a podcast growth coach. Be specific to this show. Never give generic advice."
  - `build(array $context): string` — context: show name, description, category, score, top 3 failing checks
  - Output instruction in prompt: exactly 3 sentences, plain text, no markdown
  - Unit test: assert `build()` output contains show name from context
  - *Commit: "Add CoachSummaryPrompt v1"*

- [ ] **TASK 3** — Build PodcastCoachService
  - `app/AI/PodcastCoachService.php`
  - `getSummary(FeedReport $report): ?string`
  - Cache key: `coach:v1:{md5(show_title + failing_check_names)}`
  - Cache hit → return string. Miss → call API → cache 7 days → return string
  - On any API failure: return `null` (caller handles gracefully)
  - Feature test with mocked client: miss calls API, hit skips it
  - *Commit: "Add PodcastCoachService with cache-first logic"*

- [ ] **TASK 4** — Endpoint and controller
  - `POST /report/{report}/ai/summary` → `AiSummaryController@generate`
  - Returns `{ summary: "..." }` or `{ summary: null }` on failure
  - Rate limit: 20 requests per IP per hour
  - *Commit: "Add AI summary endpoint with rate limiting"*

---

#### Day 2 — Frontend

- [ ] **TASK 5** — Build the coach summary card
  - Blade partial: `resources/views/partials/coach-summary.blade.php`
  - Position: top of report page, above score breakdown
  - On page load, Alpine.js auto-fires `POST /ai/summary` (no user action needed)
  - Three states: loading skeleton → summary text → hidden (on failure)
  - *Commit: "Add AI coach summary card to report page"*

- [ ] **TASK 6** — Add `AI_FEATURES_ENABLED` env flag
  - If false, card is not rendered, no API calls made
  - Safe on/off switch without a deploy
  - *Commit: "Add AI feature flag"*

---

#### Day 3 — Tune and Ship

- [ ] **TASK 7** — Test with 10 real podcast feeds
  - Range: low score/high score, B2B/entertainment/news, new show/established
  - Is each summary actually specific to that show, or does it feel generic?
  - Refine prompt if needed → bump to v2 → old cache auto-invalidates
  - *Commit: "Refine CoachSummaryPrompt based on real feed testing"*

- [ ] **TASK 8** — Deploy to production
  - Add `ANTHROPIC_API_KEY` to Railway environment
  - Smoke test 3 real feeds live
  - *Commit: "Deploy Phase 2 AI coach summary"*

- [ ] **TASK 9** — Write the LinkedIn post
  - Title: "I added one AI feature to PodCheck. Here's why I didn't build five."
  - Cover: before/after of static vs personalized, why simple scope was the right call, caching strategy, what real feed testing taught you
  - Link to a live example report
  - **This is not optional — the post is part of the deliverable.**

---

### Cost Model

| Scenario | Cost |
|----------|------|
| Cache miss (new feed content) | ~$0.0002 per summary |
| Cache hit (same show checked again) | $0 |
| 1,000 daily checks, 70% cache hit rate | ~$0.06/day |

Effectively free at current scale.

---

### What Comes After

Once this is live you'll have a real AI feature in production, a real architectural decision to discuss in interviews, and real usage data. That data drives Phase 3 — if users engage, expand. If not, you've shipped something clean and learned something real. Either outcome is a better story than a half-built complex feature.

---

## Content Pipeline (LinkedIn)

| Post | Topic |
|------|-------|
| #1 — after Task 9 | Why I built one AI feature instead of five |
| #2 — +1 week | Prompt versioning — the boring thing that saves you at 3am |
| #3 — +2 weeks | Content-hash caching for AI responses in Laravel |
| #4 — +3 weeks | What 10 real podcast feeds taught me about writing good prompts |
