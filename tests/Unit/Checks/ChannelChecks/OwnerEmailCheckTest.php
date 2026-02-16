<?php

declare(strict_types=1);

use App\Services\Checks\ChannelChecks\OwnerEmailCheck;
use App\Services\Checks\CheckStatus;

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function buildFeedWithOwner(?string $name = 'Jane Podcaster', ?string $email = 'jane@example.com'): SimpleXMLElement
{
    $nameTag = $name !== null ? "<itunes:name>{$name}</itunes:name>" : '';
    $emailTag = $email !== null ? "<itunes:email>{$email}</itunes:email>" : '';

    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <title>Test Show</title>
            <itunes:owner>
              {$nameTag}
              {$emailTag}
            </itunes:owner>
          </channel>
        </rss>
        XML;

    return simplexml_load_string($xml);
}

function buildFeedWithoutOwner(): SimpleXMLElement
{
    $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <title>Test Show</title>
          </channel>
        </rss>
        XML;

    return simplexml_load_string($xml);
}

// ──────────────────────────────────────────────────
// Metadata
// ──────────────────────────────────────────────────

test('name returns "Owner Email"', function () {
    $check = new OwnerEmailCheck;

    expect($check->name())->toBe('Owner Email');
});

test('severity returns "error"', function () {
    $check = new OwnerEmailCheck;

    expect($check->severity())->toBe('error');
});

test('implements CheckInterface', function () {
    $check = new OwnerEmailCheck;

    expect($check)->toBeInstanceOf(\App\Services\Checks\CheckInterface::class);
});

// ──────────────────────────────────────────────────
// Missing Owner — Fail
// ──────────────────────────────────────────────────

test('fails when itunes:owner is missing', function () {
    $check = new OwnerEmailCheck;
    $feed = buildFeedWithoutOwner();

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('owner information is missing')
        ->and($result->suggestion)->toContain('itunes:owner');
});

// ──────────────────────────────────────────────────
// Missing Email — Fail
// ──────────────────────────────────────────────────

test('fails when itunes:email is missing from owner', function () {
    $check = new OwnerEmailCheck;
    $feed = buildFeedWithOwner('Jane Podcaster', null);

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('email is missing')
        ->and($result->suggestion)->toContain('itunes:email');
});

test('fails when itunes:email is empty', function () {
    $check = new OwnerEmailCheck;
    $feed = buildFeedWithOwner('Jane Podcaster', '');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('email is missing');
});

// ──────────────────────────────────────────────────
// Invalid Email — Warn
// ──────────────────────────────────────────────────

test('warns when email format is invalid', function () {
    $check = new OwnerEmailCheck;
    $feed = buildFeedWithOwner('Jane Podcaster', 'not-an-email');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('not appear to be valid')
        ->and($result->message)->toContain('not-an-email');
});

test('warns when email is missing @ symbol', function () {
    $check = new OwnerEmailCheck;
    $feed = buildFeedWithOwner('Jane Podcaster', 'jane.example.com');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Warn);
});

// ──────────────────────────────────────────────────
// Valid Owner — Pass
// ──────────────────────────────────────────────────

test('passes with valid owner name and email', function () {
    $check = new OwnerEmailCheck;
    $feed = buildFeedWithOwner('Jane Podcaster', 'jane@example.com');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('jane@example.com')
        ->and($result->message)->toContain('Jane Podcaster');
});

test('passes with valid email and no name', function () {
    $check = new OwnerEmailCheck;
    $feed = buildFeedWithOwner(null, 'jane@example.com');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('jane@example.com');
});
