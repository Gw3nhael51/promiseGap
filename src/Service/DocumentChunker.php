<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

use App\DTO\DocumentChunk;
use App\Entity\Document;

/**
 * Splits technical specification documents into overlapping semantic chunks with structural metadata (#11)
 */
#[Autoconfigure(public: true)]
class DocumentChunker
{
    private const int CHARS_PER_TOKEN = 4;

    /**
     * @param int $targetTokens Target chunk size in tokens (~400-500 tokens)
     * @param int $overlapTokens Overlap between consecutive chunks in tokens (~50 tokens)
     */
    public function __construct(
        private int $targetTokens = 450,
        private int $overlapTokens = 50,
    ) {
    }

    /**
     * Chunks a Document entity into DocumentChunk value objects
     *
     * @param Document $document Technical specification document
     * @return DocumentChunk[] Ordered list of chunks
     */
    public function chunkDocument(Document $document): array
    {
        return $this->chunkText(
            rawText: (string) $document->getExtractedText(),
            documentTitle: (string) ($document->getOriginalFilename() ?? 'Technical Specification'),
            documentId: $document->getId(),
        );
    }

    /**
     * Splits raw text into chunks while preserving section headings and page markers
     *
     * @param string $rawText Extracted text from PDF or Markdown
     * @param string $documentTitle Document filename or title
     * @param int|null $documentId Optional Document entity ID
     * @param int|null $maxTokens Optional override for target chunk size in tokens
     * @param int|null $overlap Optional override for chunk overlap in tokens
     * @return DocumentChunk[] Generated chunks
     */
    public function chunkText(
        string $rawText,
        string $documentTitle = 'Technical Specification',
        ?int $documentId = null,
        ?int $maxTokens = null,
        ?int $overlap = null,
    ): array {
        $cleaned = TextSanitizer::clean($rawText);
        if ($cleaned === '') {
            return [];
        }

        $targetChars = max(120, ($maxTokens ?? $this->targetTokens) * self::CHARS_PER_TOKEN);
        $overlapChars = max(0, min((int) ($targetChars / 2), ($overlap ?? $this->overlapTokens) * self::CHARS_PER_TOKEN));

        $lines = preg_split('/\R/', $cleaned) ?: [];
        $currentPage = 1;
        $headingStack = [];
        $currentSection = $documentTitle;

        /** @var array<int, array{section: string, page: int, text: string}> $blocks */
        $blocks = [];
        $buffer = [];
        $bufferPage = 1;

        $flushBuffer = function () use (&$buffer, &$blocks, &$currentSection, &$bufferPage): void {
            $joined = trim(implode("\n", $buffer));
            if ($joined !== '') {
                $blocks[] = [
                    'section' => $currentSection,
                    'page' => $bufferPage,
                    'text' => $this->cleanFormattingArtifacts($joined),
                ];
            }
            $buffer = [];
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (preg_match('/^\[Page\s+(\d+)\]$/i', $trimmed, $pageMatch) === 1) {
                $flushBuffer();
                $currentPage = (int) $pageMatch[1];
                $bufferPage = $currentPage;
                continue;
            }

            if (preg_match('/^(#{1,4})\s+(.+)$/', $trimmed, $headingMatch) === 1) {
                $flushBuffer();
                $level = strlen($headingMatch[1]);
                $title = trim($headingMatch[2], " #*`");
                $headingStack = array_slice($headingStack, 0, $level - 1);
                $headingStack[$level - 1] = $title;
                $currentSection = implode(' > ', array_filter($headingStack));
                $bufferPage = $currentPage;
                continue;
            }

            if ($trimmed === '') {
                $flushBuffer();
                $bufferPage = $currentPage;
                continue;
            }

            if ($buffer === []) {
                $bufferPage = $currentPage;
            }
            $buffer[] = $trimmed;
        }
        $flushBuffer();

        // Assembler : Regrouper ou découper les blocs selon la taille cible en tokens avec recouvrement
        $chunks = [];
        $chunkIndex = 0;
        $accumText = '';
        $accumSection = $documentTitle;
        $accumPage = 1;

        $emitChunk = function (string $text, string $section, int $page) use (&$chunks, &$chunkIndex, $documentTitle, $documentId): void {
            $normalized = trim($text);
            if ($normalized === '') {
                return;
            }
            $tokens = max(1, (int) ceil(mb_strlen($normalized) / self::CHARS_PER_TOKEN));
            $chunks[] = new DocumentChunk(
                text: $normalized,
                sectionTitle: $section,
                documentTitle: $documentTitle,
                pageNumber: $page,
                chunkIndex: $chunkIndex++,
                tokenEstimate: $tokens,
                documentId: $documentId,
            );
        };

        foreach ($blocks as $block) {
            $blockText = $block['text'];
            if ($blockText === '') {
                continue;
            }

            // Séparer : Couper immédiatement un paragraphe si la section change
            if ($accumText !== '' && $block['section'] !== $accumSection) {
                $emitChunk($accumText, $accumSection, $accumPage);
                $accumText = '';
            }

            if (mb_strlen($blockText) > $targetChars) {
                if ($accumText !== '') {
                    $emitChunk($accumText, $accumSection, $accumPage);
                    $accumText = '';
                }
                foreach ($this->splitLongBlock($blockText, $targetChars, $overlapChars) as $piece) {
                    $emitChunk($piece, $block['section'], $block['page']);
                }
                continue;
            }

            $candidate = $accumText === '' ? $blockText : ($accumText . "\n\n" . $blockText);
            if (mb_strlen($candidate) <= $targetChars) {
                if ($accumText === '') {
                    $accumSection = $block['section'];
                    $accumPage = $block['page'];
                }
                $accumText = $candidate;
            } else {
                $emitChunk($accumText, $accumSection, $accumPage);
                $tail = $overlapChars > 0 ? $this->extractOverlapTail($accumText, $overlapChars) : '';
                $accumSection = $block['section'];
                $accumPage = $block['page'];
                $accumText = $tail !== '' ? ($tail . "\n" . $blockText) : $blockText;
            }
        }

        if ($accumText !== '') {
            $emitChunk($accumText, $accumSection, $accumPage);
        }

        return $chunks;
    }

