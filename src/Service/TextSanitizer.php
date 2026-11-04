<?php

namespace App\Service;

/**
 * Normalizes raw extracted text so it can be safely stored and processed
 */
final class TextSanitizer
{
    public const int MAX_CHARACTERS = 1000000;

    /**
     * Removes unsafe characters and normalizes line endings and blank lines
     *
     * @param string $raw Raw text coming from a file or an external tool
     * @return string Clean text, truncated to the maximum allowed length
     */
    public static function clean(string $raw): string
    {
        // Retirer : Supprimer le BOM, les octets nuls refusés par PostgreSQL et l'UTF-8 invalide
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $text = str_replace("\0", '', mb_scrub($text, 'UTF-8'));

        // Normaliser : Unifier les fins de ligne et limiter les lignes vides consécutives
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+\n/', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return mb_substr(trim($text), 0, self::MAX_CHARACTERS);
    }
}
