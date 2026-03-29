<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 30)]
class LocaleListener
{
    public function onKernelRequest(RequestEvent $event): void
    {
        // Récupérer la session de l'utilisateur
        $session = $event->getRequest()->getSession();

        // Vérifier si une langue est définie en session et l'appliquer
        if ($session->has('_locale')) {
            $event->getRequest()->setLocale($session->get('_locale'));
        }
    }
}