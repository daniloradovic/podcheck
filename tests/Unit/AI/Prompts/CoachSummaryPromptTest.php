<?php

declare(strict_types=1);

use App\AI\Prompts\CoachSummaryPrompt;

// ──────────────────────────────────────────────────
// version()
// ──────────────────────────────────────────────────

test('version returns v1', function () {
    $prompt = new CoachSummaryPrompt;

    expect($prompt->version())->toBe('v1');
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
        ->toContain('Never give generic advice');
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
        ->toContain('(not provided)');
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
