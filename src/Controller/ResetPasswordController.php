<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller managing password recovery requests and token-based reset operations
 */
class ResetPasswordController extends AbstractController
{
    /**
     * Handles forgot password email submission and displays instructions
     *
     * @param Request $request Current HTTP request
     * @return Response HTTP response rendering the forgot password template
     */
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function request(Request $request): Response
    {
        $isSent = false;
        if ($request->isMethod('POST')) {
            $isSent = true;
        }

        return $this->render('security/Forgot.html.twig', [
            'isSent' => $isSent,
        ]);
    }

    /**
     * Handles password reset form submission and confirmation
     *
     * @param Request $request Current HTTP request
     * @return Response HTTP response rendering the password reset template
     */
    #[Route('/reset-password', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function reset(Request $request): Response
    {
        $isSuccess = false;
        if ($request->isMethod('POST')) {
            $isSuccess = true;
        }

        return $this->render('security/Reset.html.twig', [
            'isSuccess' => $isSuccess,
        ]);
    }
}
