<?php

namespace App\Controller;

use App\Entity\Analysis;
use App\Repository\AnalysisRepository;
use App\Service\DemoDealService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller handling live audit progress and evidence-backed results visualization
 */
class AuditController extends AbstractController
{
    /**
     * Displays the live execution progress of contract claim extraction and proof confrontation
     *
     * @param int $id Analysis workspace identifier
     * @param AnalysisRepository $analysisRepository Repository to locate analysis records
     * @param DemoDealService $demoDealService Service providing demo data fallback
     * @return Response HTTP response rendering the progress view
     */
    #[Route('/audit/{id}/progress', name: 'app_audit_progress', methods: ['GET'])]
    public function progress(int $id, AnalysisRepository $analysisRepository, DemoDealService $demoDealService): Response
    {
        $analysis = $analysisRepository->find($id) ?? $demoDealService->getOrCreateDemoAnalysis();
        $demoData = $demoDealService->getCloudHostingDealData();

        return $this->render('audit/Audit.html.twig', [
            'analysis' => $analysis,
            'demoData' => $demoData,
        ]);
    }

    /**
     * Displays the interactive audit results dashboard with risk gauge, counters, and proof drawer
     *
     * @param int $id Analysis workspace identifier
     * @param AnalysisRepository $analysisRepository Repository to locate analysis records
     * @param DemoDealService $demoDealService Service providing demo claims and evidence
     * @return Response HTTP response rendering the results view
     */
    #[Route('/audit/{id}/results', name: 'app_audit_results', methods: ['GET'])]
    public function results(int $id, AnalysisRepository $analysisRepository, DemoDealService $demoDealService): Response
    {
        $analysis = $analysisRepository->find($id) ?? $demoDealService->getOrCreateDemoAnalysis();
        $demoData = $demoDealService->getCloudHostingDealData();

        return $this->render('audit/Results.html.twig', [
            'analysis' => $analysis,
            'deal' => $demoData['deal'],
            'stats' => $demoData['stats'],
            'claims' => $demoData['claims'],
        ]);
    }
}
