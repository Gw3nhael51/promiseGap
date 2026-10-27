<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller displaying the public landing page with product value proposition
 */
class LandingController extends AbstractController
{
    /**
     * Renders the public landing page with product overview and comparison showcase
     *
     * @return Response HTTP response rendering the landing page
     */
    #[Route('/', name: 'app_landing', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('landing/Landing.html.twig');
    }
}
