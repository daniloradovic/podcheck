# PodCheck — Progress Tracker

Track your progress here. Check off tasks as you complete them.
Reference this file at the start of each Cursor session to know where you are.

## Current Status
- **Phase**: 2 — Feed Fetching & Parsing
- **Current Task**: TASK 7
- **Last Completed**: TASK 6

---

## Phase 1: Project Setup
- [x] TASK 1 — Create Laravel 12 project + push to GitHub
- [x] TASK 2 — Tailwind CSS + base layout
- [x] TASK 3 — Landing page with URL input form
- [x] TASK 4 — FeedReport model + migration

## Phase 2: Feed Fetching & Parsing
- [x] TASK 5 — FeedFetcher service
- [x] TASK 6 — FeedFetcher tests
- [ ] TASK 7 — FeedCheckController basic flow

## Phase 3: Validation Engine
- [ ] TASK 8 — CheckInterface + FeedValidator orchestrator
- [ ] TASK 9 — ArtworkCheck
- [ ] TASK 10 — CategoryCheck
- [ ] TASK 11 — Remaining channel checks (batch)
- [ ] TASK 12 — Episode-level checks
- [ ] TASK 13 — Wire validator into controller

## Phase 4: Scoring
- [ ] TASK 14 — HealthScorer
- [ ] TASK 15 — SeoScorer
- [ ] TASK 16 — Full pipeline integration test

## Phase 5: Results UI
- [ ] TASK 17 — Report page header + score badge
- [ ] TASK 18 — Check results list UI
- [ ] TASK 19 — Category score cards
- [ ] TASK 20 — Episode sampling summary
- [ ] TASK 21 — Polish landing page

## Phase 6: Polish & Ship
- [ ] TASK 22 — Loading state
- [ ] TASK 23 — Error handling UI
- [ ] TASK 24 — Caching
- [ ] TASK 25 — Meta tags + OG image
- [ ] TASK 26 — README
- [ ] TASK 27 — Deploy
- [ ] TASK 28 — Launch post

---

## Notes
- **TASK 1**: Restarted with clean Laravel 12 install (no starter kit). Added Tailwind CSS 4, Alpine.js, and Pest PHP. Previous Livewire starter kit had unnecessary auth/Flux/Fortify scaffolding.
- **TASK 4**: Created FeedReport model with ULID primary key, JSON casting for results, auto-generated slug on creation, and route-model binding via slug. Migration includes index on feed_url for lookup performance.
- **TASK 5**: Created FeedFetcher service with `fetch(string $url): SimpleXMLElement` method. Uses constructor-injected HTTP client (no facades), 10s timeout, 3 max redirects. Custom FeedFetchException with static factory methods for specific error scenarios (invalid URL, timeout, 404, SSL errors, non-XML response, not an RSS feed). Validates URL scheme, parses XML with libxml error handling, and verifies root element is `rss` or `feed`.
- **TASK 6**: Added 24 Pest unit tests for FeedFetcher covering: valid RSS/Atom feed parsing, channel data accessibility, invalid URL handling (empty, malformed, missing scheme, FTP scheme), HTTP-only URL acceptance, timeout handling (two variants), SSL/certificate errors, generic connection failures, HTTP error responses (404, 403, 500, 503), empty/whitespace-only responses, and non-XML response handling (HTML, JSON, plain text, malformed XML, valid XML that isn't RSS/Atom). Created test fixtures: `valid-rss-feed.xml` (3 episodes with full iTunes metadata) and `valid-atom-feed.xml`.
