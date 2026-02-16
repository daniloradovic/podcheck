<?php

declare(strict_types=1);

namespace App\Services\Scoring;

class SeoScore
{
    /**
     * @param  int  $overall  Overall SEO score (0-100)
     * @param  array<string, array{score: int, status: string, message: string, suggestion: string|null}>  $details  Per-area breakdown
     */
    public function __construct(
        public readonly int $overall,
        public readonly array $details,
    ) {}

    /**
     * Convert the SEO score to an array for JSON serialization.
     *
     * @return array{overall: int, details: array<string, array{score: int, status: string, message: string, suggestion: string|null}>}
     */
    public function toArray(): array
    {
        return [
            'overall' => $this->overall,
            'details' => $this->details,
        ];
    }
}
