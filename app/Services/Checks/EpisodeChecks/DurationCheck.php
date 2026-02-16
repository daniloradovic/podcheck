<?php

declare(strict_types=1);

namespace App\Services\Checks\EpisodeChecks;

use App\Services\Checks\CheckInterface;
use App\Services\Checks\CheckResult;
use SimpleXMLElement;

class DurationCheck implements CheckInterface
{
    public function name(): string
    {
        return 'Episode Duration';
    }

    public function run(SimpleXMLElement $feed): CheckResult
    {
        $namespaces = $feed->getNamespaces(true);
        $itunesNs = $namespaces['itunes'] ?? 'http://www.itunes.com/dtds/podcast-1.0.dtd';

        $itunes = $feed->children($itunesNs);

        if (! isset($itunes->duration)) {
            return CheckResult::warn(
                'Episode is missing an <itunes:duration> tag.',
                'Add an <itunes:duration> tag to each episode (e.g., <itunes:duration>00:32:15</itunes:duration> or <itunes:duration>1935</itunes:duration>). This helps listeners see episode length before downloading and improves the listening experience.'
            );
        }

        $duration = trim((string) $itunes->duration);

        if ($duration === '') {
            return CheckResult::warn(
                'Episode <itunes:duration> tag is empty.',
                'Provide a valid duration value in HH:MM:SS, MM:SS, or total seconds format.'
            );
        }

        if (! $this->isValidDuration($duration)) {
            return CheckResult::warn(
                "Episode duration format is not recognized: \"{$duration}\".",
                'Use one of the standard duration formats: HH:MM:SS (e.g., "01:23:45"), MM:SS (e.g., "23:45"), or total seconds (e.g., "5025").'
            );
        }

        return CheckResult::pass(
            "Episode duration is present: {$duration}."
        );
    }

    public function severity(): string
    {
        return 'warning';
    }

    /**
     * Validate the duration format.
     *
     * Accepts: HH:MM:SS, MM:SS, H:MM:SS, or total seconds (integer).
     */
    private function isValidDuration(string $duration): bool
    {
        // Total seconds (integer only)
        if (preg_match('/^\d+$/', $duration)) {
            return true;
        }

        // MM:SS or HH:MM:SS (or H:MM:SS)
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $duration)) {
            return true;
        }

        return false;
    }
}
