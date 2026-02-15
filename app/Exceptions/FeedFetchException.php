<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class FeedFetchException extends RuntimeException
{
    public static function invalidUrl(string $url): self
    {
        return new self("The URL \"{$url}\" is not a valid feed URL.");
    }

    public static function timeout(string $url): self
    {
        return new self("The feed at \"{$url}\" took too long to respond. Please try again later.");
    }

    public static function notFound(string $url): self
    {
        return new self("No feed was found at \"{$url}\". Please check the URL and try again.");
    }

    public static function serverError(string $url, int $status): self
    {
        return new self("The server at \"{$url}\" returned an error (HTTP {$status}). Please try again later.");
    }

    public static function connectionFailed(string $url): self
    {
        return new self("Could not connect to \"{$url}\". The server may be down or the URL may be incorrect.");
    }

    public static function sslError(string $url): self
    {
        return new self("SSL certificate error when connecting to \"{$url}\". The server's certificate may be invalid.");
    }

    public static function notXml(string $url): self
    {
        return new self("The response from \"{$url}\" is not valid XML. Please make sure this is an RSS feed URL.");
    }

    public static function notAnRssFeed(string $url): self
    {
        return new self("The XML at \"{$url}\" does not appear to be a valid RSS or Atom feed.");
    }

    public static function emptyResponse(string $url): self
    {
        return new self("The feed at \"{$url}\" returned an empty response.");
    }
}
