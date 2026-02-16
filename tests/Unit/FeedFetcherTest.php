<?php

declare(strict_types=1);

use App\Exceptions\FeedFetchException;
use App\Services\FeedFetcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function makeFetcher(): FeedFetcher
{
    return app(FeedFetcher::class);
}

// ──────────────────────────────────────────────────
// Valid Feed Tests
// ──────────────────────────────────────────────────

test('it fetches and parses a valid RSS feed', function () {
    Http::fake([
        'example.com/*' => Http::response(fixture('valid-rss-feed.xml'), 200),
    ]);

    $xml = makeFetcher()->fetch('https://example.com/feed.xml');

    expect($xml)->toBeInstanceOf(SimpleXMLElement::class)
        ->and($xml->getName())->toBe('rss')
        ->and((string) $xml->channel->title)->toBe('The PodCheck Test Show');
});

test('it fetches and parses a valid Atom feed', function () {
    Http::fake([
        'example.com/*' => Http::response(fixture('valid-atom-feed.xml'), 200),
    ]);

    $xml = makeFetcher()->fetch('https://example.com/feed.atom');

    expect($xml)->toBeInstanceOf(SimpleXMLElement::class)
        ->and($xml->getName())->toBe('feed');
});

test('it returns a SimpleXMLElement with accessible channel data', function () {
    Http::fake([
        'example.com/*' => Http::response(fixture('valid-rss-feed.xml'), 200),
    ]);

    $xml = makeFetcher()->fetch('https://example.com/feed.xml');

    expect((string) $xml->channel->title)->toBe('The PodCheck Test Show')
        ->and((string) $xml->channel->link)->toBe('https://example.com/podcast')
        ->and((string) $xml->channel->language)->toBe('en-us')
        ->and($xml->channel->item)->toHaveCount(3);
});

// ──────────────────────────────────────────────────
// Invalid URL Tests
// ──────────────────────────────────────────────────

test('it throws on empty URL', function () {
    makeFetcher()->fetch('');
})->throws(FeedFetchException::class, 'not a valid feed URL');

test('it throws on malformed URL', function () {
    makeFetcher()->fetch('not-a-url');
})->throws(FeedFetchException::class, 'not a valid feed URL');

test('it throws on URL without scheme', function () {
    makeFetcher()->fetch('example.com/feed.xml');
})->throws(FeedFetchException::class, 'not a valid feed URL');

test('it throws on FTP scheme URL', function () {
    makeFetcher()->fetch('ftp://example.com/feed.xml');
})->throws(FeedFetchException::class, 'not a valid feed URL');

test('it accepts HTTP scheme URL', function () {
    Http::fake([
        'example.com/*' => Http::response(fixture('valid-rss-feed.xml'), 200),
    ]);

    $xml = makeFetcher()->fetch('http://example.com/feed.xml');

    expect($xml)->toBeInstanceOf(SimpleXMLElement::class);
});

// ──────────────────────────────────────────────────
// Timeout Handling
// ──────────────────────────────────────────────────

test('it throws FeedFetchException on timeout', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection timed out');
    });

    makeFetcher()->fetch('https://example.com/feed.xml');
})->throws(FeedFetchException::class, 'took too long to respond');

test('it classifies timeout keyword variants', function () {
    Http::fake(function () {
        throw new ConnectionException('cURL error: Operation timeout after 10000 milliseconds');
    });

    makeFetcher()->fetch('https://example.com/feed.xml');
})->throws(FeedFetchException::class, 'took too long to respond');

// ──────────────────────────────────────────────────
// Connection Errors
// ──────────────────────────────────────────────────

test('it throws FeedFetchException on SSL error', function () {
    Http::fake(function () {
        throw new ConnectionException('cURL error: SSL certificate problem');
    });

    makeFetcher()->fetch('https://example.com/feed.xml');
})->throws(FeedFetchException::class, 'SSL certificate error');

