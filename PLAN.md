# PodCheck — Progress Tracker

Track your progress here. Check off tasks as you complete them.
Reference this file at the start of each Cursor session to know where you are.

## Current Status
- **Phase**: 3 — Validation Engine
- **Current Task**: TASK 11
- **Last Completed**: TASK 10

---

## Phase 1: Project Setup
- [x] TASK 1 — Create Laravel 12 project + push to GitHub
- [x] TASK 2 — Tailwind CSS + base layout
- [x] TASK 3 — Landing page with URL input form
- [x] TASK 4 — FeedReport model + migration

## Phase 2: Feed Fetching & Parsing
- [x] TASK 5 — FeedFetcher service
- [x] TASK 6 — FeedFetcher tests
- [x] TASK 7 — FeedCheckController basic flow

## Phase 3: Validation Engine
- [x] TASK 8 — CheckInterface + FeedValidator orchestrator
- [x] TASK 9 — ArtworkCheck
- [x] TASK 10 — CategoryCheck
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
- **TASK 7**: Wired up FeedCheckController with constructor-injected FeedFetcher. `POST /check` validates URL, fetches feed, extracts title (RSS 2.0 and Atom), creates FeedReport with basic results JSON, and redirects to `/report/{slug}`. FeedFetchException errors redirect back with user-friendly messages. Added `GET /report/{slug}` route with `show()` method and `report.blade.php` view displaying feed title, URL, metadata, and raw JSON results. Enabled RefreshDatabase for feature tests. Wrote 12 Pest feature tests covering: successful flow (fetch → store → redirect), single report creation, validation errors (missing/invalid/too-long URL), fetch errors (404, non-XML) with redirect-back behavior, input preservation on error, report display, 404 for missing slugs, and graceful handling of null feed titles.
- **TASK 9**: Created `ArtworkCheck` in `app/Services/Checks/ChannelChecks/`. Extracts `<itunes:image>` href from RSS 2.0 and Atom feeds. Validates image via HEAD request for content type (JPEG/PNG required) and `getimagesize()` for dimensions (1400×1400 to 3000×3000, must be square). Returns fail for missing artwork or unsupported format, warn for unreachable images or out-of-range dimensions, pass for valid artwork. Uses constructor-injected `HttpFactory` (no facades). Wrote 11 Pest unit tests covering metadata, missing/empty artwork, invalid formats (GIF, WebP), content type parsing with charset, PNG support, dimension warnings, HEAD failure handling, and fixture extraction.
- **TASK 10**: Created `CategoryCheck` in `app/Services/Checks/ChannelChecks/`. Validates `<itunes:category>` against Apple's full podcast category taxonomy (19 primary categories with subcategories, hardcoded as const array). Extracts primary category and optional nested subcategory from feed XML. Returns fail for missing category or invalid primary category, warn for invalid subcategory or missing subcategory when one is available, pass for valid category/subcategory combinations. Categories with no subcategories (Technology, True Crime, Government, History) pass without requiring one. Handles XML entity encoding (`&amp;` in "Society & Culture", "Health & Fitness"). Wrote 16 Pest unit tests covering metadata, missing/empty categories, invalid categories, subcategory validation, all pass/warn/fail paths, and XML entity handling.
- **TASK 8**: Created the validation engine foundation: `CheckStatus` enum (pass/warn/fail), `CheckResult` value object with static factory methods (`pass()`, `warn()`, `fail()`), status helpers (`isPassing()`, `isWarning()`, `isFailing()`), and `toArray()` for JSON serialization. `CheckInterface` defines the contract: `name()`, `run(SimpleXMLElement)`, `severity()`. `FeedValidator` orchestrator accepts separate `$channelChecks` and `$episodeChecks` arrays, runs channel checks against the full feed and episode checks against individual `<item>` elements (capped at 10 episodes), formats results with check metadata for JSON storage. Supports both RSS 2.0 and Atom feeds with proper namespace handling. Includes `summarize()` static method for counting pass/warn/fail totals. Moved shared test helpers (`fixture()`, `loadFeedFixture()`) to `tests/Pest.php`. Wrote 27 tests: 12 for CheckResult (factory methods, constructors, status helpers, serialization, immutability) and 15 for FeedValidator (empty checks, single/multiple channel checks, episode iteration with title/guid extraction, 10-episode limit, missing title/guid fallbacks, combined channel+episode, summarize, Atom feed support).
