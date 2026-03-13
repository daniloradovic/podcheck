<?php

declare(strict_types=1);

use App\AI\Prompts\CoachSummaryPrompt;

// ──────────────────────────────────────────────────
// version()
// ──────────────────────────────────────────────────

test('version returns v2', function () {
    $prompt = new CoachSummaryPrompt;

    expect($prompt->version())->toBe('v2');
});

// ──────────────────────────────────────────────────
// system()
// ──────────────────────────────────────────────────

test('system prompt instructs specificity and no generic advice', function () {
    $prompt = new CoachSummaryPrompt;

    $system = $prompt->system();

    expect($system)
        ->toContain('podcast growth coach')
        ->toContain('specific')
        ->toContain('niche');
});

// ──────────────────────────────────────────────────
// build()
// ──────────────────────────────────────────────────

test('build output contains the show name', function () {
    $prompt = new CoachSummaryPrompt;

    $result = $prompt->build([
        'show_name' => 'The B2B SaaS Founders Podcast',
        'show_description' => 'Interviews with SaaS founders about growth.',
        'show_category' => 'Business',
        'health_score' => 62,
        'failing_checks' => [],
    ]);

    expect($result)->toContain('The B2B SaaS Founders Podcast');
});

test('build output contains the health score', function () {
    $prompt = new CoachSummaryPrompt;

    $result = $prompt->build([
        'show_name' => 'Tech Talks Weekly',
        'show_description' => null,
        'show_category' => null,
        'health_score' => 45,
        'failing_checks' => [],
    ]);

    expect($result)->toContain('45/100');
});

test('build output contains the show description when provided', function () {
    $prompt = new CoachSummaryPrompt;

    $result = $prompt->build([
        'show_name' => 'My Podcast',
        'show_description' => 'A show about entrepreneurship and mindset.',
        'show_category' => 'Entrepreneurship',
        'health_score' => 80,
        'failing_checks' => [],
    ]);

    expect($result)
        ->toContain('A show about entrepreneurship and mindset.')
        ->toContain('Entrepreneurship');
});

test('build includes top 3 failing checks when provided', function () {
    $prompt = new CoachSummaryPrompt;

    $result = $prompt->build([
        'show_name' => 'Crime Stories',
        'show_description' => 'True crime deep dives.',
        'show_category' => 'True Crime',
        'health_score' => 30,
        'failing_checks' => [
            ['name' => 'Channel Description', 'suggestion' => 'Add a longer description.'],
            ['name' => 'Artwork', 'suggestion' => 'Use a 3000x3000 image.'],
            ['name' => 'Owner Email', 'suggestion' => 'Add an owner email.'],
            ['name' => 'Extra Check', 'suggestion' => 'Should not appear.'],
        ],
    ]);

    expect($result)
        ->toContain('Channel Description')
        ->toContain('Artwork')
        ->toContain('Owner Email')
        ->not->toContain('Extra Check');
});

test('build handles missing optional context keys gracefully', function () {
    $prompt = new CoachSummaryPrompt;

    $result = $prompt->build([
        'show_name' => 'Minimal Show',
    ]);

    expect($result)
        ->toContain('Minimal Show')
        ->toContain('0/100')
        ->toContain('well below average');
});

test('build output instructs exactly 3 sentences', function () {
    $prompt = new CoachSummaryPrompt;

    $result = $prompt->build([
        'show_name' => 'Any Show',
        'health_score' => 70,
        'failing_checks' => [],
    ]);

    expect($result)->toContain('exactly 3 sentences');
});

test('build output instructs plain text no markdown', function () {
    $prompt = new CoachSummaryPrompt;

    $result = $prompt->build([
        'show_name' => 'Any Show',
        'health_score' => 70,
        'failing_checks' => [],
    ]);

    expect($result)->toContain('no markdown');
});

// ──────────────────────────────────────────────────
// v2 — Score labels
// ──────────────────────────────────────────────────

test('build appends excellent label for scores 90 and above', function () {
    $prompt = new CoachSummaryPrompt;

    expect($prompt->build(['show_name' => 'S', 'health_score' => 90, 'failing_checks' => []]))->toContain('excellent');
    expect($prompt->build(['show_name' => 'S', 'health_score' => 100, 'failing_checks' => []]))->toContain('excellent');
});

test('build appends good label for scores 75–89', function () {
    $prompt = new CoachSummaryPrompt;

    expect($prompt->build(['show_name' => 'S', 'health_score' => 75, 'failing_checks' => []]))->toContain('good');
    expect($prompt->build(['show_name' => 'S', 'health_score' => 89, 'failing_checks' => []]))->toContain('good');
});

test('build appends average label for scores 55–74', function () {
    $prompt = new CoachSummaryPrompt;

    expect($prompt->build(['show_name' => 'S', 'health_score' => 55, 'failing_checks' => []]))->toContain('average');
    expect($prompt->build(['show_name' => 'S', 'health_score' => 74, 'failing_checks' => []]))->toContain('average');
});

test('build appends below average label for scores 35–54', function () {
    $prompt = new CoachSummaryPrompt;

    expect($prompt->build(['show_name' => 'S', 'health_score' => 35, 'failing_checks' => []]))->toContain('below average');
    expect($prompt->build(['show_name' => 'S', 'health_score' => 54, 'failing_checks' => []]))->toContain('below average');
});

test('build appends well below average label for scores under 35', function () {
    $prompt = new CoachSummaryPrompt;

    expect($prompt->build(['show_name' => 'S', 'health_score' => 0, 'failing_checks' => []]))->toContain('well below average');
    expect($prompt->build(['show_name' => 'S', 'health_score' => 34, 'failing_checks' => []]))->toContain('well below average');
});

// ──────────────────────────────────────────────────
// v2 — Niche block fallback when description/category are absent
// ──────────────────────────────────────────────────

test('build uses fallback niche instruction when description and category are both absent', function () {
    $prompt = new CoachSummaryPrompt;

    $result = $prompt->build([
        'show_name' => 'Ghost Show',
        'health_score' => 50,
        'failing_checks' => [],
    ]);

    expect($result)->toContain('use only the show name to infer the niche');
});

test('build does not show fallback niche instruction when description is provided', function () {
    $prompt = new CoachSummaryPrompt;

    $result = $prompt->build([
        'show_name' => 'Ghost Show',
        'show_description' => 'A show about real estate investing.',
        'health_score' => 50,
        'failing_checks' => [],
    ]);

    expect($result)->not->toContain('use only the show name to infer the niche');
});

// ──────────────────────────────────────────────────
// v2 — Anti-generic anchor example present
// ──────────────────────────────────────────────────

test('build output contains the wrong vs right specificity anchor example', function () {
    $prompt = new CoachSummaryPrompt;

    $result = $prompt->build([
        'show_name' => 'Any Show',
        'health_score' => 60,
        'failing_checks' => [],
    ]);

    expect($result)
        ->toContain('WRONG')
        ->toContain('RIGHT');
});

// ──────────────────────────────────────────────────
// v2 — Failing checks connect to niche
// ──────────────────────────────────────────────────

test('build failing checks section instructs AI to connect issues to show niche', function () {
    $prompt = new CoachSummaryPrompt;

    $result = $prompt->build([
        'show_name' => 'Niche Show',
        'health_score' => 40,
        'failing_checks' => [
            ['name' => 'Artwork', 'suggestion' => 'Add artwork.'],
        ],
    ]);

    expect($result)->toContain('niche and audience');
});
