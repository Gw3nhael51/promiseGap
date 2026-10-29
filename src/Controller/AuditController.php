<?php

namespace App\Controller;

use App\Entity\Analysis;
use App\Enum\ExtractionStatus;
use App\Security\AnalysisVoter;
use App\Service\AuditPipelineService;
use App\Service\DemoDealService;
use App\Service\TextExtractionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller managing the audit execution progress and discrepancy results views
 */
class AuditController extends AbstractController
{
    /**
     * Renders the step-by-step live audit progress view and ensures pending documents are processed
     *
     * @param Analysis $analysis Analysis workspace resolved from the route
     * @param DemoDealService $demoDealService Service providing demo data fallback
     * @param TextExtractionService $textExtractionService Document text extraction service
     * @param AuditPipelineService $auditPipelineService End-to-end RAG audit pipeline service
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     * @param Request $request Current HTTP request
     * @return Response HTTP response rendering the progress template
     */
    #[Route('/audit/{id}/progress', name: 'app_audit_progress', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(AnalysisVoter::VIEW, subject: 'analysis')]
    public function progress(
        Analysis $analysis,
        DemoDealService $demoDealService,
        TextExtractionService $textExtractionService,
        AuditPipelineService $auditPipelineService,
        EntityManagerInterface $entityManager,
        Request $request,
    ): Response {
        $this->ensureAnalysisProcessed($analysis, $textExtractionService, $auditPipelineService, $entityManager);
        $locale = $request->getLocale();

        $demoData = $analysis->getCommitments()->isEmpty()
            ? $demoDealService->getCloudHostingDealData($locale)
            : $auditPipelineService->buildResultsViewData($analysis, $locale);

        return $this->render('audit/Audit.html.twig', [
            'analysis' => $analysis,
            'demoData' => $demoData,
        ]);
    }

    /**
     * Renders the discrepancy findings table and source evidence slide-over drawer
     *
     * @param Analysis $analysis Analysis workspace resolved from the route
     * @param DemoDealService $demoDealService Service providing demo claims and evidence
     * @param TextExtractionService $textExtractionService Document text extraction service
     * @param AuditPipelineService $auditPipelineService End-to-end RAG audit pipeline service
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     * @param Request $request Current HTTP request
     * @return Response HTTP response rendering the results dashboard
     */
    #[Route('/audit/{id}/results', name: 'app_audit_results', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(AnalysisVoter::VIEW, subject: 'analysis')]
    public function results(
        Analysis $analysis,
        DemoDealService $demoDealService,
        TextExtractionService $textExtractionService,
        AuditPipelineService $auditPipelineService,
        EntityManagerInterface $entityManager,
        Request $request,
    ): Response {
        $this->ensureAnalysisProcessed($analysis, $textExtractionService, $auditPipelineService, $entityManager);
        $locale = $request->getLocale();

        $demoData = $analysis->getCommitments()->isEmpty()
            ? $demoDealService->getCloudHostingDealData($locale)
            : $auditPipelineService->buildResultsViewData($analysis, $locale);

        return $this->render('audit/Results.html.twig', [
            'analysis' => $analysis,
            'deal' => $demoData['deal'],
            'stats' => $demoData['stats'],
            'claims' => $demoData['claims'],
        ]);
    }

    /**
     * Processes any pending documents synchronously when no background worker has consumed them yet
     *
     * @param Analysis $analysis Analysis workspace to process
     * @param TextExtractionService $textExtractionService Document text extraction service
     * @param AuditPipelineService $auditPipelineService End-to-end RAG audit pipeline service
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     * @return void
     */
    private function ensureAnalysisProcessed(
        Analysis $analysis,
        TextExtractionService $textExtractionService,
        AuditPipelineService $auditPipelineService,
        EntityManagerInterface $entityManager,
    ): void {
        if ($analysis->getDocuments()->isEmpty()) {
            return;
        }

        $extractedAny = false;
        foreach ($analysis->getDocuments() as $document) {
            if ($document->getExtractionStatus() === ExtractionStatus::PENDING) {
                $textExtractionService->process($document);
                $extractedAny = true;
            }
        }

        if ($extractedAny) {
            $entityManager->flush();
        }

        $auditPipelineService->runIfReady($analysis);
    }
}
