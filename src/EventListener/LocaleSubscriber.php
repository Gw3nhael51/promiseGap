<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Restores the user's preferred language ('en' or 'fr') from the session on each HTTP request
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
class LocaleSubscriber
{
    /**
     * Applies the session locale to the current request when present
     *
     * @param RequestEvent $event Kernel request event
     * @return void
     */
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->hasPreviousSession()) {
            return;
        }

        $locale = $request->getSession()->get('_locale');
        if (is_string($locale) && in_array($locale, ['en', 'fr'], true)) {
            $request->setLocale($locale);
        }
    }
}
