<?php

namespace App\Services\Nlp;

/**
 * Stage 1: normalize and validate review text.
 */
final class TextNormalizer
{
    /** Clean untrusted text for storage: no tags, control chars, or runs of whitespace. */
    public function clean(?string $text): string
    {
        $text = (string) $text;
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        }
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{200B}-\x{200F}\x{FEFF}]/u', '', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }

    /** Content fingerprint: stable across case, punctuation, and spacing differences. */
    public function fingerprint(?string $title, string $body): string
    {
        $canon = mb_strtolower(trim(($title ? $title.' ' : '').$body));
        $canon = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $canon) ?? '';

        return hash('sha256', trim($canon));
    }

    /** @return string[] lowercase tokens (apostrophes and hyphens kept inside words) */
    public function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $text = str_replace(['’', '‘'], "'", $text);
        preg_match_all("/[\p{L}\p{N}]+(?:['\-][\p{L}\p{N}]+)*/u", $text, $m);

        return $m[0];
    }

    /** @return string[] */
    public function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+|\s*[\r\n]+\s*/u', $text) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));
    }

    /** Is the text usable for analysis? Returns a reason string if not, null if ok. */
    public function unusableReason(string $cleanBody): ?string
    {
        $min = (int) config('reviewlens.import.min_review_chars');
        if ($cleanBody === '') {
            return 'Review text is empty.';
        }
        if (mb_strlen($cleanBody) < $min) {
            return "Review text is shorter than {$min} characters.";
        }
        if (mb_strlen($cleanBody) > (int) config('reviewlens.import.max_review_chars')) {
            return 'Review text exceeds the maximum length.';
        }
        if (count($this->tokenize($cleanBody)) < 2) {
            return 'Review text has too few words.';
        }
        if (! preg_match('/\p{L}{2,}/u', $cleanBody)) {
            return 'Review text contains no words.';
        }

        return null;
    }

    /** Convert a 0-5 / 0-10 / 0-100 rating to a 1-5 star integer, or null. */
    public function normalizeRating(mixed $raw): ?int
    {
        if ($raw === null) {
            return null;
        }
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $scale = 5.0;
        if (preg_match('#^([\d.,]+)\s*/\s*([\d.]+)$#', $raw, $m)) {
            $raw = $m[1];
            $scale = (float) $m[2];
        }
        $raw = str_replace(',', '.', preg_replace('/[^\d.,]/', '', $raw) ?? '');
        if ($raw === '' || ! is_numeric($raw) || $scale <= 0) {
            return null;
        }
        $value = (float) $raw;
        if ($scale === 5.0 && $value > 5) {
            return null;
        }
        $normalized = $value / $scale * 5;
        if ($normalized < 0.5 || $normalized > 5.0) {
            return null;
        }

        return (int) max(1, min(5, round($normalized)));
    }
}
