<?php

namespace App\Service;

use App\Exception\TextExtractionException;

/**
 * Extracts text from Markdown and plain text files while preserving headers and bullet points
 */
class MarkdownExtractor
{
    /**
     * Reads and normalizes a Markdown or plain text file
     *
     * @param string $path Absolute path of the file
     * @return ExtractionResult Extracted text
     * @throws TextExtractionException When the file is unreadable or empty
     */
    public function extract(string $path): ExtractionResult
    {
        $content = is_file($path) ? file_get_contents($path) : false;
        if ($content === false) {
            throw new TextExtractionException('The document file could not be read.');
        }

        $text = TextSanitizer::clean($content);
        if ($text === '') {
            throw new TextExtractionException('The document is empty.');
        }

        return new ExtractionResult($text);
    }
}
