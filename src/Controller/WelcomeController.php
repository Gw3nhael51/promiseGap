<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller managing initial onboarding and workspace role configuration
 */
class WelcomeController extends AbstractController
{
    /**
     * Renders the welcome onboarding wizard for newly registered users
     *
     * @param Request $request Current HTTP request
     * @return Response HTTP response rendering the onboarding template
     */
    #[Route('/welcome', name: 'app_welcome', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $name = mb_substr(trim(strip_tags($request->query->getString('name', 'there'))), 0, 50) ?: 'there';

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('welcome', $request->getPayload()->getString('_token'))) {
                throw new AccessDeniedHttpException('Invalid CSRF token.');
            }

            return $this->redirectToRoute('app_cockpit');
        }

        return $this->render('security/Welcome.html.twig', [
            'name' => $name,
        ]);
    }
}
