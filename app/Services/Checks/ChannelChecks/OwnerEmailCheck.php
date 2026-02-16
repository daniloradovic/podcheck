<?php

declare(strict_types=1);

namespace App\Services\Checks\ChannelChecks;

use App\Services\Checks\CheckInterface;
use App\Services\Checks\CheckResult;
use SimpleXMLElement;

class OwnerEmailCheck implements CheckInterface
{
    public function name(): string
    {
        return 'Owner Email';
    }

    public function run(SimpleXMLElement $feed): CheckResult
    {
        $owner = $this->extractOwnerInfo($feed);

        if ($owner === null) {
            return CheckResult::fail(
                'iTunes owner information is missing.',
                'Add an <itunes:owner> block with <itunes:name> and <itunes:email> to your channel. Apple uses this email for account verification and communication about your podcast.'
            );
        }

        if ($owner['email'] === null) {
            return CheckResult::fail(
                'iTunes owner email is missing.',
                'Add an <itunes:email> tag inside your <itunes:owner> block. Apple Podcasts requires an owner email for account verification.'
            );
        }

        if (! filter_var($owner['email'], FILTER_VALIDATE_EMAIL)) {
            return CheckResult::warn(
                "iTunes owner email does not appear to be valid: \"{$owner['email']}\".",
                'Ensure the <itunes:email> contains a properly formatted email address (e.g., you@example.com).'
            );
        }

        $nameInfo = $owner['name'] !== null
            ? " (name: \"{$owner['name']}\")"
            : '';

        return CheckResult::pass(
            "iTunes owner email is present: \"{$owner['email']}\"{$nameInfo}."
        );
    }

    public function severity(): string
    {
        return 'error';
    }

    /**
     * Extract the owner name and email from the feed's channel element.
     *
     * @return array{name: string|null, email: string|null}|null
     */
    private function extractOwnerInfo(SimpleXMLElement $feed): ?array
    {
        $namespaces = $feed->getNamespaces(true);
        $itunesNs = $namespaces['itunes'] ?? 'http://www.itunes.com/dtds/podcast-1.0.dtd';

        $channel = $this->getChannel($feed);

        if ($channel === null) {
            return null;
        }

        $itunes = $channel->children($itunesNs);

        if (! isset($itunes->owner)) {
            return null;
        }

        $ownerChildren = $itunes->owner->children($itunesNs);

        $name = isset($ownerChildren->name) ? trim((string) $ownerChildren->name) : null;
        $email = isset($ownerChildren->email) ? trim((string) $ownerChildren->email) : null;

        if ($name === '') {
            $name = null;
        }

        if ($email === '') {
            $email = null;
        }

        return [
            'name' => $name,
            'email' => $email,
        ];
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
