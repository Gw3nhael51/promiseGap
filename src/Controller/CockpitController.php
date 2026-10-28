<?php

namespace App\Controller;

use App\Entity\Analysis;
use App\Enum\AnalysisStatus;
use App\Form\AnalysisType;
use App\Repository\AnalysisRepository;
use App\Service\AnalysisCreator;
use App\Service\DemoDealService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller handling the dual dropzone audit cockpit and contract submission
 */
class CockpitController extends AbstractController
{
    /**
     * Renders the cockpit workbench and handles document submissions
     *
     * @param Request $request Current HTTP request
     * @param AnalysisCreator $analysisCreator Service creating the analysis and storing its documents
     * @param AnalysisRepository $analysisRepository Repository to fetch past audits of the current user
     * @return Response HTTP response rendering the cockpit or redirecting to audit progress
     */
    #[Route('/cockpit', name: 'app_cockpit', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        AnalysisCreator $analysisCreator,
        AnalysisRepository $analysisRepository,
    ): Response {
        $analysis = new Analysis();
        $form = $this->createForm(AnalysisType::class, $analysis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $analysisCreator->create(
                    $analysis,
                    $form->get('proposalFile')->getData(),
                    $form->get('technicalSpecFiles')->getData() ?? [],
                    $this->getUser()->getUserIdentifier(),
                    AnalysisStatus::PROCESSING,
                );

                return $this->redirectToRoute('app_audit_progress', [
                    'id' => $analysis->getId(),
                ]);
            } catch (\InvalidArgumentException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        $pastAudits = $analysisRepository->findByOwner($this->getUser()->getUserIdentifier(), 5);

        return $this->render('cockpit/Main.html.twig', [
            'form' => $form,
            'pastAudits' => $pastAudits,
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * Instantly loads the default demo deal and redirects to the audit progress view
     *
     * @param DemoDealService $demoDealService Service providing preloaded contract data
     * @return Response HTTP redirect response to the progress view
     */
    #[Route('/cockpit/demo', name: 'app_cockpit_demo', methods: ['GET', 'POST'])]
    public function loadDemo(DemoDealService $demoDealService): Response
    {
        $demoAnalysis = $demoDealService->getOrCreateDemoAnalysis('cloud-hosting');

        return $this->redirectToRoute('app_audit_progress', [
            'id' => $demoAnalysis->getId(),
        ]);
    }
}
