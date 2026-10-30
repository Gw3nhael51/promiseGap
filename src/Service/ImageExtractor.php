<?php

namespace App\Service;

use App\Exception\TextExtractionException;

/**
 * Extracts structured Markdown text and metrics from document images using Meta Muse Spark multimodal vision
 */
class ImageExtractor
{
    /**
     * Initializes the multimodal image document extractor
     *
     * @param AiClientInterface $aiClient Cached Meta Muse Spark client supporting multimodal image inputs
     */
    public function __construct(
        private AiClientInterface $aiClient,
    ) {
    }

    /**
     * Analyzes a document photo or scan (PNG, JPEG, WEBP) and transcribes its content into structured Markdown
     *
     * @param string $path Absolute path to the stored image file
     * @return ExtractionResult Sanitized Markdown text and single-page metadata
     * @throws TextExtractionException When the image file cannot be read, is corrupted, or yields empty text
     */
    public function extract(string $path): ExtractionResult
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new TextExtractionException('The image document could not be read.');
        }

        $imageInfo = @getimagesize($path);
        if ($imageInfo === false || ($imageInfo[0] ?? 0) <= 0 || ($imageInfo[1] ?? 0) <= 0) {
            throw new TextExtractionException('The uploaded image file is corrupted or unreadable.');
        }

        $systemPrompt = implode("\n", [
            'You are a high-precision multimodal document vision and OCR engine powered by Meta Muse Spark.',
            'Your task is to transcribe and structure the uploaded document image (contract scan, commercial quote, invoice, administrative letter, SLA table, or technical architecture diagram).',
            'Rules:',
            '1. Start your output with "[Page 1]" on the first line.',
            '2. Transcribe all visible headings, clauses, bullet points, footnotes, and numerical metrics (percentages, SLAs, RPO/RTO, response times, dates, amounts, security standards) verbatim into clean Markdown.',
            '3. If the image contains tables or architecture diagrams, convert them into structured Markdown sections or bulleted technical specifications preserving every label and relationship.',
            '4. Output only the structured Markdown transcription without conversational filler.',
        ]);

        $userPrompt = 'Transcribe this document image into structured Markdown for contractual and technical cross-examination.';

        try {
            $response = $this->aiClient->chat($systemPrompt, $userPrompt, [
                'image_path' => $path,
                'reasoning_effort' => 'low',
                'temperature' => 0.0,
            ]);
        } catch (\Throwable $exception) {
            throw new TextExtractionException('Failed to extract text from the document image via Muse Spark Vision.', $exception);
        }

        $text = TextSanitizer::clean($response->content);
        if ($text === '') {
            throw new TextExtractionException('No readable text could be extracted from the image.');
        }

        if (!str_starts_with($text, '[Page ')) {
            $text = "[Page 1]\n\n" . $text;
        }

        return new ExtractionResult($text, 1);
    }
}
