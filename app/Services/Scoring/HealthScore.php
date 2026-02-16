<?php

declare(strict_types=1);

namespace App\Services\Scoring;

class HealthScore
{
    /**
     * @param  int  $overall  Overall health score (0-100)
     * @param  array<string, array{score: int, pass: int, warn: int, fail: int, total: int}>  $categories  Per-category breakdown
     */
    public function __construct(
        public readonly int $overall,
        public readonly array $categories,
    ) {}

    /**
     * Convert the health score to an array for JSON serialization.
     *
     * @return array{overall: int, categories: array<string, array{score: int, pass: int, warn: int, fail: int, total: int}>}
     */
    public function toArray(): array
    {
        return [
            'overall' => $this->overall,
            'categories' => $this->categories,
        ];
    }
}
