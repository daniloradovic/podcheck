<?php

declare(strict_types=1);

namespace App\Services\Checks\ChannelChecks;

use App\Services\Checks\CheckInterface;
use App\Services\Checks\CheckResult;
use SimpleXMLElement;

class WebsiteLinkCheck implements CheckInterface
{
    public function name(): string
    {
        return 'Website Link';
    }

    public function run(SimpleXMLElement $feed): CheckResult
    {
        $link = $this->extractLink($feed);

        if ($link === null) {
            return CheckResult::fail(
                'Website link is missing.',
                'Add a <link>https://yourpodcast.com</link> tag to your channel. This links to your podcast\'s website and helps listeners find more about your show.'
            );
        }

        if (! $this->isValidUrl($link)) {
            return CheckResult::warn(
                "Website link does not appear to be a valid URL: \"{$link}\".",
                'Ensure the <link> tag contains a full URL starting with http:// or https:// (e.g., https://yourpodcast.com).'
            );
        }

        return CheckResult::pass(
            "Website link is present: \"{$link}\"."
        );
    }

    public function severity(): string
    {
        return 'warning';
    }

    /**
     * Extract the website link from the feed's channel element.
     */
    private function extractLink(SimpleXMLElement $feed): ?string
    {
        $channel = $this->getChannel($feed);

        if ($channel === null) {
            return null;
        }

        if (! isset($channel->link)) {
            return null;
        }

        $value = trim((string) $channel->link);

        return $value !== '' ? $value : null;
    }

    /**
     * Check if the given string is a valid HTTP(S) URL.
     */
    private function isValidUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return in_array(strtolower((string) $scheme), ['http', 'https'], true);
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
