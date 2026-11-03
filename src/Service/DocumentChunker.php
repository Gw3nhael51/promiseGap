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

        $blocks = $this->parseBlocks($cleaned, $documentTitle);

        return $this->assembleChunks($blocks, $documentTitle, $documentId, $targetChars, $overlapChars);
    }

    /**
     * Parses sanitized text into structured content blocks with page numbers and section hierarchy
     *
     * @param string $cleaned Sanitized markdown or text
     * @param string $documentTitle Document title used as default section
     * @return array<int, array{section: string, page: int, text: string}> Parsed content blocks
     */
    private function parseBlocks(string $cleaned, string $documentTitle): array
    {
        $lines = preg_split('/\R/', $cleaned) ?: [];
        $currentPage = 1;
        $headingStack = [];
        $currentSection = $documentTitle;

        $blocks = [];
        $buffer = [];
        $bufferPage = 1;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (preg_match('/^\[Page\s+(\d+)\]$/i', $trimmed, $pageMatch) === 1) {
                $this->flushBlockBuffer($buffer, $blocks, $currentSection, $bufferPage);
                $currentPage = (int) $pageMatch[1];
                continue;
            }

            if (preg_match('/^(#{1,4})\s+(.+)$/', $trimmed, $headingMatch) === 1) {
                $this->flushBlockBuffer($buffer, $blocks, $currentSection, $bufferPage);
                $level = strlen($headingMatch[1]);
                $title = trim($headingMatch[2], " #*`");
                $headingStack = array_slice($headingStack, 0, $level - 1);
                $headingStack[$level - 1] = $title;
                $currentSection = implode(' > ', array_filter($headingStack));
                continue;
            }

            if ($trimmed === '') {
                $this->flushBlockBuffer($buffer, $blocks, $currentSection, $bufferPage);
                continue;
            }

            if ($buffer === []) {
                $bufferPage = $currentPage;
            }
            $buffer[] = $trimmed;
        }

        $this->flushBlockBuffer($buffer, $blocks, $currentSection, $bufferPage);

        return $blocks;
    }

    /**
     * Flushes buffered lines into a content block if non-empty
     *
     * @param array<int, string> $buffer Reference to buffer lines
     * @param array<int, array{section: string, page: int, text: string}> $blocks Reference to parsed blocks
     * @param string $currentSection Current active section title
     * @param int $bufferPage Page number of first buffered line
     * @return void
     */
    private function flushBlockBuffer(array &$buffer, array &$blocks, string $currentSection, int $bufferPage): void
    {
        $joined = trim(implode("\n", $buffer));
        if ($joined !== '') {
            $blocks[] = [
                'section' => $currentSection,
                'page' => $bufferPage,
                'text' => $this->cleanFormattingArtifacts($joined),
            ];
        }
        $buffer = [];
    }

    /**
     * Assembles content blocks into overlapping DocumentChunk instances
     *
     * @param array<int, array{section: string, page: int, text: string}> $blocks Structured content blocks
     * @param string $documentTitle Document title
     * @param ?int $documentId Optional Document ID
     * @param int $targetChars Target characters per chunk
     * @param int $overlapChars Characters of overlap between chunks
     * @return DocumentChunk[] Generated chunks
     */
    private function assembleChunks(
        array $blocks,
        string $documentTitle,
        ?int $documentId,
        int $targetChars,
        int $overlapChars,
    ): array {
        $chunks = [];
        $accumText = '';
        $accumSection = $documentTitle;
        $accumPage = 1;

        foreach ($blocks as $block) {
            $blockText = $block['text'];
            if ($blockText === '') {
                continue;
            }

            if ($accumText !== '' && $block['section'] !== $accumSection) {
                $this->pushChunk($chunks, $accumText, $accumSection, $accumPage, $documentTitle, $documentId);
                $accumText = '';
            }

            if (mb_strlen($blockText) > $targetChars) {
                if ($accumText !== '') {
                    $this->pushChunk($chunks, $accumText, $accumSection, $accumPage, $documentTitle, $documentId);
                    $accumText = '';
                }
                $this->appendSplitBlocks($chunks, $block, $documentTitle, $documentId, $targetChars, $overlapChars);
                continue;
            }

            $accumText = $this->accumulateBlock(
                $chunks,
                $block,
                $accumText,
                $accumSection,
                $accumPage,
                $documentTitle,
                $documentId,
                $targetChars,
                $overlapChars,
            );
        }

        if ($accumText !== '') {
            $this->pushChunk($chunks, $accumText, $accumSection, $accumPage, $documentTitle, $documentId);
        }

        return $chunks;
    }

    /**
     * Splits an oversized block and appends each slice as a chunk
     *
     * @param DocumentChunk[] $chunks Reference to accumulated chunks
     * @param array{section: string, page: int, text: string} $block Oversized content block
     * @param string $documentTitle Document title
     * @param ?int $documentId Document ID
     * @param int $targetChars Target window size
     * @param int $overlapChars Window overlap size
     * @return void
     */
    private function appendSplitBlocks(
        array &$chunks,
        array $block,
        string $documentTitle,
        ?int $documentId,
        int $targetChars,
        int $overlapChars,
    ): void {
        foreach ($this->splitLongBlock($block['text'], $targetChars, $overlapChars) as $piece) {
            $this->pushChunk($chunks, $piece, $block['section'], $block['page'], $documentTitle, $documentId);
        }
    }

    /**
     * Merges a regular block into the accumulator or emits a chunk when target size is exceeded
     *
     * @param DocumentChunk[] $chunks Reference to accumulated chunks
     * @param array{section: string, page: int, text: string} $block Content block to merge
     * @param string $accumText Currently accumulated chunk text
     * @param string $accumSection Reference to active section title
     * @param int $accumPage Reference to active page number
     * @param string $documentTitle Document title
     * @param ?int $documentId Document ID
     * @param int $targetChars Target window size
     * @param int $overlapChars Overlap size
     * @return string Updated accumulator text
     */
    private function accumulateBlock(
        array &$chunks,
        array $block,
        string $accumText,
        string &$accumSection,
        int &$accumPage,
        string $documentTitle,
        ?int $documentId,
        int $targetChars,
        int $overlapChars,
    ): string {
        $candidate = $accumText === '' ? $block['text'] : ($accumText . "\n\n" . $block['text']);

        if (mb_strlen($candidate) <= $targetChars) {
            if ($accumText === '') {
                $accumSection = $block['section'];
                $accumPage = $block['page'];
            }

            return $candidate;
        }

        $this->pushChunk($chunks, $accumText, $accumSection, $accumPage, $documentTitle, $documentId);

        $tail = $overlapChars > 0 ? $this->extractOverlapTail($accumText, $overlapChars) : '';
        $accumSection = $block['section'];
        $accumPage = $block['page'];

        return $tail !== '' ? ($tail . "\n" . $block['text']) : $block['text'];
    }

    /**
     * Creates and appends a new DocumentChunk to the collection if text is non-empty
     *
     * @param DocumentChunk[] $chunks Reference to accumulated chunks
     * @param string $text Chunk body text
     * @param string $section Section heading
     * @param int $page Source page number
     * @param string $documentTitle Document title
     * @param ?int $documentId Document ID
     * @return void
     */
    private function pushChunk(
        array &$chunks,
        string $text,
        string $section,
        int $page,
        string $documentTitle,
        ?int $documentId,
    ): void {
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
            chunkIndex: count($chunks),
            tokenEstimate: $tokens,
            documentId: $documentId,
        );
    }

    /**
     * Cleans redundant markdown decorations and whitespace inside a paragraph
     *
     * @param string $text Raw paragraph text
     * @return string Sanitized paragraph text
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
     * @param string $text Long text block to partition
     * @param int $targetChars Target window character length
     * @param int $overlapChars Character overlap between consecutive windows
     * @return string[] Array of chunked text slices
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
     *
     * @param string $text Candidate chunk text
     * @param int $overlapChars Number of trailing characters to retain
     * @return string Overlap context tail string
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
