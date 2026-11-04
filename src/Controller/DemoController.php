<?php

namespace App\Controller;

use App\Service\DemoDealService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller displaying the preloaded demo contract catalog for one-click trial
 */
class DemoController extends AbstractController
{
    /**
     * Renders the catalog of preloaded contracts ready for demonstration
     *
     * @param DemoDealService $demoDealService Service providing demo deals catalog
     * @param Request $request Current HTTP request
     * @return Response HTTP response rendering the demo selection template
     */
    #[Route('/demo', name: 'app_demo', methods: ['GET'])]
    public function index(DemoDealService $demoDealService, Request $request): Response
    {
        $session = $request->getSession();
        $demoTrialsCount = $session->get('demo_trials_count', 0);

        return $this->render('demo/Demo.html.twig', [
            'deals' => $demoDealService->getCatalog($request->getLocale()),
            'demoTrialsCount' => $demoTrialsCount,
        ]);
    }

    /**
     * Instantly initializes the selected demo contract and redirects to the audit progress view
     *
     * @param string $dealKey Identifier key of the selected contract deal
     * @param DemoDealService $demoDealService Service creating or loading the demo analysis
     * @param Request $request Current HTTP request
     * @return Response HTTP redirect response to progress view or registration if quota exceeded
     */
    #[Route('/demo/open/{dealKey}', name: 'app_demo_open', methods: ['POST'])]
    public function open(string $dealKey, DemoDealService $demoDealService, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('demo_open', $request->getPayload()->getString('_token'))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        if (!$this->isGranted('ROLE_USER')) {
            $session = $request->getSession();
            $demoCount = $session->get('demo_trials_count', 0);

            if ($demoCount >= 1) {
                $this->addFlash('error', "You have used your 1 free demo trial! Create an account to audit more contracts and unlock the cockpit.");

                return $this->redirectToRoute('app_register');
            }

            $session->set('demo_trials_count', $demoCount + 1);
        }

        $analysis = $demoDealService->getOrCreateDemoAnalysis($dealKey);

        return $this->redirectToRoute('app_audit_progress', [
            'id' => $analysis->getId(),
        ]);
    }
}
