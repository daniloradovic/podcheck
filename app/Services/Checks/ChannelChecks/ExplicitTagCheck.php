<?php

declare(strict_types=1);

namespace App\Services\Checks\ChannelChecks;

use App\Services\Checks\CheckInterface;
use App\Services\Checks\CheckResult;
use SimpleXMLElement;

class ExplicitTagCheck implements CheckInterface
{
    private const array VALID_VALUES = ['true', 'false', 'yes', 'no'];

    public function name(): string
    {
        return 'Explicit Tag';
    }

    public function run(SimpleXMLElement $feed): CheckResult
    {
        $value = $this->extractExplicitValue($feed);

        if ($value === null) {
            return CheckResult::fail(
                'iTunes explicit tag is missing.',
                'Add an <itunes:explicit>false</itunes:explicit> tag to your channel. Apple Podcasts requires this tag to indicate whether your podcast contains explicit content.'
            );
        }

        $normalized = strtolower(trim($value));

        if (! in_array($normalized, self::VALID_VALUES, true)) {
            return CheckResult::warn(
                "iTunes explicit tag has a non-standard value: \"{$value}\".",
                'The <itunes:explicit> tag should be "true" or "false". Legacy values "yes" and "no" are also accepted but "true"/"false" is preferred.'
            );
        }

        if ($normalized === 'yes' || $normalized === 'no') {
            return CheckResult::warn(
                "iTunes explicit tag uses legacy value \"{$value}\".",
                'Update the <itunes:explicit> tag to use "true" or "false" instead of "yes"/"no". The legacy values still work but are deprecated.'
            );
        }

        return CheckResult::pass(
            "iTunes explicit tag is present and valid (\"{$normalized}\")."
        );
    }

    public function severity(): string
    {
        return 'error';
    }

    /**
     * Extract the itunes:explicit value from the feed's channel element.
     */
    private function extractExplicitValue(SimpleXMLElement $feed): ?string
    {
        $namespaces = $feed->getNamespaces(true);
        $itunesNs = $namespaces['itunes'] ?? 'http://www.itunes.com/dtds/podcast-1.0.dtd';

        $channel = $this->getChannel($feed);

        if ($channel === null) {
            return null;
        }

        $itunes = $channel->children($itunesNs);

        if (! isset($itunes->explicit)) {
            return null;
        }

        $value = (string) $itunes->explicit;

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
