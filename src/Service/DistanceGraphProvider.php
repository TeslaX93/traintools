<?php

namespace App\Service;

use App\Repository\DistanceRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Taniko\Dijkstra\Graph;

/**
 * Graf odległości i lista stacji, trzymane w cache zamiast budowane od nowa
 * przy każdym żądaniu.
 *
 * Budowa grafu z ~3200 krawędzi to ok. 18 ms czystego CPU i dzieje się nawet
 * wtedy, gdy ktoś tylko otworzy formularz. Odtworzenie gotowego obiektu z
 * cache zajmuje ok. 1 ms.
 */
class DistanceGraphProvider
{
    /**
     * Wersja w kluczu, bo w cache ląduje zserializowany obiekt z zewnętrznej
     * biblioteki. Jeżeli aktualizacja taniko/dijkstra zmieni budowę klasy,
     * stary wpis przestanie do niej pasować - wtedy podbijamy "v1" na "v2"
     * zamiast zostawiać niezgodne dane.
     */
    private const GRAPH_KEY = 'distance.graph.v1';

    private const STATIONS_KEY = 'distance.stations.v1';

    /**
     * Dane zmienia wyłącznie komenda DownloadLatestDistances, która sama
     * czyści cache. TTL jest tylko siatką bezpieczeństwa.
     */
    private const TTL = 86400;

    private ?Graph $graph = null;

    /** @var list<string>|null */
    private ?array $stations = null;

    /** @var array<string, int>|null */
    private ?array $stationsIndex = null;

    public function __construct(
        private readonly DistanceRepository $distanceRepository,
        private readonly CacheInterface $cache,
    ) {
    }

    public function getGraph(): Graph
    {
        // zapamiętanie w polu, żeby kilka wywołań w jednym żądaniu nie
        // odczytywało cache w kółko
        return $this->graph ??= $this->cache->get(
            self::GRAPH_KEY,
            function (ItemInterface $item): Graph {
                $item->expiresAfter(self::TTL);

                $graph = Graph::create();
                foreach ($this->distanceRepository->getAllEdges() as $edge) {
                    $graph->add($edge['station_a'], $edge['station_b'], (float) $edge['distance']);
                }

                return $graph;
            }
        );
    }

    /**
     * @return list<string>
     */
    public function getStations(): array
    {
        return $this->stations ??= $this->cache->get(
            self::STATIONS_KEY,
            function (ItemInterface $item): array {
                $item->expiresAfter(self::TTL);

                return $this->distanceRepository->getAllStations();
            }
        );
    }

    public function stationExists(string $name): bool
    {
        // odwrócona tablica zamiast in_array - przy ośmiu polach formularza
        // różnica jest kosmetyczna, ale nie kosztuje nic
        $this->stationsIndex ??= array_flip($this->getStations());

        return isset($this->stationsIndex[$name]);
    }

    /**
     * Do wywołania po imporcie nowych odległości.
     */
    public function invalidate(): void
    {
        $this->cache->delete(self::GRAPH_KEY);
        $this->cache->delete(self::STATIONS_KEY);

        $this->graph = null;
        $this->stations = null;
        $this->stationsIndex = null;
    }
}
