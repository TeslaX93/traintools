<?php

namespace App\Controller;

use App\Repository\StationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Mapa stacji kolejowych. Dane pochodzą z katalogu Portalu Pasażera,
 * zebranego komendą CrawlStations - każda stacja ma współrzędne i adres.
 */
class StationMapController extends AbstractController
{
    private const CACHE_KEY = 'station.map.v1';

    /** Katalog stacji zmienia się rzadko - odświeżamy raz na dobę. */
    private const TTL = 86400;

    public function __construct(
        private readonly StationRepository $stationRepository,
        private readonly CacheInterface $cache,
    ) {
    }

    #[Route('/mapa', name: 'station_map')]
    public function index(): Response
    {
        return $this->render('map/index.html.twig', [
            'stationCount' => count($this->stations()),
        ]);
    }

    /**
     * Dane pobierane osobno, żeby sama strona ładowała się od razu, a lista
     * stacji mogła być cache'owana przez przeglądarkę.
     */
    #[Route('/mapa/stacje.json', name: 'station_map_data')]
    public function data(): JsonResponse
    {
        $response = new JsonResponse($this->stations());
        $response->setPublic();
        $response->setMaxAge(self::TTL);

        return $response;
    }

    /**
     * @return list<array{name: string, address: string|null, lat: float, lng: float, displayUrl: string|null}>
     */
    private function stations(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(self::TTL);

            // 5 miejsc po przecinku to ok. 1 m dokladnosci - dla znacznika
            // stacji az nadto, a skraca odpowiedz o kilkadziesiat kilobajtow
            return array_map(static function (array $station): array {
                $station['lat'] = round((float) $station['lat'], 5);
                $station['lng'] = round((float) $station['lng'], 5);

                return $station;
            }, $this->stationRepository->findForMap());
        });
    }
}
