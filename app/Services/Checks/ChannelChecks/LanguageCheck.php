<?php

declare(strict_types=1);

namespace App\Services\Checks\ChannelChecks;

use App\Services\Checks\CheckInterface;
use App\Services\Checks\CheckResult;
use SimpleXMLElement;

class LanguageCheck implements CheckInterface
{
    /**
     * Common ISO 639-1 language codes used in podcast feeds.
     *
     * This is not exhaustive but covers the most commonly used languages.
     */
    private const array COMMON_LANGUAGE_CODES = [
        'aa', 'ab', 'af', 'ak', 'am', 'an', 'ar', 'as', 'av', 'ay', 'az',
        'ba', 'be', 'bg', 'bh', 'bi', 'bm', 'bn', 'bo', 'br', 'bs',
        'ca', 'ce', 'ch', 'co', 'cr', 'cs', 'cu', 'cv', 'cy',
        'da', 'de', 'dv', 'dz',
        'ee', 'el', 'en', 'eo', 'es', 'et', 'eu',
        'fa', 'ff', 'fi', 'fj', 'fo', 'fr', 'fy',
        'ga', 'gd', 'gl', 'gn', 'gu', 'gv',
        'ha', 'he', 'hi', 'ho', 'hr', 'ht', 'hu', 'hy', 'hz',
        'ia', 'id', 'ie', 'ig', 'ii', 'ik', 'in', 'io', 'is', 'it', 'iu',
        'ja', 'jv',
        'ka', 'kg', 'ki', 'kj', 'kk', 'kl', 'km', 'kn', 'ko', 'kr', 'ks', 'ku', 'kv', 'kw', 'ky',
        'la', 'lb', 'lg', 'li', 'ln', 'lo', 'lt', 'lu', 'lv',
        'mg', 'mh', 'mi', 'mk', 'ml', 'mn', 'mr', 'ms', 'mt', 'my',
        'na', 'nb', 'nd', 'ne', 'ng', 'nl', 'nn', 'no', 'nr', 'nv', 'ny',
        'oc', 'oj', 'om', 'or', 'os',
        'pa', 'pi', 'pl', 'ps', 'pt',
        'qu',
        'rm', 'rn', 'ro', 'ru', 'rw',
        'sa', 'sc', 'sd', 'se', 'sg', 'si', 'sk', 'sl', 'sm', 'sn', 'so', 'sq', 'sr', 'ss', 'st', 'su', 'sv', 'sw',
        'ta', 'te', 'tg', 'th', 'ti', 'tk', 'tl', 'tn', 'to', 'tr', 'ts', 'tt', 'tw', 'ty',
        'ug', 'uk', 'ur', 'uz',
        'va', 've', 'vi', 'vo',
        'wa', 'wo',
        'xh',
        'yi', 'yo',
        'za', 'zh', 'zu',
    ];

    public function name(): string
    {
        return 'Language Tag';
    }

    public function run(SimpleXMLElement $feed): CheckResult
    {
        $language = $this->extractLanguage($feed);

        if ($language === null) {
            return CheckResult::fail(
                'Language tag is missing.',
                'Add a <language>en-us</language> tag to your channel. This tells podcast directories which language your podcast is in and helps with discoverability.'
            );
        }

        if (! $this->isValidLanguageCode($language)) {
            return CheckResult::warn(
                "Language tag value \"{$language}\" does not appear to be a valid language code.",
                'Use a valid ISO 639-1 language code, optionally with a region (e.g., "en", "en-us", "fr", "de-at"). Common codes: en, es, fr, de, pt, ja, zh.'
            );
        }

        return CheckResult::pass(
            "Language tag is present and valid: \"{$language}\"."
        );
    }

    public function severity(): string
    {
        return 'warning';
    }

    /**
     * Extract the language value from the feed's channel element.
     */
    private function extractLanguage(SimpleXMLElement $feed): ?string
    {
        $channel = $this->getChannel($feed);

        if ($channel === null) {
            return null;
        }

        if (! isset($channel->language)) {
            return null;
        }

        $value = trim((string) $channel->language);

        return $value !== '' ? $value : null;
    }

    /**
     * Validate the language code against ISO 639-1 format.
     *
     * Accepts codes like "en", "en-us", "en-US", "pt-BR", etc.
     */
    private function isValidLanguageCode(string $code): bool
    {
        $normalized = strtolower($code);

        // Match "xx" or "xx-xx" pattern
        if (! preg_match('/^[a-z]{2,3}(-[a-z]{2,4})?$/', $normalized)) {
            return false;
        }

        // Extract the primary language code
        $primary = explode('-', $normalized)[0];

        return in_array($primary, self::COMMON_LANGUAGE_CODES, true);
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
