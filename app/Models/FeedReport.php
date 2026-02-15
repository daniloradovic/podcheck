<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FeedReport extends Model
{
    use HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'feed_url',
        'feed_title',
        'overall_score',
        'results_json',
        'slug',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'results_json' => 'array',
            'overall_score' => 'integer',
        ];
    }

    /**
     * Boot the model and register event listeners.
     */
    protected static function booted(): void
    {
        static::creating(function (FeedReport $report): void {
            if (empty($report->slug)) {
                $report->slug = self::generateUniqueSlug();
            }
        });
    }

    /**
     * Generate a unique slug for the report.
     */
    private static function generateUniqueSlug(): string
    {
        do {
            $slug = Str::random(10);
        } while (self::where('slug', $slug)->exists());

        return $slug;
    }

    /**
     * Get the route key name for Laravel route-model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
