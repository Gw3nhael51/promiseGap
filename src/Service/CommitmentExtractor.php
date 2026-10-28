<?php

namespace App\Service;

use App\DTO\CommitmentDTO;
use App\DTO\CommitmentExtractionResult;
use App\Entity\Analysis;
use App\Entity\Commitment;
use App\Enum\DocumentType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Extracts explicit and implicit commercial commitments from proposal documents using Meta Muse Spark (US03)
 */
class CommitmentExtractor
{
    private const string SYSTEM_PROMPT = <<<'PROMPT'
You are an expert B2B contract auditor powered by Meta Muse Spark.
Extract all commercial commitments, promises, guarantees, SLAs, deliverables, timelines, support obligations, security certifications, and performance claims from the provided commercial proposal.
For each commitment:
- "verbatimText": exact verbatim quote from the proposal.
- "category": one of "SLA", "SUPPORT", "SECURITY", "DELIVERY", "GENERAL".
- "confidenceScore": float between 0.0 and 1.0.
- "sourcePage": integer page number where the quote appears (default 1).
PROMPT;

    /**
     * @param AiClientInterface $aiClient AI client (cached Meta Muse Spark service)
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     */
    public function __construct(
        private AiClientInterface $aiClient,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Parses raw proposal text and returns structured CommitmentDTO items
     *
     * @param string $proposalText Extracted text from a commercial proposal
     * @return CommitmentExtractionResult Structured commitments
     */
    public function extractFromText(string $proposalText): CommitmentExtractionResult
    {
        $trimmed = trim($proposalText);
        if ($trimmed === '') {
            return new CommitmentExtractionResult([]);
        }

        $schema = [
            'type' => 'object',
            'required' => ['commitments'],
            'properties' => [
                'commitments' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['verbatimText', 'category', 'confidenceScore', 'sourcePage'],
                    ],
                ],
            ],
        ];

        $data = $this->aiClient->completeJson(self::SYSTEM_PROMPT, $trimmed, $schema);
        $rawItems = is_array($data['commitments'] ?? null) ? $data['commitments'] : [];

        $dtos = [];
        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $verbatim = trim((string) ($item['verbatimText'] ?? ''));
            if ($verbatim === '') {
                continue;
            }
            $category = strtoupper(trim((string) ($item['category'] ?? 'GENERAL'))) ?: 'GENERAL';
            $confidence = max(0.0, min(1.0, (float) ($item['confidenceScore'] ?? 0.9)));
            $page = max(1, (int) ($item['sourcePage'] ?? 1));

            $dtos[] = new CommitmentDTO(
                verbatimText: $verbatim,
                category: $category,
                confidenceScore: $confidence,
                sourcePage: $page,
            );
        }

        return new CommitmentExtractionResult($dtos);
    }

    /**
     * Extracts and persists commitments from all PROPOSAL documents of an Analysis
     *
     * @param Analysis $analysis Target analysis workspace
     * @return Commitment[] Persisted Commitment entities
     */
    public function extractForAnalysis(Analysis $analysis): array
    {
        $persisted = [];

        foreach ($analysis->getDocuments() as $document) {
            if ($document->getDocumentType() !== DocumentType::PROPOSAL) {
                continue;
            }

            $text = (string) $document->getExtractedText();
            if (trim($text) === '') {
                continue;
            }

            $result = $this->extractFromText($text);
            foreach ($result->commitments as $dto) {
                $commitment = new Commitment();
                $commitment->setVerbatimText($dto->verbatimText);
                $commitment->setCategory($dto->category);
                $commitment->setConfidenceScore($dto->confidenceScore);
                $commitment->setSourcePage($dto->sourcePage);

                $analysis->addCommitment($commitment);
                $this->entityManager->persist($commitment);
                $persisted[] = $commitment;
            }
        }

        $this->entityManager->flush();

        return $persisted;
    }
}
