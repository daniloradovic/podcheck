<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\FeedFetchException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use SimpleXMLElement;

class FeedFetcher
{
    private const int TIMEOUT_SECONDS = 10;

    private const int MAX_REDIRECTS = 3;

    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    /**
     * Fetch and parse an RSS feed from the given URL.
     *
     * @throws FeedFetchException
     */
    public function fetch(string $url): SimpleXMLElement
    {
        $this->validateUrl($url);

        $body = $this->fetchBody($url);

        return $this->parseXml($url, $body);
    }

    /**
     * Validate that the URL is well-formed and uses an allowed scheme.
     *
     * @throws FeedFetchException
     */
    private function validateUrl(string $url): void
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw FeedFetchException::invalidUrl($url);
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw FeedFetchException::invalidUrl($url);
        }
    }

    /**
     * Fetch the raw response body from the feed URL.
     *
     * @throws FeedFetchException
     */
    private function fetchBody(string $url): string
    {
        try {
            $response = $this->http
                ->timeout(self::TIMEOUT_SECONDS)
                ->maxRedirects(self::MAX_REDIRECTS)
                ->withHeaders([
                    'Accept' => 'application/rss+xml, application/xml, text/xml, */*',
                    'User-Agent' => 'PodCheck/1.0 (Podcast Feed Health Checker)',
                ])
                ->get($url);
        } catch (ConnectionException $e) {
            throw $this->classifyConnectionError($url, $e);
        }

        if ($response->status() === 404) {
            throw FeedFetchException::notFound($url);
        }

        if ($response->serverError()) {
            throw FeedFetchException::serverError($url, $response->status());
        }

        if ($response->clientError()) {
            throw FeedFetchException::serverError($url, $response->status());
        }

        $body = $response->body();

        if (empty(trim($body))) {
            throw FeedFetchException::emptyResponse($url);
        }

        return $body;
    }

    /**
     * Parse the raw XML string into a SimpleXMLElement and validate it's an RSS/Atom feed.
     *
     * @throws FeedFetchException
     */
    private function parseXml(string $url, string $body): SimpleXMLElement
    {
        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $xml = simplexml_load_string($body);

        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        if ($xml === false || count($errors) > 0) {
            throw FeedFetchException::notXml($url);
        }

        $rootName = $xml->getName();

        if (! in_array($rootName, ['rss', 'feed'], true)) {
            throw FeedFetchException::notAnRssFeed($url);
        }

        return $xml;
    }

    /**
     * Classify a connection exception into a specific FeedFetchException.
     */
    private function classifyConnectionError(string $url, ConnectionException $e): FeedFetchException
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return FeedFetchException::timeout($url);
        }

        if (str_contains($message, 'ssl') || str_contains($message, 'certificate')) {
            return FeedFetchException::sslError($url);
        }

        return FeedFetchException::connectionFailed($url);
    }
}
