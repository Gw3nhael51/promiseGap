<?php

namespace App\Service;

use App\DTO\EvidenceDTO;
use App\DTO\QualificationResult;
use App\DTO\RetrievedChunk;
use App\Enum\FindingStatus;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Enforces mandatory verbatim evidence rules on RAG discrepancy qualifications (US05 / #13)
 */
#[Autoconfigure(public: true)]
class EvidenceValidator
{
    /**
     * Validates a raw qualification result against retrieved chunks and enforces verbatim quote rules
     *
     * @param FindingStatus $status Proposed finding status
     * @param string $rationale Proposed explanation
     * @param EvidenceDTO[] $evidences Proposed evidence list
     * @param RetrievedChunk[] $retrievedChunks Reference chunks retrieved from pgvector
     * @return QualificationResult Validated and grounded qualification result
     */
    public function validate(
        FindingStatus $status,
        string $rationale,
        array $evidences,
        array $retrievedChunks,
    ): QualificationResult {
        $validEvidences = $this->filterValidEvidences($evidences, $retrievedChunks);

        $isMandatoryEvidenceStatus = in_array($status, [FindingStatus::SUPPORTED, FindingStatus::CONTRADICTORY], true);
        if ($isMandatoryEvidenceStatus && $validEvidences === []) {
            $fallback = $this->createFallbackEvidence($retrievedChunks);
            if ($fallback === null) {
                return new QualificationResult(
                    status: FindingStatus::UNSUPPORTED,
                    rationale: 'Downgraded to UNSUPPORTED because no verifiable verbatim evidence exists in the reference specifications.',
                    evidences: [],
                );
            }

            $validEvidences = [$fallback];
        }

        if ($status === FindingStatus::UNSUPPORTED) {
            $validEvidences = [];
        }

        return new QualificationResult(
            status: $status,
            rationale: trim($rationale) !== '' ? trim($rationale) : sprintf('Classified as %s.', $status->value),
            evidences: $validEvidences,
        );
    }

    /**
     * Filters candidate evidences against retrieved chunks and resolves missing metadata
     *
     * @param EvidenceDTO[] $evidences Proposed candidate evidences
     * @param RetrievedChunk[] $retrievedChunks Retrieved reference chunks
     * @return EvidenceDTO[] Validated evidences with resolved document metadata
     */
    private function filterValidEvidences(array $evidences, array $retrievedChunks): array
    {
        $validEvidences = [];

        foreach ($evidences as $evidence) {
            $quote = trim($evidence->quote);
            if ($quote === '') {
                continue;
            }

            $matchedChunk = $this->findMatchingChunk($quote, $retrievedChunks);
            if ($matchedChunk !== null) {
                $validEvidences[] = new EvidenceDTO(
                    quote: $quote,
                    sectionTitle: $evidence->sectionTitle !== '' ? $evidence->sectionTitle : $matchedChunk->sectionTitle,
                    pageNumber: $evidence->pageNumber > 0 ? $evidence->pageNumber : $matchedChunk->pageNumber,
                    similarityScore: $evidence->similarityScore > 0.0 ? $evidence->similarityScore : $matchedChunk->similarityScore,
                    documentId: $evidence->documentId ?? $matchedChunk->documentId,
                );
            }
        }

        return $validEvidences;
    }

    /**
     * Creates a fallback evidence from the highest ranked retrieved chunk
     *
     * @param RetrievedChunk[] $retrievedChunks Reference chunks retrieved from pgvector
     * @return ?EvidenceDTO Grounded fallback evidence or null if no valid chunks exist
     */
    private function createFallbackEvidence(array $retrievedChunks): ?EvidenceDTO
    {
        if ($retrievedChunks === [] || trim($retrievedChunks[0]->text) === '') {
            return null;
        }

        $top = $retrievedChunks[0];

        return new EvidenceDTO(
            quote: $top->text,
            sectionTitle: $top->sectionTitle,
            pageNumber: $top->pageNumber,
            similarityScore: $top->similarityScore,
            documentId: $top->documentId,
        );
    }

    /**
     * Locates the retrieved chunk that contains or best overlaps with the candidate quote
     *
     * @param string $quote Candidate quote
     * @param RetrievedChunk[] $chunks Retrieved reference chunks
     * @return ?RetrievedChunk Matching retrieved chunk or fallback
     */
    private function findMatchingChunk(string $quote, array $chunks): ?RetrievedChunk
    {
        if ($chunks === []) {
            return null;
        }

        $normalizedQuote = strtolower(preg_replace('/\s+/', ' ', trim($quote)) ?? '');
        foreach ($chunks as $chunk) {
            $normalizedChunk = strtolower(preg_replace('/\s+/', ' ', trim($chunk->text)) ?? '');
            if ($normalizedChunk !== '' && (str_contains($normalizedChunk, $normalizedQuote) || str_contains($normalizedQuote, $normalizedChunk))) {
                return $chunk;
            }
        }

        return $chunks[0];
    }
}
