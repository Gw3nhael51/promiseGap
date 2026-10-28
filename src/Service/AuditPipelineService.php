<?php

namespace App\Service;

use App\Entity\Analysis;
use App\Enum\AnalysisStatus;
use App\Enum\DocumentType;
use App\Enum\ExtractionStatus;
use App\Enum\FindingStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Orchestrates the end-to-end RAG audit pipeline from extracted text to discrepancy findings (#13)
 */
#[Autoconfigure(public: true)]
class AuditPipelineService
{
    public function __construct(
        private CommitmentExtractor $commitmentExtractor,
        private VectorStoreService $vectorStoreService,
        private DiscrepancyClassifier $discrepancyClassifier,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Runs commitment extraction, pgvector indexing and discrepancy qualification when all documents are extracted
     *
     * @param Analysis $analysis Target analysis workspace
     * @return bool True if the pipeline ran to completion
     */
    public function runIfReady(Analysis $analysis): bool
    {
        $documents = $analysis->getDocuments();
        if ($documents->isEmpty()) {
            return false;
        }

        foreach ($documents as $document) {
            if ($document->getExtractionStatus() === ExtractionStatus::PENDING) {
                return false;
            }
        }

        // Ignorer : Ne pas relancer le pipeline si des engagements ont déjà été extraits
        if (!$analysis->getCommitments()->isEmpty()) {
            return false;
        }

        $this->run($analysis);

        return true;
    }

    /**
     * Executes the full RAG discrepancy pipeline on an Analysis workspace
     */
    public function run(Analysis $analysis): void
    {
        $this->vectorStoreService->indexAnalysisDocuments($analysis);
        $commitments = $this->commitmentExtractor->extractForAnalysis($analysis);

        foreach ($commitments as $commitment) {
            $topChunks = $this->vectorStoreService->searchSimilar(
                (int) $analysis->getId(),
                (string) $commitment->getVerbatimText(),
                4,
            );
            $this->discrepancyClassifier->qualifyAndPersist($commitment, $topChunks);
        }

        $analysis->setStatus(AnalysisStatus::COMPLETED);
        $this->entityManager->flush();
    }

    /**
     * Formats persisted Commitment, Finding and Evidence entities for the Results.html.twig template
     *
     * @return array{deal: array<string, mixed>, stats: array<string, int>, claims: array<int, array<string, mixed>>}
     */
    public function buildResultsViewData(Analysis $analysis): array
    {
        $stats = ['con' => 0, 'uns' => 0, 'amb' => 0, 'sup' => 0];
        $claims = [];
        $index = 1;

        foreach ($analysis->getCommitments() as $commitment) {
            $finding = $commitment->getFinding();
            $status = $finding?->getStatus() ?? FindingStatus::AMBIGUOUS;
            $short = $status->shortCode();
            $stats[$short] = ($stats[$short] ?? 0) + 1;

            $evidence = $finding?->getEvidences()->first() ?: null;
            $hasEv = $evidence !== null && trim((string) $evidence->getQuote()) !== '';

            $claims[] = [
                'id' => 'P' . $index++,
                'st' => $short,
                'label' => $status->label(),
                'sev' => match ($status) {
                    FindingStatus::CONTRADICTORY => 'Critical',
                    FindingStatus::UNSUPPORTED => 'High',
                    FindingStatus::AMBIGUOUS => 'Medium',
                    FindingStatus::SUPPORTED => 'Low',
                },
                'conf' => sprintf('%d%%', (int) round($commitment->getConfidenceScore() * 100)),
                'sim' => sprintf('%.2f', $evidence !== null ? $evidence->getSimilarityScore() : 0.0),
                'promise' => mb_substr((string) $commitment->getVerbatimText(), 0, 90),
                'pb' => '',
                'pm' => (string) $commitment->getVerbatimText(),
                'pa' => '',
                'psrc' => sprintf('Proposal · p.%d', $commitment->getSourcePage() ?? 1),
                'hasEv' => $hasEv,
                'eb' => '',
                'em' => $hasEv ? (string) $evidence->getQuote() : '',
                'ea' => '',
                'esrc' => $hasEv
                    ? sprintf('%s · p.%d', $evidence->getSectionTitle() ?? 'Tech Specs', $evidence->getPageNumber() ?? 1)
                    : 'No matching section in technical specifications',
                'why' => (string) ($finding?->getRationale() ?? ''),
            ];
        }

        $total = max(1, count($claims));
        $weightedRisk = (int) min(100, round((($stats['con'] * 100) + ($stats['uns'] * 75) + ($stats['amb'] * 40)) / $total));

        $proposalDoc = $analysis->getDocuments()->filter(static fn ($d) => $d->getDocumentType() === DocumentType::PROPOSAL)->first();
        $specDoc = $analysis->getDocuments()->filter(static fn ($d) => $d->getDocumentType() === DocumentType::TECHNICAL_SPEC)->first();

        return [
            'deal' => [
                'id' => 'analysis-' . $analysis->getId(),
                'title' => (string) $analysis->getTitle(),
                'tag' => 'CUSTOM AUDIT',
                'risk' => $weightedRisk >= 60 ? 'High risk' : ($weightedRisk >= 30 ? 'Medium risk' : 'Low risk'),
                'riskColor' => $weightedRisk >= 60 ? '#FF7B7B' : ($weightedRisk >= 30 ? '#F5B04A' : '#4ADE9A'),
                'riskBg' => $weightedRisk >= 60 ? 'rgba(255,123,123,0.14)' : 'rgba(74,222,154,0.14)',
                'riskScore' => $weightedRisk,
                'proposalName' => $proposalDoc ? (string) $proposalDoc->getOriginalFilename() : 'Proposal.pdf',
                'specsName' => $specDoc ? (string) $specDoc->getOriginalFilename() : 'Technical_Specs.md',
            ],
            'stats' => $stats,
            'claims' => $claims,
        ];
    }
}
