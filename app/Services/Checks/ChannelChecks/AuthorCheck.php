<?php

declare(strict_types=1);

namespace App\Services\Checks\ChannelChecks;

use App\Services\Checks\CheckInterface;
use App\Services\Checks\CheckResult;
use SimpleXMLElement;

class AuthorCheck implements CheckInterface
{
    public function name(): string
    {
        return 'iTunes Author';
    }

    public function run(SimpleXMLElement $feed): CheckResult
    {
        $author = $this->extractAuthor($feed);

        if ($author === null) {
            return CheckResult::fail(
                'iTunes author is missing.',
                'Add an <itunes:author>Your Name</itunes:author> tag to your channel. This is displayed as the podcast creator in Apple Podcasts and other directories.'
            );
        }

        if (mb_strlen($author) > 255) {
            return CheckResult::warn(
                'iTunes author name is excessively long ('.mb_strlen($author).' characters).',
                'Keep the <itunes:author> value concise — ideally under 255 characters. Use the show name or host name.'
            );
        }

        return CheckResult::pass(
            "iTunes author is present: \"{$author}\"."
        );
    }

    public function severity(): string
    {
        return 'warning';
    }

    /**
     * Extract the itunes:author value from the feed's channel element.
     */
    private function extractAuthor(SimpleXMLElement $feed): ?string
    {
        $namespaces = $feed->getNamespaces(true);
        $itunesNs = $namespaces['itunes'] ?? 'http://www.itunes.com/dtds/podcast-1.0.dtd';

        $channel = $this->getChannel($feed);

        if ($channel === null) {
            return null;
        }

        $itunes = $channel->children($itunesNs);

        if (! isset($itunes->author)) {
            return null;
        }

        $value = trim((string) $itunes->author);

        return $value !== '' ? $value : null;
    }

    /**
     * Get the channel element from the feed (RSS 2.0 or Atom).
     */
    private function getChannel(SimpleXMLElement $feed): ?SimpleXMLElement
    {
        if ($feed->getName() === 'rss' && isset($feed->channel)) {
            return $feed->channel;
        }

        if ($feed->getName() === 'feed') {
            return $feed;
        }

        return null;
    }
}