    /**
     * Cleans redundant markdown decorations and whitespace inside a paragraph
     */
    private function cleanFormattingArtifacts(string $text): string
    {
        $cleaned = preg_replace('/`{3,}.*$/m', '', $text) ?? $text;
        $cleaned = str_replace(['**', '__'], '', $cleaned);
        $cleaned = preg_replace('/[ \t]{2,}/', ' ', $cleaned) ?? $cleaned;

        return trim($cleaned);
    }

    /**
     * Splits an oversized paragraph into overlapping windows at word boundaries
     *
     * @return string[]
     */
    private function splitLongBlock(string $text, int $targetChars, int $overlapChars): array
    {
        $pieces = [];
        $length = mb_strlen($text);
        $step = max(1, $targetChars - $overlapChars);
        $offset = 0;

        while ($offset < $length) {
            $slice = mb_substr($text, $offset, $targetChars);
            $pieces[] = trim($slice);
            if ($offset + $targetChars >= $length) {
                break;
            }
            $offset += $step;
        }

        return $pieces;
    }

    /**
     * Extracts the trailing substring of a chunk for overlap context
     */
    private function extractOverlapTail(string $text, int $overlapChars): string
    {
        if (mb_strlen($text) <= $overlapChars) {
            return $text;
        }

        $tail = mb_substr($text, -$overlapChars);
        $spacePos = mb_strpos($tail, ' ');

        return $spacePos !== false ? trim(mb_substr($tail, $spacePos + 1)) : trim($tail);
    }
}
