<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Najtańszy możliwy bezpiecznik: sprawdza, że strony w ogóle odpowiadają.
 *
 * Taki test złapałby /frequency zwracające 500 (cała implementacja była
 * zakomentowana) oraz stationsList wywalające się na setContent() wołanym
 * na stringu - obie te trasy leżały nietknięte, bo nikt tam nie zaglądał.
 *
 * Celowo nie ma tu tras wymagających bazy ani sieci - te są osobno.
 */
class RoutesSmokeTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * @dataProvider provideRoutes
     */
    public function testRouteReturnsExpectedStatus(string $url, int $expectedStatus): void
    {
        $this->client->request('GET', $url);

        self::assertSame(
            $expectedStatus,
            $this->client->getResponse()->getStatusCode(),
            sprintf('Trasa %s powinna zwracac %d', $url, $expectedStatus)
        );
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function provideRoutes(): iterable
    {
        yield 'strona glowna' => ['/', 200];
        yield 'generator tablic' => ['/ztr', 200];
        yield 'opis API Bilkom' => ['/bilkom', 200];

        // frekwencja jest nieukonczona i ma swiadomie zwracac 404, a nie 500
        yield 'frekwencja (nieukonczona)' => ['/frequency', 404];
        yield 'frekwencja json (nieukonczona)' => ['/frequency/json', 404];

        yield 'nieistniejaca trasa' => ['/tego-na-pewno-nie-ma', 404];
    }

    public function testHomepageLinksToMainSections(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();

        $hrefs = $crawler->filter('a')->each(static fn ($a) => $a->attr('href'));

        foreach (['/ztr', '/distance', '/panels', '/bilkom'] as $expected) {
            self::assertContains($expected, $hrefs, sprintf('Strona glowna powinna linkowac do %s', $expected));
        }
    }
}
