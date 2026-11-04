<?php

namespace App\Service;

/**
 * Holds the outcome of a successful text extraction
 */
final readonly class ExtractionResult
{
    /**
     * Rough average number of characters per token used for estimates
     */
    private const int CHARACTERS_PER_TOKEN = 4;

    /**
     * @param string $text Clean extracted text
     * @param int $pageCount Number of pages (1 for flat text documents)
     */
    public function __construct(
        public string $text,
        public int $pageCount = 1,
    ) {
    }

    /**
     * Returns the number of characters of the extracted text
     *
     * @return int Character count
     */
    public function characterCount(): int
    {
        return mb_strlen($this->text);
    }

    /**
     * Estimates the number of tokens of the extracted text
     *
     * @return int Estimated token count
     */
    public function estimatedTokens(): int
    {
        return (int) ceil($this->characterCount() / self::CHARACTERS_PER_TOKEN);
    }
}
