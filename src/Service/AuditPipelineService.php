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
    /**
     * @param CommitmentExtractor $commitmentExtractor Service extracting proposal commitments
     * @param VectorStoreService $vectorStoreService Service indexing and searching reference chunks
     * @param DiscrepancyClassifier $discrepancyClassifier Service qualifying commitments against chunks
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     * @param AiClientInterface $aiClient Cached Meta Muse Spark AI client used for on-demand translation
     */
    public function __construct(
        private CommitmentExtractor $commitmentExtractor,
        private VectorStoreService $vectorStoreService,
        private DiscrepancyClassifier $discrepancyClassifier,
        private EntityManagerInterface $entityManager,
        private AiClientInterface $aiClient,
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
     * @param Analysis $analysis Target analysis workspace
     * @param string $locale Active interface locale ('en' or 'fr')
     * @return array{deal: array<string, mixed>, stats: array<string, int>, claims: array<int, array<string, mixed>>}
     */
    public function buildResultsViewData(Analysis $analysis, string $locale = 'en'): array
    {
        $isFr = $locale === 'fr';
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
                'label' => $isFr ? match ($status) {
                    FindingStatus::CONTRADICTORY => 'Contradictoire',
                    FindingStatus::UNSUPPORTED => 'Non supporté',
                    FindingStatus::AMBIGUOUS => 'Ambigu',
                    FindingStatus::SUPPORTED => 'Supporté',
                } : $status->label(),
                'sev' => $isFr ? match ($status) {
                    FindingStatus::CONTRADICTORY => 'Critique',
                    FindingStatus::UNSUPPORTED => 'Élevée',
                    FindingStatus::AMBIGUOUS => 'Moyenne',
                    FindingStatus::SUPPORTED => 'Faible',
                } : match ($status) {
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
                'psrc' => sprintf('%s · p.%d', $isFr ? 'Proposition' : 'Proposal', $commitment->getSourcePage() ?? 1),
                'hasEv' => $hasEv,
                'eb' => '',
                'em' => $hasEv ? (string) $evidence->getQuote() : '',
                'ea' => '',
                'esrc' => $hasEv
                    ? sprintf('%s · p.%d', $evidence->getSectionTitle() ?? ($isFr ? 'Specs Tech' : 'Tech Specs'), $evidence->getPageNumber() ?? 1)
                    : ($isFr ? 'Aucune section correspondante dans les spécifications techniques' : 'No matching section in technical specifications'),
                'why' => (string) ($finding?->getRationale() ?? ''),
            ];
        }

        if ($isFr && $claims !== []) {
            $claims = $this->translateClaimsToFrench($claims);
        }

        $total = max(1, count($claims));
        $weightedRisk = (int) min(100, round((($stats['con'] * 100) + ($stats['uns'] * 75) + ($stats['amb'] * 40)) / $total));

        $proposalDoc = $analysis->getDocuments()->filter(static fn ($d) => $d->getDocumentType() === DocumentType::PROPOSAL)->first();
        $specDoc = $analysis->getDocuments()->filter(static fn ($d) => $d->getDocumentType() === DocumentType::TECHNICAL_SPEC)->first();

        return [
            'deal' => [
                'id' => 'analysis-' . $analysis->getId(),
                'title' => (string) $analysis->getTitle(),
                'tag' => $isFr ? 'AUDIT PERSONNALISÉ' : 'CUSTOM AUDIT',
                'risk' => $isFr
                    ? ($weightedRisk >= 60 ? 'Risque élevé' : ($weightedRisk >= 30 ? 'Risque modéré' : 'Risque faible'))
                    : ($weightedRisk >= 60 ? 'High risk' : ($weightedRisk >= 30 ? 'Medium risk' : 'Low risk')),
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

    /**
     * Translates claim promises, evidence quotes, and discrepancy rationales into French via Meta Muse Spark
     *
     * @param array<int, array<string, mixed>> $claims Claims array in English
     * @return array<int, array<string, mixed>> Translated claims array in French
     */
    private function translateClaimsToFrench(array $claims): array
    {
        $payload = array_map(static fn (array $c): array => [
            'id' => $c['id'],
            'pm' => $c['pm'],
            'em' => $c['em'],
            'why' => $c['why'],
        ], $claims);

        try {
            $data = $this->aiClient->completeJson(
                'You are a legal and technical translator powered by Meta Muse Spark. Translate the following contract audit claims into concise, natural French. Preserve numbers, percentages, and technical standards (SLA, RPO, AES-256, TLS 1.3, ISO 27001, SOC 2). Return a JSON object with a "translations" array containing objects with "id", "pm", "em", and "why".',
                json_encode(['items' => $payload], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                [
                    'type' => 'object',
                    'required' => ['translations'],
                ],
                ['reasoning_effort' => 'low'],
            );

            $byId = [];
            foreach ($data['translations'] ?? [] as $tr) {
                if (is_array($tr) && isset($tr['id'])) {
                    $byId[(string) $tr['id']] = $tr;
                }
            }

            foreach ($claims as &$claim) {
                $tr = $byId[$claim['id']] ?? null;
                if ($tr !== null) {
                    $pmFr = trim((string) ($tr['pm'] ?? $claim['pm']));
                    $claim['pm'] = $pmFr !== '' ? $pmFr : $claim['pm'];
                    $claim['promise'] = mb_substr($claim['pm'], 0, 90);
                    if ($claim['hasEv'] && !empty($tr['em'])) {
                        $claim['em'] = trim((string) $tr['em']);
                    }
                    if (!empty($tr['why'])) {
                        $claim['why'] = trim((string) $tr['why']);
                    }
                }
            }
            unset($claim);
        } catch (\Throwable) {
            return $claims;
        }

        return $claims;
    }
}
