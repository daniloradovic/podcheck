<?php

declare(strict_types=1);

use App\Services\Checks\ChannelChecks\DescriptionCheck;
use App\Services\Checks\CheckStatus;

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function buildFeedWithDescription(?string $description = null, ?string $summary = null): SimpleXMLElement
{
    $descTag = $description !== null
        ? "<description>{$description}</description>"
        : '';

    $summaryTag = $summary !== null
        ? "<itunes:summary>{$summary}</itunes:summary>"
        : '';

    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <title>Test Show</title>
            {$descTag}
            {$summaryTag}
          </channel>
        </rss>
        XML;

    return simplexml_load_string($xml);
}

function buildFeedWithoutDescription(): SimpleXMLElement
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

test('name returns "Channel Description"', function () {
    $check = new DescriptionCheck;

    expect($check->name())->toBe('Channel Description');
});

test('severity returns "error"', function () {
    $check = new DescriptionCheck;

    expect($check->severity())->toBe('error');
});

test('implements CheckInterface', function () {
    $check = new DescriptionCheck;

    expect($check)->toBeInstanceOf(\App\Services\Checks\CheckInterface::class);
});

// ──────────────────────────────────────────────────
// Missing Description — Fail
// ──────────────────────────────────────────────────

test('fails when both description and summary are missing', function () {
    $check = new DescriptionCheck;
    $feed = buildFeedWithoutDescription();

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing')
        ->and($result->suggestion)->toContain('description');
});

test('fails when description is empty and no summary', function () {
    $check = new DescriptionCheck;
    $feed = buildFeedWithDescription('', null);

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing');
});

// ──────────────────────────────────────────────────
// Short Description — Warn
// ──────────────────────────────────────────────────

test('warns when description is too short', function () {
    $check = new DescriptionCheck;
    $feed = buildFeedWithDescription('Short desc.');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('too short')
        ->and($result->suggestion)->toContain('20');
});

// ──────────────────────────────────────────────────
// Long Description — Warn
// ──────────────────────────────────────────────────

test('warns when description exceeds 4000 characters', function () {
    $check = new DescriptionCheck;
    $longDescription = str_repeat('A podcast about interesting things. ', 150);
    $feed = buildFeedWithDescription($longDescription);

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('very long')
        ->and($result->suggestion)->toContain('4000');
});

// ──────────────────────────────────────────────────
// Valid Description — Pass
// ──────────────────────────────────────────────────

test('passes with valid description', function () {
    $check = new DescriptionCheck;
    $feed = buildFeedWithDescription('A test podcast feed used for automated testing of the PodCheck feed health checker.');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('present');
});

test('passes when only itunes:summary is present', function () {
    $check = new DescriptionCheck;
    $feed = buildFeedWithDescription(null, 'A great podcast about technology and development, covering the latest trends.');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('present');
});

test('prefers description over itunes:summary', function () {
    $check = new DescriptionCheck;
    $feed = buildFeedWithDescription(
        'This is the main description used for the channel.',
        'This is the itunes summary.'
    );

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass);
});

test('passes with description exactly at minimum length', function () {
    $check = new DescriptionCheck;
    $feed = buildFeedWithDescription(str_repeat('A', 20));

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass);
});

test('passes with description at 4000 characters', function () {
    $check = new DescriptionCheck;
    $feed = buildFeedWithDescription(str_repeat('A', 4000));

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass);
});
