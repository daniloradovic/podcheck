<?php

declare(strict_types=1);

use App\Services\Checks\CheckResult;
use App\Services\Checks\CheckStatus;

// ──────────────────────────────────────────────────
// Factory Methods
// ──────────────────────────────────────────────────

test('pass() creates a passing result', function () {
    $result = CheckResult::pass('Feed title is present');

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toBe('Feed title is present')
        ->and($result->suggestion)->toBeNull();
});

test('warn() creates a warning result with suggestion', function () {
    $result = CheckResult::warn(
        'Feed title is too long (85 characters)',
        'Keep your title under 60 characters for best display across platforms.'
    );

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toBe('Feed title is too long (85 characters)')
        ->and($result->suggestion)->toBe('Keep your title under 60 characters for best display across platforms.');
});

test('fail() creates a failing result with suggestion', function () {
    $result = CheckResult::fail(
        'iTunes artwork image is missing',
        'Add an <itunes:image> tag with a URL to your podcast artwork (1400x1400 to 3000x3000 pixels, JPEG or PNG).'
    );

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toBe('iTunes artwork image is missing')
        ->and($result->suggestion)->toBe('Add an <itunes:image> tag with a URL to your podcast artwork (1400x1400 to 3000x3000 pixels, JPEG or PNG).');
});

// ──────────────────────────────────────────────────
// Constructor
// ──────────────────────────────────────────────────

test('constructor allows explicit status, message, and suggestion', function () {
    $result = new CheckResult(
        status: CheckStatus::Warn,
        message: 'Custom message',
        suggestion: 'Custom suggestion',
    );

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toBe('Custom message')
        ->and($result->suggestion)->toBe('Custom suggestion');
});

test('constructor defaults suggestion to null', function () {
    $result = new CheckResult(
        status: CheckStatus::Pass,
        message: 'All good',
    );

    expect($result->suggestion)->toBeNull();
});

// ──────────────────────────────────────────────────
// Status Helpers
// ──────────────────────────────────────────────────

test('isPassing() returns true only for pass status', function () {
    expect(CheckResult::pass('OK')->isPassing())->toBeTrue()
        ->and(CheckResult::warn('Warn', 'Fix')->isPassing())->toBeFalse()
        ->and(CheckResult::fail('Fail', 'Fix')->isPassing())->toBeFalse();
});

test('isWarning() returns true only for warn status', function () {
    expect(CheckResult::warn('Warn', 'Fix')->isWarning())->toBeTrue()
        ->and(CheckResult::pass('OK')->isWarning())->toBeFalse()
        ->and(CheckResult::fail('Fail', 'Fix')->isWarning())->toBeFalse();
});

test('isFailing() returns true only for fail status', function () {
    expect(CheckResult::fail('Fail', 'Fix')->isFailing())->toBeTrue()
        ->and(CheckResult::pass('OK')->isFailing())->toBeFalse()
        ->and(CheckResult::warn('Warn', 'Fix')->isFailing())->toBeFalse();
});

// ──────────────────────────────────────────────────
// Serialization
// ──────────────────────────────────────────────────

test('toArray() serializes a passing result', function () {
    $result = CheckResult::pass('Feed title is present');

    expect($result->toArray())->toBe([
        'status' => 'pass',
        'message' => 'Feed title is present',
        'suggestion' => null,
    ]);
});

test('toArray() serializes a failing result with suggestion', function () {
    $result = CheckResult::fail('Artwork missing', 'Add artwork');

    expect($result->toArray())->toBe([
        'status' => 'fail',
        'message' => 'Artwork missing',
        'suggestion' => 'Add artwork',
    ]);
});

test('toArray() serializes a warning result with suggestion', function () {
    $result = CheckResult::warn('Title too long', 'Shorten it');

    expect($result->toArray())->toBe([
        'status' => 'warn',
        'message' => 'Title too long',
        'suggestion' => 'Shorten it',
    ]);
});

// ──────────────────────────────────────────────────
// Immutability
// ──────────────────────────────────────────────────

test('CheckResult properties are readonly', function () {
    $result = CheckResult::pass('Test');

    $reflection = new ReflectionClass($result);

    expect($reflection->getProperty('status')->isReadOnly())->toBeTrue()
        ->and($reflection->getProperty('message')->isReadOnly())->toBeTrue()
        ->and($reflection->getProperty('suggestion')->isReadOnly())->toBeTrue();
});
