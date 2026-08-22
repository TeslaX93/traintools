<?php

namespace App\Tests\Service;

use App\Service\HtmlFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * HtmlFetcher stoi między aplikacją a Bilkomem i Infopasażerem, więc jego
 * zachowanie przy błędach i powtórzeniach ma realne znaczenie dla tamtych
 * serwisów. Testy jadą na atrapie klienta - żadnego ruchu sieciowego.
 */
class HtmlFetcherTest extends TestCase
{
    public function testPobieraTresc(): void
    {
        $fetcher = new HtmlFetcher(new MockHttpClient([new MockResponse('<html>tablica</html>')]), new ArrayAdapter());

        self::assertSame('<html>tablica</html>', $fetcher->fetch('https://example.test/a', 60));
    }

    public function testDrugieWywolanieIdzieZCache(): void
    {
        $calls = 0;
        $fetcher = new HtmlFetcher($this->countingClient($calls, 'tresc'), new ArrayAdapter());

        self::assertSame('tresc', $fetcher->fetch('https://example.test/a', 60));
        self::assertSame('tresc', $fetcher->fetch('https://example.test/a', 60));
        self::assertSame(1, $calls, 'Drugie wywolanie powinno pojsc z cache');
    }

    public function testBladZwracaNullINieTrafiaDoCache(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', ['http_code' => 503]),
            new MockResponse('juz dziala'),
        ]);
        $fetcher = new HtmlFetcher($client, new ArrayAdapter());

        self::assertNull($fetcher->fetch('https://example.test/a', 60));
        // gdyby blad wpadl do cache, ponowna proba oddalaby null zamiast tresci
        self::assertSame('juz dziala', $fetcher->fetch('https://example.test/a', 60));
    }

    public function testFetchManyZachowujeKluczeWejsciowe(): void
    {
        $client = new MockHttpClient([
            new MockResponse('pierwsza'),
            new MockResponse('druga'),
        ]);
        $fetcher = new HtmlFetcher($client, new ArrayAdapter());

        $result = $fetcher->fetchMany([7 => 'https://example.test/a', 'x' => 'https://example.test/b'], 60);

        self::assertSame([7 => 'pierwsza', 'x' => 'druga'], $result);
    }

    public function testPowtorzonyAdresPobieranyJestRaz(): void
    {
        $calls = 0;
        $fetcher = new HtmlFetcher($this->countingClient($calls, 'ta sama strona'), new ArrayAdapter());

        $result = $fetcher->fetchMany([
            'a' => 'https://example.test/taka-sama',
            'b' => 'https://example.test/taka-sama',
        ], 60);

        self::assertSame(['a' => 'ta sama strona', 'b' => 'ta sama strona'], $result);
        self::assertSame(1, $calls, 'Ten sam adres nie powinien leciec dwa razy');
    }

    /**
     * MockHttpClient::getRequestsCount() nie nadaje sie tutaj: HtmlFetcher wola
     * withOptions(), ktore zwraca klon klienta, wiec licznik zostaje na
     * oryginale. Liczymy wiec wywolania w samym wywolaniu zwrotnym.
     */
    private function countingClient(int &$calls, string $body): MockHttpClient
    {
        return new MockHttpClient(function () use (&$calls, $body) {
            ++$calls;

            return new MockResponse($body);
        });
    }

    public function testPojedynczyBladNiePsujePozostalych(): void
    {
        $client = new MockHttpClient([
            new MockResponse('dobra'),
            new MockResponse('', ['http_code' => 500]),
        ]);
        $fetcher = new HtmlFetcher($client, new ArrayAdapter());

        $result = $fetcher->fetchMany([
            'ok' => 'https://example.test/a',
            'zly' => 'https://example.test/b',
        ], 60);

        self::assertSame(['ok' => 'dobra', 'zly' => null], $result);
    }

    public function testWysylaWlasnyUserAgent(): void
    {
        $seen = null;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$seen) {
            $seen = $options['headers'] ?? [];

            return new MockResponse('ok');
        });

        (new HtmlFetcher($client, new ArrayAdapter()))->fetch('https://example.test/a', 60);

        self::assertNotNull($seen);
        $joined = implode(' ', array_map(static fn ($h) => is_array($h) ? implode(' ', $h) : (string) $h, $seen));
        self::assertStringContainsString('KalkulatorKolejowy.pl', $joined);
    }
}
