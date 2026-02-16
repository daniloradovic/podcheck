<?php

declare(strict_types=1);

namespace App\Services\Checks\ChannelChecks;

use App\Services\Checks\CheckInterface;
use App\Services\Checks\CheckResult;
use SimpleXMLElement;

class CategoryCheck implements CheckInterface
{
    /**
     * Apple Podcasts category taxonomy.
     * Keys are primary categories, values are arrays of valid subcategories.
     *
     * @see https://podcasters.apple.com/support/1691-apple-podcasts-categories
     */
    private const array APPLE_CATEGORIES = [
        'Arts' => [
            'Books',
            'Design',
            'Fashion & Beauty',
            'Food',
            'Performing Arts',
            'Visual Arts',
        ],
        'Business' => [
            'Careers',
            'Entrepreneurship',
            'Investing',
            'Management',
            'Marketing',
            'Non-Profit',
        ],
        'Comedy' => [
            'Comedy Interviews',
            'Improv',
            'Stand-Up',
        ],
        'Education' => [
            'Courses',
            'How To',
            'Language Learning',
            'Self-Improvement',
        ],
        'Fiction' => [
            'Comedy Fiction',
            'Drama',
            'Science Fiction',
        ],
        'Government' => [],
        'History' => [],
        'Health & Fitness' => [
            'Alternative Health',
            'Fitness',
            'Medicine',
            'Mental Health',
            'Nutrition',
            'Sexuality',
        ],
        'Kids & Family' => [
            'Education for Kids',
            'Parenting',
            'Pets & Animals',
            'Stories for Kids',
        ],
        'Leisure' => [
            'Animation & Manga',
            'Automotive',
            'Aviation',
            'Crafts',
            'Games',
            'Hobbies',
            'Home & Garden',
            'Video Games',
        ],
        'Music' => [
            'Music Commentary',
            'Music History',
            'Music Interviews',
        ],
        'News' => [
            'Business News',
            'Daily News',
            'Entertainment News',
            'News Commentary',
            'Politics',
            'Sports News',
            'Tech News',
        ],
        'Religion & Spirituality' => [
            'Buddhism',
            'Christianity',
            'Hinduism',
            'Islam',
            'Judaism',
            'Religion',
            'Spirituality',
        ],
        'Science' => [
            'Astronomy',
            'Chemistry',
            'Earth Sciences',
            'Life Sciences',
            'Mathematics',
            'Natural Sciences',
            'Nature',
            'Physics',
            'Social Sciences',
        ],
        'Society & Culture' => [
            'Documentary',
            'Personal Journals',
            'Philosophy',
            'Places & Travel',
            'Relationships',
        ],
        'Sports' => [
            'Baseball',
            'Basketball',
            'Cricket',
            'Fantasy Sports',
            'Football',
            'Golf',
            'Hockey',
            'Rugby',
            'Running',
            'Soccer',
            'Swimming',
            'Tennis',
            'Volleyball',
            'Wilderness',
            'Wrestling',
        ],
        'Technology' => [],
        'True Crime' => [],
        'TV & Film' => [
            'After Shows',
            'Film History',
            'Film Interviews',
            'Film Reviews',
            'TV Reviews',
        ],
    ];

    public function name(): string
    {
        return 'iTunes Category';
    }

    public function run(SimpleXMLElement $feed): CheckResult
    {
        $category = $this->extractCategory($feed);

        if ($category === null) {
            return CheckResult::fail(
                'iTunes category is missing.',
                'Add an <itunes:category text="Technology"/> tag to your channel. Apple Podcasts requires at least one category for your podcast to be listed.'
            );
        }

        $primaryCategory = $category['primary'];
        $subcategory = $category['subcategory'];

        if (! $this->isValidPrimaryCategory($primaryCategory)) {
            return CheckResult::fail(
                "iTunes category \"{$primaryCategory}\" is not a valid Apple Podcasts category.",
                'Use one of Apple\'s official categories: '.implode(', ', array_keys(self::APPLE_CATEGORIES)).'. Check the Apple Podcasts category list for the full taxonomy.'
            );
        }

        if ($subcategory !== null && ! $this->isValidSubcategory($primaryCategory, $subcategory)) {
            return CheckResult::warn(
                "Subcategory \"{$subcategory}\" is not valid under \"{$primaryCategory}\".",
                $this->buildSubcategorySuggestion($primaryCategory)
            );
        }

        if ($subcategory === null && $this->hasSubcategories($primaryCategory)) {
            return CheckResult::warn(
                "Category \"{$primaryCategory}\" is set but no subcategory is specified.",
                'Adding a subcategory helps listeners find your podcast. '.$this->buildSubcategorySuggestion($primaryCategory)
            );
        }

        if ($subcategory !== null) {
            return CheckResult::pass(
                "iTunes category is valid: \"{$primaryCategory}\" > \"{$subcategory}\"."
            );
        }

        return CheckResult::pass(
            "iTunes category is valid: \"{$primaryCategory}\"."
        );
    }

    public function severity(): string
    {
        return 'error';
    }

    /**
     * Extract the primary category and optional subcategory from the feed.
     *
     * @return array{primary: string, subcategory: string|null}|null
     */
    private function extractCategory(SimpleXMLElement $feed): ?array
    {
        $namespaces = $feed->getNamespaces(true);
        $itunesNs = $namespaces['itunes'] ?? 'http://www.itunes.com/dtds/podcast-1.0.dtd';

        $channel = $this->getChannel($feed);

        if ($channel === null) {
            return null;
        }

        $itunes = $channel->children($itunesNs);

        if (! isset($itunes->category)) {
            return null;
        }

        $primaryText = (string) $itunes->category->attributes()['text'];

        if ($primaryText === '') {
            return null;
        }

        // Check for subcategory: <itunes:category text="Parent"><itunes:category text="Child"/></itunes:category>
        $subcategory = null;
        $subCategories = $itunes->category->children($itunesNs);

        if (isset($subCategories->category)) {
            $subText = (string) $subCategories->category->attributes()['text'];

            if ($subText !== '') {
                $subcategory = $subText;
            }
        }

        return [
            'primary' => $primaryText,
            'subcategory' => $subcategory,
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

    /**
     * Check if the given category is a valid Apple Podcasts primary category.
     */
    private function isValidPrimaryCategory(string $category): bool
    {
        return array_key_exists($category, self::APPLE_CATEGORIES);
    }

    /**
     * Check if the given subcategory is valid under the given primary category.
     */
    private function isValidSubcategory(string $primaryCategory, string $subcategory): bool
    {
        if (! $this->isValidPrimaryCategory($primaryCategory)) {
            return false;
        }

        return in_array($subcategory, self::APPLE_CATEGORIES[$primaryCategory], true);
    }

    /**
     * Check if the given primary category has defined subcategories.
     */
    private function hasSubcategories(string $primaryCategory): bool
    {
        return ! empty(self::APPLE_CATEGORIES[$primaryCategory]);
    }

    /**
     * Build a suggestion string listing valid subcategories for a primary category.
     */
    private function buildSubcategorySuggestion(string $primaryCategory): string
    {
        $subcategories = self::APPLE_CATEGORIES[$primaryCategory] ?? [];

        if (empty($subcategories)) {
            return "The \"{$primaryCategory}\" category has no subcategories.";
        }

        return "Valid subcategories for \"{$primaryCategory}\": ".implode(', ', $subcategories).'.';
    }
}
