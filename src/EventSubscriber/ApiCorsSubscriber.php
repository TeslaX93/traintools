<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Nagłówki CORS dla publicznych endpointów JSON.
 *
 * API jest otwarte, tylko do odczytu i bez uwierzytelniania, więc nie ma
 * powodu, żeby przeglądarka blokowała odpytywanie go z cudzej strony. Bez tych
 * nagłówków każda aplikacja przeglądarkowa — łącznie z zewnętrznymi
 * przeglądarkami specyfikacji OpenAPI — dostaje "Failed to fetch".
 */
class ApiCorsSubscriber implements EventSubscriberInterface
{
    /** Nazwy tras, nie ścieżki - przetrwają zmianę adresu. */
    private const API_ROUTES = [
        'app_bilkom_api',
        'app_stations_api',
        'station_map_data',
    ];

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onKernelResponse'];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('_route');

        if (!in_array($route, self::API_ROUTES, true)) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('Access-Control-Allow-Origin', '*');
        $headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type');
        $headers->set('Access-Control-Max-Age', '86400');
    }
}
