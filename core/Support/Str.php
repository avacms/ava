<?php

declare(strict_types=1);

namespace Ava\Support;

/**
 * String helper utilities.
 */
final class Str
{
    /**
     * Convert a string to a URL slug.
     *
     * Letters and digits from any script are kept ("Café" -> "café",
     * "日本語" -> "日本語"); punctuation is dropped and whitespace becomes the
     * separator. Text is NFC-normalised when intl is available, so a macOS
     * (NFD) filename and a browser's (NFC) URL produce the same slug.
     */
    public static function slug(string $value, string $separator = '-'): string
    {
        $value = self::nfc(mb_strtolower($value, 'UTF-8'));
        $value = preg_replace('/[^\p{L}\p{M}\p{N}\s-]+/u', '', $value) ?? '';
        $value = preg_replace('/[\s-]+/u', $separator, $value) ?? '';
        return trim($value, $separator);
    }

    /**
     * Normalise to Unicode NFC when the intl extension is available.
     */
    public static function nfc(string $value): string
    {
        if ($value === '' || !class_exists(\Normalizer::class) || preg_match('/[\x80-\xFF]/', $value) !== 1) {
            return $value;
        }

        $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);

        return is_string($normalized) ? $normalized : $value;
    }

    /**
     * Get the portion of a string before a given value.
     */
    public static function before(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strpos($subject, $search);
        return $pos === false ? $subject : substr($subject, 0, $pos);
    }

    /**
     * Get the portion of a string after a given value.
     */
    public static function after(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strpos($subject, $search);
        return $pos === false ? $subject : substr($subject, $pos + strlen($search));
    }

    /**
     * Limit a string to a given number of characters.
     */
    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit, 'UTF-8') . $end;
    }

    /**
     * Limit a string to a given number of words.
     */
    public static function words(string $value, int $words = 100, string $end = '...'): string
    {
        preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);

        if (!isset($matches[0]) || mb_strlen($value, 'UTF-8') === mb_strlen($matches[0], 'UTF-8')) {
            return $value;
        }

        return rtrim($matches[0]) . $end;
    }
}
