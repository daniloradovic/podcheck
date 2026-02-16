<?php

declare(strict_types=1);

namespace App\Services\Checks;

use SimpleXMLElement;

interface CheckInterface
{
    /**
     * The human-readable name of this check.
     */
    public function name(): string;

    /**
     * Run the check against the given feed XML element.
     *
     * For channel-level checks, this receives the full feed XML.
     * For episode-level checks, this receives an individual <item> element.
     */
    public function run(SimpleXMLElement $feed): CheckResult;

    /**
     * The severity level of this check.
     *
     * Indicates how important this check is for feed health.
     * Common values: "error", "warning", "info"
     */
    public function severity(): string;
}
