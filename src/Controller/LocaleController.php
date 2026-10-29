<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller switching the active interface and AI analysis language between English and French
 */
class LocaleController extends AbstractController
{
    /**
     * Stores the selected locale in the user session and redirects back to the referring page
     *
     * @param string $locale Target locale code ('en' or 'fr')
     * @param Request $request Current HTTP request
     * @return Response Redirect response to the previous page or landing page
     */
    #[Route('/locale/{locale}', name: 'app_switch_locale', requirements: ['locale' => 'en|fr'], methods: ['GET', 'POST'])]
    public function switchLocale(string $locale, Request $request): Response
    {
        $request->getSession()->set('_locale', $locale);

        $referer = $request->headers->get('referer');
        if (is_string($referer) && $referer !== '') {
            $parsed = parse_url($referer);
            $path = $parsed['path'] ?? '/';
            $query = isset($parsed['query']) && $parsed['query'] !== '' ? '?' . $parsed['query'] : '';
            if (str_starts_with($path, '/') && !str_starts_with($path, '//')) {
                return $this->redirect($path . $query);
            }
        }

        return $this->redirectToRoute('app_landing');
    }
}
