<?php

namespace App\Controller;

use App\Entity\Analysis;
use App\Enum\AnalysisStatus;
use App\Form\AnalysisType;
use App\Repository\AnalysisRepository;
use App\Service\AnalysisCreator;
use App\Service\DemoDealService;
use App\Service\DiscrepancyClassifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller handling the dual dropzone audit cockpit and contract submission
 */
#[IsGranted('ROLE_USER')]
class CockpitController extends AbstractController
{
    /**
     * Renders the cockpit workbench, stores reasoning mode preference, and handles document submissions
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
        $session = $request->getSession();
        $reasoningMode = (string) $session->get('_reasoning_mode', 'auto');

        if ($request->isMethod('POST')) {
            $submittedMode = strtolower(trim($request->request->getString('reasoning_mode', $reasoningMode)));
            if (in_array($submittedMode, DiscrepancyClassifier::ALLOWED_REASONING_MODES, true)) {
                $reasoningMode = $submittedMode;
                $session->set('_reasoning_mode', $reasoningMode);
            }
        }

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

        $page = $request->query->getInt('page', 1);
        $pagination = $analysisRepository->findPaginated($this->getUser()->getUserIdentifier(), $page, 5);

        return $this->render('cockpit/Main.html.twig', [
            'form' => $form,
            'pastAudits' => $pagination['items'],
            'pagination' => $pagination,
            'reasoningMode' => $reasoningMode,
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
