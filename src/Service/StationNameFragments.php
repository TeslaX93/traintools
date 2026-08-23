<?php

namespace App\Service;

use App\Repository\StationRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Początkowe fragmenty prawdziwych nazw stacji, używane przez animację na
 * stronie głównej.
 *
 * Wcześniej losowały się tam ciągi z całego alfabetu łacińskiego, więc
 * pojawiały się litery, których polskie nazewnictwo nie zna (Q, V, X).
 */
class StationNameFragments
{
    private const CACHE_KEY = 'station.fragments.v1';

    /** Lista zmienia się tylko po przecrawlowaniu katalogu stacji. */
    private const TTL = 86400;

    private const MIN_LENGTH = 3;

    private const MAX_LENGTH = 5;

    /** Ile fragmentów trafia do strony - tyle wystarczy na wrażenie losowości. */
    private const LIMIT = 400;

    public function __construct(
        private readonly StationRepository $stationRepository,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return list<string>
     */
    public function get(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(self::TTL);

            $fragments = [];
            foreach ($this->stationRepository->findAllNames() as $name) {
                $fragment = $this->toFragment($name);
                if ($fragment !== null) {
                    $fragments[$fragment] = true;
                }
            }

            $fragments = array_keys($fragments);
            sort($fragments);

            return $fragments;
        });
    }

    /**
     * Losowa próbka - do szablonu nie ma po co wysyłać kilku tysięcy pozycji.
     *
     * @return list<string>
     */
    public function sample(int $limit = self::LIMIT): array
    {
        $fragments = $this->get();

        if (count($fragments) <= $limit) {
            shuffle($fragments);

            return $fragments;
        }

        $keys = array_rand($fragments, $limit);
        $sample = array_map(static fn ($key) => $fragments[$key], (array) $keys);
        shuffle($sample);

        return $sample;
    }

    /**
     * "Katowice Załęże" -> "Katow", "Żywiec" -> "Żywie", "Hel" -> "Hel".
     */
    private function toFragment(string $name): ?string
    {
        // pierwszy człon nazwy: "Warszawa Centralna" -> "Warszawa"
        $first = preg_split('/\s+/u', trim($name))[0] ?? '';
        $first = rtrim($first, '.,');

        if (mb_strlen($first) < self::MIN_LENGTH) {
            return null;
        }

        // same litery - odpadaja skroty typu "PKP" pisane wersalikami i numery
        if (!preg_match('/^\p{Lu}\p{Ll}+$/u', $first)) {
            return null;
        }

        return mb_substr($first, 0, self::MAX_LENGTH);
    }
}
