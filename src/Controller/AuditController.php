<?php

namespace App\Controller;

use App\Entity\Analysis;
use App\Security\AnalysisVoter;
use App\Service\DemoDealService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller handling live audit progress and evidence-backed results visualization
 */
class AuditController extends AbstractController
{
    /**
     * Displays the live execution progress of contract claim extraction and proof confrontation
     *
     * @param Analysis $analysis Analysis workspace resolved from the route
     * @param DemoDealService $demoDealService Service providing demo data fallback
     * @return Response HTTP response rendering the progress view
     */
    #[Route('/audit/{id}/progress', name: 'app_audit_progress', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(AnalysisVoter::VIEW, subject: 'analysis')]
    public function progress(Analysis $analysis, DemoDealService $demoDealService): Response
    {
        $demoData = $demoDealService->getCloudHostingDealData();

        return $this->render('audit/Audit.html.twig', [
            'analysis' => $analysis,
            'demoData' => $demoData,
        ]);
    }

    /**
     * Displays the interactive audit results dashboard with risk gauge, counters, and proof drawer
     *
     * @param Analysis $analysis Analysis workspace resolved from the route
     * @param DemoDealService $demoDealService Service providing demo claims and evidence
     * @return Response HTTP response rendering the results view
     */
    #[Route('/audit/{id}/results', name: 'app_audit_results', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(AnalysisVoter::VIEW, subject: 'analysis')]
    public function results(Analysis $analysis, DemoDealService $demoDealService): Response
    {
        $demoData = $demoDealService->getCloudHostingDealData();

        return $this->render('audit/Results.html.twig', [
            'analysis' => $analysis,
            'deal' => $demoData['deal'],
            'stats' => $demoData['stats'],
            'claims' => $demoData['claims'],
        ]);
    }
}
