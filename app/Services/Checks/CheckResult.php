<?php

declare(strict_types=1);

namespace App\Services\Checks;

class CheckResult
{
    public function __construct(
        public readonly CheckStatus $status,
        public readonly string $message,
        public readonly ?string $suggestion = null,
    ) {}

    /**
     * Create a passing check result.
     */
    public static function pass(string $message): self
    {
        return new self(
            status: CheckStatus::Pass,
            message: $message,
        );
    }

    /**
     * Create a warning check result.
     */
    public static function warn(string $message, string $suggestion): self
    {
        return new self(
            status: CheckStatus::Warn,
            message: $message,
            suggestion: $suggestion,
        );
    }

    /**
     * Create a failing check result.
     */
    public static function fail(string $message, string $suggestion): self
    {
        return new self(
            status: CheckStatus::Fail,
            message: $message,
            suggestion: $suggestion,
        );
    }

    /**
     * Check if this result is a pass.
     */
    public function isPassing(): bool
    {
        return $this->status === CheckStatus::Pass;
    }

    /**
     * Check if this result is a warning.
     */
    public function isWarning(): bool
    {
        return $this->status === CheckStatus::Warn;
    }

    /**
     * Check if this result is a failure.
     */
    public function isFailing(): bool
    {
        return $this->status === CheckStatus::Fail;
    }

    /**
     * Convert the result to an array for JSON serialization.
     *
     * @return array{status: string, message: string, suggestion: string|null}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'message' => $this->message,
            'suggestion' => $this->suggestion,
        ];
    }
}
