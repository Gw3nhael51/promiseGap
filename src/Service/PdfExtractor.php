<?php

namespace App\Service;

use App\Exception\TextExtractionException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Extracts page-by-page text from PDF files using poppler's pdftotext
 */
class PdfExtractor
{
    private const int TIMEOUT_SECONDS = 60;
    private const int MIN_TEXT_LENGTH = 20;

    /**
     * Runs pdftotext and returns the text of each page with a page marker
     *
     * @param string $path Absolute path of the PDF file
     * @return ExtractionResult Extracted text split by page
     * @throws TextExtractionException When the PDF cannot be parsed or contains no text layer
     */
    public function extract(string $path): ExtractionResult
    {
        if (!is_file($path)) {
            throw new TextExtractionException('The document file could not be read.');
        }

        $process = new Process(['pdftotext', '-layout', '-enc', 'UTF-8', $path, '-']);
        $process->setTimeout(self::TIMEOUT_SECONDS);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new TextExtractionException('The PDF took too long to process.');
        } catch (\Throwable) {
            throw new TextExtractionException('The PDF text extractor is not available.');
        }

        if (!$process->isSuccessful()) {
            throw new TextExtractionException('The PDF could not be parsed. It may be corrupted or password protected.');
        }

        $pages = [];
        foreach (explode("\f", $process->getOutput()) as $index => $pageText) {
            $pageText = TextSanitizer::clean($pageText);
            if ($pageText !== '') {
                $pages[] = sprintf("[Page %d]\n%s", $index + 1, $pageText);
            }
        }

        $text = mb_substr(implode("\n\n", $pages), 0, TextSanitizer::MAX_CHARACTERS);
        if (mb_strlen(preg_replace('/\[Page \d+\]|\s+/', '', $text) ?? '') < self::MIN_TEXT_LENGTH) {
            throw new TextExtractionException('This PDF contains no extractable text. It may be scanned or image-only.');
        }

        return new ExtractionResult($text, max(1, count($pages)));
    }
}
