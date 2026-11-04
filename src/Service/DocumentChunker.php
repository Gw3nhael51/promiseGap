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

        $config = [
            'targetChars' => $targetChars,
            'overlapChars' => $overlapChars,
            'title' => $documentTitle,
            'id' => $documentId,
        ];

        $blocks = $this->parseBlocks($cleaned, $documentTitle);

        return $this->assembleChunks($blocks, $config);
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
     * @param array{targetChars: int, overlapChars: int, title: string, id: ?int} $config Chunking configuration
     * @return DocumentChunk[] Generated chunks
     */
    private function assembleChunks(array $blocks, array $config): array
    {
        $chunks = [];
        $accum = [
            'text' => '',
            'section' => $config['title'],
            'page' => 1,
        ];

        foreach ($blocks as $block) {
            $blockText = $block['text'];
            if ($blockText === '') {
                continue;
            }

            if ($accum['text'] !== '' && $block['section'] !== $accum['section']) {
                $this->pushChunk($chunks, $accum['text'], $accum['section'], $accum['page'], $config['title'], $config['id']);
                $accum['text'] = '';
            }

            if (mb_strlen($blockText) > $config['targetChars']) {
                if ($accum['text'] !== '') {
                    $this->pushChunk($chunks, $accum['text'], $accum['section'], $accum['page'], $config['title'], $config['id']);
                    $accum['text'] = '';
                }
                $this->appendSplitBlocks($chunks, $block, $config);
                continue;
            }

            $this->accumulateBlock($chunks, $block, $accum, $config);
        }

        if ($accum['text'] !== '') {
            $this->pushChunk($chunks, $accum['text'], $accum['section'], $accum['page'], $config['title'], $config['id']);
        }

        return $chunks;
    }

    /**
     * Splits an oversized block and appends each slice as a chunk
     *
     * @param DocumentChunk[] $chunks Reference to accumulated chunks
     * @param array{section: string, page: int, text: string} $block Oversized content block
     * @param array{targetChars: int, overlapChars: int, title: string, id: ?int} $config Chunking configuration
     * @return void
     */
    private function appendSplitBlocks(
        array &$chunks,
        array $block,
        array $config,
    ): void {
        foreach ($this->splitLongBlock($block['text'], $config['targetChars'], $config['overlapChars']) as $piece) {
            $this->pushChunk($chunks, $piece, $block['section'], $block['page'], $config['title'], $config['id']);
        }
    }

    /**
     * Merges a regular block into the accumulator or emits a chunk when target size is exceeded
     *
     * @param DocumentChunk[] $chunks Reference to accumulated chunks
     * @param array{section: string, page: int, text: string} $block Content block to merge
     * @param array{text: string, section: string, page: int} $accum Accumulator state
     * @param array{targetChars: int, overlapChars: int, title: string, id: ?int} $config Chunking configuration
     * @return void
     */
    private function accumulateBlock(
        array &$chunks,
        array $block,
        array &$accum,
        array $config,
    ): void {
        $candidate = $accum['text'] === '' ? $block['text'] : ($accum['text'] . "\n\n" . $block['text']);

        if (mb_strlen($candidate) <= $config['targetChars']) {
            if ($accum['text'] === '') {
                $accum['section'] = $block['section'];
                $accum['page'] = $block['page'];
            }
            $accum['text'] = $candidate;

            return;
        }

        $this->pushChunk($chunks, $accum['text'], $accum['section'], $accum['page'], $config['title'], $config['id']);

        $tail = $config['overlapChars'] > 0 ? $this->extractOverlapTail($accum['text'], $config['overlapChars']) : '';
        $accum['section'] = $block['section'];
        $accum['page'] = $block['page'];
        $accum['text'] = $tail !== '' ? ($tail . "\n" . $block['text']) : $block['text'];
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
