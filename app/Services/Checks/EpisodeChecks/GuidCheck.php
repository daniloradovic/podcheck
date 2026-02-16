<?php

declare(strict_types=1);

namespace App\Services\Checks\EpisodeChecks;

use App\Services\Checks\CheckInterface;
use App\Services\Checks\CheckResult;
use SimpleXMLElement;

class GuidCheck implements CheckInterface
{
    /** @var list<string> */
    private array $seenGuids = [];

    public function name(): string
    {
        return 'Episode GUID';
    }

    public function run(SimpleXMLElement $feed): CheckResult
    {
        if (! isset($feed->guid)) {
            return CheckResult::fail(
                'Episode is missing a <guid> tag.',
                'Add a <guid> tag to each episode item. The GUID should be a unique, permanent identifier (e.g., a URL or UUID). It\'s how podcast apps track which episodes have been downloaded or played.'
            );
        }

        $guid = trim((string) $feed->guid);

        if ($guid === '') {
            return CheckResult::fail(
                'Episode <guid> tag is empty.',
                'Provide a non-empty value for the <guid> tag. Use the episode\'s permanent URL or a UUID. An empty GUID can cause podcast apps to misbehave.'
            );
        }

        if (in_array($guid, $this->seenGuids, true)) {
            return CheckResult::fail(
                "Duplicate GUID found: \"{$guid}\".",
                'Each episode must have a unique <guid>. Duplicate GUIDs cause podcast apps to treat different episodes as the same one, leading to missing episodes for listeners.'
            );
        }

        $this->seenGuids[] = $guid;

        return CheckResult::pass(
            "Episode GUID is present and unique: \"{$guid}\"."
        );
    }

    public function severity(): string
    {
        return 'error';
    }
}
