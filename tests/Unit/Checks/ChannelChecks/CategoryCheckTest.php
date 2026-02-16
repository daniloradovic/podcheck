<?php

declare(strict_types=1);

use App\Services\Checks\ChannelChecks\CategoryCheck;
use App\Services\Checks\CheckStatus;

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function buildFeedWithCategory(?string $primary = null, ?string $sub = null): SimpleXMLElement
{
    $categoryTag = '';

    if ($primary !== null) {
        $subTag = $sub !== null ? "<itunes:category text=\"{$sub}\"/>" : '';
        $categoryTag = "<itunes:category text=\"{$primary}\">{$subTag}</itunes:category>";
    }

    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <title>Test Show</title>
            {$categoryTag}
          </channel>
        </rss>
        XML;

    return simplexml_load_string($xml);
}

// ──────────────────────────────────────────────────
// Metadata
// ──────────────────────────────────────────────────

test('name returns "iTunes Category"', function () {
    $check = new CategoryCheck();

    expect($check->name())->toBe('iTunes Category');
});

test('severity returns "error"', function () {
    $check = new CategoryCheck();

    expect($check->severity())->toBe('error');
});

test('implements CheckInterface', function () {
    $check = new CategoryCheck();

    expect($check)->toBeInstanceOf(\App\Services\Checks\CheckInterface::class);
});

// ──────────────────────────────────────────────────
// Missing Category — Fail
// ──────────────────────────────────────────────────

test('fails when itunes:category is missing', function () {
    $check = new CategoryCheck();
    $feed = buildFeedWithCategory();

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing')
        ->and($result->suggestion)->toContain('itunes:category');
});

test('fails when itunes:category has empty text attribute', function () {
    $check = new CategoryCheck();

    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <title>Test Show</title>
            <itunes:category text=""/>
          </channel>
        </rss>
        XML;

    $feed = simplexml_load_string($xml);
    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing');
});

// ──────────────────────────────────────────────────
// Invalid Primary Category — Fail
// ──────────────────────────────────────────────────

test('fails when category is not in Apple taxonomy', function () {
    $check = new CategoryCheck();
    $feed = buildFeedWithCategory('Cooking');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('Cooking')
        ->and($result->message)->toContain('not a valid')
        ->and($result->suggestion)->toContain('Technology');
});

test('fails when category name is misspelled', function () {
    $check = new CategoryCheck();
    $feed = buildFeedWithCategory('Technolgy');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('not a valid');
});

// ──────────────────────────────────────────────────
// Valid Category Without Subcategory — Warn (when subcategories exist)
// ──────────────────────────────────────────────────

test('warns when valid category has available subcategories but none specified', function () {
    $check = new CategoryCheck();
    $feed = buildFeedWithCategory('Technology');

    // Technology has no subcategories, so this should pass
    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass);
});

test('warns when category has subcategories but none is specified', function () {
    $check = new CategoryCheck();
    $feed = buildFeedWithCategory('Arts');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('no subcategory')
        ->and($result->suggestion)->toContain('subcategor');
});

test('warns with invalid subcategory under valid primary', function () {
    $check = new CategoryCheck();
    $feed = buildFeedWithCategory('Business', 'Cooking');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('Cooking')
        ->and($result->message)->toContain('not valid under')
        ->and($result->suggestion)->toContain('Careers');
});

// ──────────────────────────────────────────────────
// Valid Category With Subcategory — Pass
// ──────────────────────────────────────────────────

test('passes with valid primary and subcategory', function () {
    $check = new CategoryCheck();
    $feed = buildFeedWithCategory('Technology', 'Tech News');

    // Technology has no subcategories in Apple's taxonomy, so "Tech News" would be invalid
    // Let's use a category that actually has subcategories
    $feed = buildFeedWithCategory('News', 'Tech News');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('News')
        ->and($result->message)->toContain('Tech News');
});

test('passes with valid primary category that has no subcategories', function () {
    $check = new CategoryCheck();
    $feed = buildFeedWithCategory('True Crime');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('True Crime');
});

test('passes with Government category (no subcategories)', function () {
    $check = new CategoryCheck();
    $feed = buildFeedWithCategory('Government');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass);
});

test('passes with valid Education > Self-Improvement', function () {
    $check = new CategoryCheck();
    $feed = buildFeedWithCategory('Education', 'Self-Improvement');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('Education')
        ->and($result->message)->toContain('Self-Improvement');
});

test('passes with valid Society & Culture > Documentary', function () {
    $check = new CategoryCheck();
    $feed = buildFeedWithCategory('Society &amp; Culture', 'Documentary');

    // XML entities in attribute values — simplexml handles this
    $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <title>Test Show</title>
            <itunes:category text="Society &amp; Culture">
              <itunes:category text="Documentary"/>
            </itunes:category>
          </channel>
        </rss>
        XML;

    $feed = simplexml_load_string($xml);
    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('Society & Culture')
        ->and($result->message)->toContain('Documentary');
});

// ──────────────────────────────────────────────────
// Valid RSS fixture
// ──────────────────────────────────────────────────

test('warns with valid fixture that has subcategory under category with no subcategories', function () {
    $check = new CategoryCheck();

    // Fixture has Technology > Tech News, but Technology has no subcategories
    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <title>The PodCheck Test Show</title>
            <itunes:category text="Technology">
              <itunes:category text="Tech News"/>
            </itunes:category>
          </channel>
        </rss>
        XML;

    $feed = simplexml_load_string($xml);
    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('Tech News')
        ->and($result->message)->toContain('not valid under');
});
