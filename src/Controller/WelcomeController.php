<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        $name = $request->query->get('name', 'there');

        if ($request->isMethod('POST')) {
            return $this->redirectToRoute('app_cockpit');
        }

        return $this->render('security/Welcome.html.twig', [
            'name' => $name,
        ]);
    }
}
