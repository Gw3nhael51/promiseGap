<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller handling user registration and account onboarding
 */
class RegistrationController extends AbstractController
{
    /**
     * Renders the registration form and processes account creation
     *
     * @param Request $request Current HTTP request
     * @return Response HTTP response rendering register view or redirecting to welcome onboarding
     */
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $name = $request->getPayload()->getString('name');
            $email = $request->getPayload()->getString('email');

            $this->addFlash('success', 'Account created successfully. Welcome to PromiseGap!');

            return $this->redirectToRoute('app_welcome', [
                'name' => $name,
            ]);
        }

        return $this->render('security/Register.html.twig');
    }
}
