<?php

namespace App\Service;

use App\DTO\EvidenceDTO;
use App\DTO\QualificationResult;
use App\DTO\RetrievedChunk;
use App\Entity\Commitment;
use App\Entity\Document;
use App\Entity\Evidence;
use App\Entity\Finding;
use App\Enum\FindingStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Proof-oriented discrepancy qualification engine comparing commitments to reference chunks (US05 / #13)
 */
#[Autoconfigure(public: true)]
class DiscrepancyClassifier
{
    public const array ALLOWED_REASONING_MODES = ['auto', 'low', 'medium', 'high'];

    private const string SYSTEM_PROMPT = <<<'PROMPT'
You are an enterprise B2B contract compliance and discrepancy qualification engine powered by Meta Muse Spark.
Given a commercial COMMITMENT from a sales proposal and the top RETRIEVED_CHUNKS from technical reference specifications, classify the commitment into one of 4 categories:
- "SUPPORTED": Full alignment backed by explicit technical evidence.
- "UNSUPPORTED": Commitment claimed in the proposal with no technical basis in the reference specifications.
- "CONTRADICTORY": Direct conflict between the proposal commitment and the technical specification (e.g., conflicting SLAs, percentages, response times, hours, scope).
- "AMBIGUOUS": Vague, subjective, or non-measurable phrasing without verifiable acceptance criteria.

You MUST provide a verbatim evidence quote from RETRIEVED_CHUNKS whenever status is SUPPORTED or CONTRADICTORY.
Return strict JSON with keys: "status", "rationale", and "evidence" (array of objects with "documentId", "quote", "sectionTitle", "pageNumber", "similarityScore").
PROMPT;

    /**
     * @param AiClientInterface $aiClient Cached Meta Muse Spark AI client
     * @param EvidenceValidator $evidenceValidator Verbatim evidence validator
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     */
    public function __construct(
        private AiClientInterface $aiClient,
        private EvidenceValidator $evidenceValidator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Resolves the Meta Muse Spark reasoning_effort level ('low', 'medium', or 'high')
     * either from an explicit user choice or adaptively based on clause risk and chunk similarity
     *
     * @param string $commitmentText Commercial commitment text
     * @param RetrievedChunk[] $retrievedChunks Retrieved technical specification chunks
     * @param string $mode User-selected mode ('auto', 'low', 'medium', or 'high')
     * @return string Resolved reasoning effort ('low', 'medium', or 'high')
     */
    public function resolveReasoningEffort(string $commitmentText, array $retrievedChunks, string $mode = 'auto'): string
    {
        $normalizedMode = strtolower(trim($mode));
        if (in_array($normalizedMode, ['low', 'medium', 'high'], true)) {
            return $normalizedMode;
        }

        if ($retrievedChunks === []) {
            return 'low';
        }

        $topSimilarity = $retrievedChunks[0]->similarityScore;
        $hasHighStakesClause = (bool) preg_match(
            '/\b(99\.\d+|penalt|liabilit|indemn|zero\s+data\s+loss|synchron|rpo|rto|iso\s*27001|soc\s*2|gdpr|hipaa|turnkey|guarantee|sla)\b/i',
            $commitmentText,
        );

        if ($hasHighStakesClause) {
            // Borderline semantic similarity on a high-stakes legal/SLA clause requires deep reasoning
            if ($topSimilarity >= 0.35 && $topSimilarity <= 0.70) {
                return 'high';
            }

            return 'medium';
        }

        $hasQuantitativeMetric = (bool) preg_match(
            '/\b\d+(\.\d+)?\s*(%|percent|ms|seconds?|minutes?|hours?|days?|weeks?|months?|gb|tb)\b/i',
            $commitmentText,
        );

        if ($hasQuantitativeMetric) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Qualifies a raw commitment string against a list of retrieved reference chunks
     *
     * @param string $commitmentText Commercial commitment verbatim text
     * @param RetrievedChunk[] $retrievedChunks Top-k retrieved chunks from pgvector
     * @param string $reasoningMode Reasoning mode ('auto', 'low', 'medium', 'high')
     * @return QualificationResult Validated qualification verdict and evidence
     */
    public function qualifyText(string $commitmentText, array $retrievedChunks, string $reasoningMode = 'auto'): QualificationResult
    {
        $serializedChunks = array_map(static fn (RetrievedChunk $chunk): array => [
            'documentId' => $chunk->documentId,
            'documentTitle' => $chunk->documentTitle,
            'sectionTitle' => $chunk->sectionTitle,
            'pageNumber' => $chunk->pageNumber,
            'similarityScore' => $chunk->similarityScore,
            'text' => $chunk->text,
        ], $retrievedChunks);

        $userPrompt = sprintf(
            "COMMITMENT:\n%s\n\nRETRIEVED_CHUNKS:\n%s",
            trim($commitmentText),
            json_encode($serializedChunks, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );

        $schema = [
            'type' => 'object',
            'required' => ['status', 'rationale', 'evidence'],
        ];

        $resolvedEffort = $this->resolveReasoningEffort($commitmentText, $retrievedChunks, $reasoningMode);

        $raw = $this->aiClient->completeJson(
            self::SYSTEM_PROMPT,
            $userPrompt,
            $schema,
            ['reasoning_effort' => $resolvedEffort],
        );
        $statusStr = strtoupper(trim((string) ($raw['status'] ?? 'AMBIGUOUS')));
        $status = FindingStatus::tryFrom($statusStr) ?? FindingStatus::AMBIGUOUS;
        $rationale = (string) ($raw['rationale'] ?? '');

        $evidenceDtos = [];
        if (is_array($raw['evidence'] ?? null)) {
            foreach ($raw['evidence'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $evidenceDtos[] = new EvidenceDTO(
                    quote: (string) ($item['quote'] ?? ''),
                    sectionTitle: (string) ($item['sectionTitle'] ?? 'Technical Specification'),
                    pageNumber: max(1, (int) ($item['pageNumber'] ?? 1)),
                    similarityScore: (float) ($item['similarityScore'] ?? 0.0),
                    documentId: isset($item['documentId']) && $item['documentId'] !== null ? (int) $item['documentId'] : null,
                );
            }
        }

        return $this->evidenceValidator->validate($status, $rationale, $evidenceDtos, $retrievedChunks);
    }

    /**
     * Qualifies a Commitment entity against retrieved chunks and persists the resulting Finding and Evidence
     *
     * @param Commitment $commitment Commitment entity to qualify
     * @param RetrievedChunk[] $retrievedChunks Top-k retrieved chunks from pgvector
     * @param string $reasoningMode Reasoning mode ('auto', 'low', 'medium', 'high')
     * @return Finding Persisted Finding entity with linked Evidence
     */
    public function qualifyAndPersist(Commitment $commitment, array $retrievedChunks, string $reasoningMode = 'auto'): Finding
    {
        $result = $this->qualifyText((string) $commitment->getVerbatimText(), $retrievedChunks, $reasoningMode);

        $finding = $commitment->getFinding() ?? new Finding();
        $finding->setStatus($result->status);
        $finding->setRationale($result->rationale);

        foreach ($finding->getEvidences()->toArray() as $oldEvidence) {
            $finding->removeEvidence($oldEvidence);
        }

        foreach ($result->evidences as $evDto) {
            $evidence = new Evidence();
            $evidence->setQuote($evDto->quote);
            $evidence->setSectionTitle($evDto->sectionTitle);
            $evidence->setPageNumber($evDto->pageNumber);
            $evidence->setSimilarityScore($evDto->similarityScore);

            if ($evDto->documentId !== null) {
                $doc = $this->entityManager->find(Document::class, $evDto->documentId);
                if ($doc !== null) {
                    $evidence->setDocument($doc);
                }
            }

            $finding->addEvidence($evidence);
        }

        $commitment->setFinding($finding);
        $this->entityManager->persist($finding);
        $this->entityManager->flush();

        return $finding;
    }
}
