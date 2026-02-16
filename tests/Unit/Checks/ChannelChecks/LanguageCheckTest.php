<?php

declare(strict_types=1);

use App\Services\Checks\ChannelChecks\LanguageCheck;
use App\Services\Checks\CheckStatus;

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function buildFeedWithLanguage(?string $language = 'en-us'): SimpleXMLElement
{
    $languageTag = $language !== null
        ? "<language>{$language}</language>"
        : '';

    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <title>Test Show</title>
            {$languageTag}
          </channel>
        </rss>
        XML;

    return simplexml_load_string($xml);
}

function buildFeedWithoutLanguage(): SimpleXMLElement
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

test('name returns "Language Tag"', function () {
    $check = new LanguageCheck;

    expect($check->name())->toBe('Language Tag');
});

test('severity returns "warning"', function () {
    $check = new LanguageCheck;

    expect($check->severity())->toBe('warning');
});

test('implements CheckInterface', function () {
    $check = new LanguageCheck;

    expect($check)->toBeInstanceOf(\App\Services\Checks\CheckInterface::class);
});

// ──────────────────────────────────────────────────
// Missing Language — Fail
// ──────────────────────────────────────────────────

test('fails when language tag is missing', function () {
    $check = new LanguageCheck;
    $feed = buildFeedWithoutLanguage();

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing')
        ->and($result->suggestion)->toContain('language');
});

test('fails when language tag is empty', function () {
    $check = new LanguageCheck;
    $feed = buildFeedWithLanguage('');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing');
});

// ──────────────────────────────────────────────────
// Invalid Language Codes — Warn
// ──────────────────────────────────────────────────

test('warns when language code is not a valid ISO code', function () {
    $check = new LanguageCheck;
    $feed = buildFeedWithLanguage('xyz');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('not appear to be a valid')
        ->and($result->message)->toContain('xyz');
});

test('warns when language code is too long', function () {
    $check = new LanguageCheck;
    $feed = buildFeedWithLanguage('english');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('not appear to be a valid');
});

test('warns when language code contains numbers', function () {
    $check = new LanguageCheck;
    $feed = buildFeedWithLanguage('en123');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Warn);
});

// ──────────────────────────────────────────────────
// Valid Language Codes — Pass
// ──────────────────────────────────────────────────

test('passes with valid "en-us" language code', function () {
    $check = new LanguageCheck;
    $feed = buildFeedWithLanguage('en-us');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('en-us');
});

test('passes with simple "en" language code', function () {
    $check = new LanguageCheck;
    $feed = buildFeedWithLanguage('en');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('en');
});

test('passes with "pt-BR" language code', function () {
    $check = new LanguageCheck;
    $feed = buildFeedWithLanguage('pt-BR');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('pt-BR');
});

test('passes with "de" language code', function () {
    $check = new LanguageCheck;
    $feed = buildFeedWithLanguage('de');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass);
});

test('passes with "ja" language code', function () {
    $check = new LanguageCheck;
    $feed = buildFeedWithLanguage('ja');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass);
});

test('passes with "fr-CA" language code', function () {
    $check = new LanguageCheck;
    $feed = buildFeedWithLanguage('fr-CA');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass);
});