test('it throws FeedFetchException on certificate error', function () {
    Http::fake(function () {
        throw new ConnectionException('cURL error: unable to get local issuer certificate');
    });

    makeFetcher()->fetch('https://example.com/feed.xml');
})->throws(FeedFetchException::class, 'SSL certificate error');

test('it throws FeedFetchException on generic connection failure', function () {
    Http::fake(function () {
        throw new ConnectionException('cURL error: Could not resolve host');
    });

    makeFetcher()->fetch('https://example.com/feed.xml');
})->throws(FeedFetchException::class, 'Could not connect');

// ──────────────────────────────────────────────────
// HTTP Error Responses
// ──────────────────────────────────────────────────

test('it throws FeedFetchException on 404 response', function () {
    Http::fake([
        'example.com/*' => Http::response('Not Found', 404),
    ]);

    makeFetcher()->fetch('https://example.com/feed.xml');
})->throws(FeedFetchException::class, 'No feed was found');

test('it throws FeedFetchException on 500 server error', function () {
    Http::fake([
        'example.com/*' => Http::response('Internal Server Error', 500),
    ]);

    makeFetcher()->fetch('https://example.com/feed.xml');
})->throws(FeedFetchException::class, 'returned an error (HTTP 500)');

test('it throws FeedFetchException on 503 server error', function () {
    Http::fake([
        'example.com/*' => Http::response('Service Unavailable', 503),
    ]);

    makeFetcher()->fetch('https://example.com/feed.xml');
})->throws(FeedFetchException::class, 'returned an error (HTTP 503)');

test('it throws FeedFetchException on 403 client error', function () {
    Http::fake([
        'example.com/*' => Http::response('Forbidden', 403),
    ]);

    makeFetcher()->fetch('https://example.com/feed.xml');
})->throws(FeedFetchException::class, 'returned an error (HTTP 403)');

test('it throws FeedFetchException on empty response body', function () {
    Http::fake([
        'example.com/*' => Http::response('', 200),
    ]);

    makeFetcher()->fetch('https://example.com/feed.xml');
})->throws(FeedFetchException::class, 'returned an empty response');

test('it throws FeedFetchException on whitespace-only response body', function () {
    Http::fake([
        'example.com/*' => Http::response("   \n\t  ", 200),
    ]);

    makeFetcher()->fetch('https://example.com/feed.xml');
})->throws(FeedFetchException::class, 'returned an empty response');

// ──────────────────────────────────────────────────
// Non-XML Response Handling
// ──────────────────────────────────────────────────

test('it throws FeedFetchException on HTML response', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><body>Not a feed</body></html>', 200),
    ]);

    makeFetcher()->fetch('https://example.com/feed.xml');
})->throws(FeedFetchException::class, 'does not appear to be a valid RSS or Atom feed');

test('it throws FeedFetchException on JSON response', function () {
    Http::fake([
        'example.com/*' => Http::response('{"error": "not a feed"}', 200),
    ]);

    makeFetcher()->fetch('https://example.com/feed.xml');
})->throws(FeedFetchException::class, 'not valid XML');

test('it throws FeedFetchException on plain text response', function () {
    Http::fake([
        'example.com/*' => Http::response('Just some plain text', 200),
    ]);

    makeFetcher()->fetch('https://example.com/feed.xml');
})->throws(FeedFetchException::class, 'not valid XML');

test('it throws FeedFetchException on malformed XML', function () {
    Http::fake([
        'example.com/*' => Http::response('<?xml version="1.0"?><rss><broken', 200),
    ]);

    makeFetcher()->fetch('https://example.com/feed.xml');
})->throws(FeedFetchException::class, 'not valid XML');

test('it throws FeedFetchException on valid XML that is not RSS or Atom', function () {
    $sitemap = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.com</loc></url></urlset>';

    Http::fake([
        'example.com/*' => Http::response($sitemap, 200),
    ]);

    makeFetcher()->fetch('https://example.com/sitemap.xml');
})->throws(FeedFetchException::class, 'does not appear to be a valid RSS or Atom feed');
