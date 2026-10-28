<?php

namespace App\Service;

use App\Entity\Analysis;
use App\Enum\AnalysisStatus;
use App\Enum\FindingStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Automated RAG benchmark runner computing Precision, Recall and F1-score against ground-truth scenarios (#19)
 */
#[Autoconfigure(public: true)]
class EvaluationRunner
{
    public const float MIN_PRECISION = 0.85;
    public const float MIN_RECALL = 0.80;

    /**
     * @param DocumentChunker $documentChunker Reference document chunking service
     * @param VectorStoreService $vectorStoreService pgvector indexing and retrieval service
     * @param DiscrepancyClassifier $discrepancyClassifier RAG discrepancy qualification engine
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     * @param string $scenariosDir Directory containing ground-truth scenario JSON files
     */
    public function __construct(
        private DocumentChunker $documentChunker,
        private VectorStoreService $vectorStoreService,
        private DiscrepancyClassifier $discrepancyClassifier,
        private EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%/tests/Fixtures/scenarios')]
        private string $scenariosDir = '',
    ) {
    }

    /**
     * Runs all evaluation scenarios through chunking, pgvector indexing and RAG qualification
     *
     * @return array{
     *     totalScenarios: int,
     *     correctPredictions: int,
     *     accuracy: float,
     *     macroPrecision: float,
     *     macroRecall: float,
     *     macroF1: float,
     *     passesAcceptanceCriteria: bool,
     *     perCategory: array<string, array{tp: int, fp: int, fn: int, precision: float, recall: float, f1: float}>,
     *     scenarios: array<int, array<string, mixed>>
     * }
     */
    public function runSuite(?string $customDir = null): array
    {
        $dir = $customDir ?? $this->scenariosDir;
        $files = glob(rtrim($dir, '/') . '/*.json') ?: [];
        sort($files);

        $categories = [
            FindingStatus::SUPPORTED->value,
            FindingStatus::CONTRADICTORY->value,
            FindingStatus::UNSUPPORTED->value,
            FindingStatus::AMBIGUOUS->value,
        ];

        $counts = [];
        foreach ($categories as $cat) {
            $counts[$cat] = ['tp' => 0, 'fp' => 0, 'fn' => 0];
        }

        $evalAnalysis = new Analysis();
        $evalAnalysis->setTitle('Automated Evaluation Benchmark Run');
        $evalAnalysis->setStatus(AnalysisStatus::PROCESSING);
        $this->entityManager->persist($evalAnalysis);
        $this->entityManager->flush();
        $analysisId = (int) $evalAnalysis->getId();

        $scenarioResults = [];
        $correct = 0;

        try {
            foreach ($files as $file) {
                $raw = file_get_contents($file);
                if ($raw === false) {
                    continue;
                }
                /** @var array<string, mixed> $scenario */
                $scenario = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

                $id = (string) ($scenario['id'] ?? basename($file, '.json'));
                $commitment = (string) ($scenario['commitment'] ?? '');
                $technicalSpec = (string) ($scenario['technicalSpec'] ?? '');
                $expected = strtoupper((string) ($scenario['expectedStatus'] ?? 'AMBIGUOUS'));

                // Indexer : Découper et indexer la spécification du scénario dans pgvector
                $this->vectorStoreService->clearAnalysisIndex($analysisId);
                $chunks = $this->documentChunker->chunkText($technicalSpec, $id . '_spec.md');
                $this->vectorStoreService->indexChunks($analysisId, $chunks);

                $retrieved = $this->vectorStoreService->searchSimilar($analysisId, $commitment, 3);
                $qualification = $this->discrepancyClassifier->qualifyText($commitment, $retrieved);
                $predicted = $qualification->status->value;

                $isMatch = $predicted === $expected;
                if ($isMatch) {
                    ++$correct;
                    ++$counts[$expected]['tp'];
                } else {
                    if (isset($counts[$predicted])) {
                        ++$counts[$predicted]['fp'];
                    }
                    if (isset($counts[$expected])) {
                        ++$counts[$expected]['fn'];
                    }
                }

                $scenarioResults[] = [
                    'id' => $id,
                    'title' => (string) ($scenario['title'] ?? $id),
                    'expected' => $expected,
                    'predicted' => $predicted,
                    'match' => $isMatch,
                    'rationale' => $qualification->rationale,
                    'topEvidence' => $qualification->evidences[0]->quote ?? null,
                ];
            }
        } finally {
            $this->vectorStoreService->clearAnalysisIndex($analysisId);
            $this->entityManager->remove($evalAnalysis);
            $this->entityManager->flush();
        }

        $perCategory = [];
        $precisionSum = 0.0;
        $recallSum = 0.0;
        $f1Sum = 0.0;

        foreach ($categories as $cat) {
            $tp = $counts[$cat]['tp'];
            $fp = $counts[$cat]['fp'];
            $fn = $counts[$cat]['fn'];

            $precision = ($tp + $fp) > 0 ? round($tp / ($tp + $fp), 4) : 1.0;
            $recall = ($tp + $fn) > 0 ? round($tp / ($tp + $fn), 4) : 1.0;
            $f1 = ($precision + $recall) > 0.0 ? round((2 * $precision * $recall) / ($precision + $recall), 4) : 0.0;

            $perCategory[$cat] = [
                'tp' => $tp,
                'fp' => $fp,
                'fn' => $fn,
                'precision' => $precision,
                'recall' => $recall,
                'f1' => $f1,
            ];

            $precisionSum += $precision;
            $recallSum += $recall;
            $f1Sum += $f1;
        }

        $numCats = count($categories);
        $totalScenarios = count($scenarioResults);
        $macroPrecision = round($precisionSum / $numCats, 4);
        $macroRecall = round($recallSum / $numCats, 4);
        $macroF1 = round($f1Sum / $numCats, 4);
        $accuracy = $totalScenarios > 0 ? round($correct / $totalScenarios, 4) : 0.0;

        return [
            'totalScenarios' => $totalScenarios,
            'correctPredictions' => $correct,
            'accuracy' => $accuracy,
            'macroPrecision' => $macroPrecision,
            'macroRecall' => $macroRecall,
            'macroF1' => $macroF1,
            'passesAcceptanceCriteria' => $macroPrecision >= self::MIN_PRECISION && $macroRecall >= self::MIN_RECALL,
            'perCategory' => $perCategory,
            'scenarios' => $scenarioResults,
        ];
    }
}
