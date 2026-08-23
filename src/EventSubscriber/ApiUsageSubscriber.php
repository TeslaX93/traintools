<?php

namespace App\EventSubscriber;

use App\Entity\ApiUsage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Zapisuje wywołania publicznego API do tabeli api_usage.
 *
 * Zapis dzieje się na zdarzeniu TERMINATE, czyli po odesłaniu odpowiedzi -
 * statystyki nie wydłużają więc czasu oczekiwania użytkownika. Błąd zapisu
 * jest logowany, ale nigdy nie przerywa działania API: statystyka nie jest
 * warta zepsucia odpowiedzi.
 */
class ApiUsageSubscriber implements EventSubscriberInterface
{
    /** Nazwy tras, nie ścieżki - przetrwają zmianę adresu. */
    private const API_ROUTES = [
        'app_bilkom_api',
        'app_stations_api',
        'station_map_data',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::TERMINATE => 'onKernelTerminate'];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if (!in_array($route, self::API_ROUTES, true)) {
            return;
        }

        try {
            $this->entityManager->persist(new ApiUsage(
                $route,
                $this->parametr($request->attributes->get('type')),
                $this->parametr($request->attributes->get('mode')),
                $this->parametr($request->attributes->get('stationId')),
            ));
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Nie udalo sie zapisac statystyki API: ' . $e->getMessage());
        }
    }

    /**
     * Parametry sa w bazie ograniczone dlugoscia - obcinamy, zeby spreparowane
     * zadanie nie wywracalo zapisu.
     */
    private function parametr(mixed $wartosc): ?string
    {
        if (!is_scalar($wartosc)) {
            return null;
        }

        return mb_substr((string) $wartosc, 0, 32);
    }
}
