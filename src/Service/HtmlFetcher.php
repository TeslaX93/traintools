<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Pobieranie stron zewnętrznych serwisów (Bilkom, Infopasażer).
 *
 * Trzy rzeczy, których nie dawało @file_get_contents():
 *  - twardy timeout, więc pojedyncze zapytanie nie blokuje workera PHP na minutę,
 *  - cache współdzielony przez wszystkich odwiedzających, więc N osób patrzących
 *    na tę samą stację = jedno zapytanie do serwisu źródłowego,
 *  - fetchMany() puszcza żądania równolegle zamiast jedno po drugim.
 */
class HtmlFetcher
{
    /** Ile sekund czekamy na pierwszy bajt odpowiedzi. */
    private const TIMEOUT = 8;

    /** Górny limit na całe pobranie, łącznie z przekierowaniami. */
    private const MAX_DURATION = 15;

    private const USER_AGENT = 'KalkulatorKolejowy.pl (+https://kalkulatorkolejowy.pl)';

    private HttpClientInterface $http;

    public function __construct(
        HttpClientInterface $http,
        private readonly CacheItemPoolInterface $cache,
    ) {
        $this->http = $http->withOptions([
            'timeout' => self::TIMEOUT,
            'max_duration' => self::MAX_DURATION,
            'headers' => ['User-Agent' => self::USER_AGENT],
        ]);
    }

    /**
     * @param int $ttl czas życia wpisu w cache (w sekundach)
     *
     * @return string|null null, gdy nie udało się pobrać strony
     */
    public function fetch(string $url, int $ttl): ?string
    {
        return $this->fetchMany([$url], $ttl)[0];
    }

    /**
     * Pobiera wiele adresów naraz. To, czego nie ma w cache, leci równolegle -
     * czas oczekiwania to czas najwolniejszego żądania, a nie ich suma.
     *
     * @param array<array-key, string> $urls
     * @param int                      $ttl  czas życia wpisu w cache (w sekundach)
     *
     * @return array<array-key, string|null> te same klucze co w $urls; null = błąd pobrania
     */
    public function fetchMany(array $urls, int $ttl): array
    {
        $contentByUrl = [];
        $items = [];
        $pending = [];

        foreach (array_unique($urls) as $url) {
            $item = $this->cache->getItem(self::cacheKey($url));
            if ($item->isHit()) {
                $contentByUrl[$url] = $item->get();
                continue;
            }

            $items[$url] = $item;
            $pending[$url] = $this->http->request('GET', $url);
        }

        foreach ($pending as $url => $response) {
            try {
                $content = $response->getContent();
            } catch (\Throwable) {
                continue; // błędów nie cache'ujemy - następne wejście spróbuje ponownie
            }

            $contentByUrl[$url] = $content;
            $this->cache->save($items[$url]->set($content)->expiresAfter($ttl));
        }

        $result = [];
        foreach ($urls as $key => $url) {
            $result[$key] = $contentByUrl[$url] ?? null;
        }

        return $result;
    }

    private static function cacheKey(string $url): string
    {
        return 'html.' . md5($url);
    }
}
