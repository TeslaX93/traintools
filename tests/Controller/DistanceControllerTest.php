<?php

namespace App\Tests\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Trasy korzystające z bazy. Gdy baza jest nieosiągalna (np. na czystym
 * klonie repozytorium), testy się pomijają zamiast wywalać cały zestaw.
 */
class DistanceControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');

        try {
            $connection->executeQuery('SELECT 1 FROM distance LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Baza niedostepna albo pusta: ' . $e->getMessage());
        }
    }

    public function testFormularzDystansuSieOtwiera(): void
    {
        $crawler = $this->client->request('GET', '/distance');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('form')->count(), 'Powinien byc formularz');
        self::assertGreaterThan(0, $crawler->filter('datalist option')->count(), 'Powinna byc lista stacji');
    }

    public function testApiStacjiZwracaListe(): void
    {
        $this->client->request('GET', '/distance/api/stations');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $stations = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertIsArray($stations);
        self::assertNotEmpty($stations);
        self::assertContainsOnly('string', $stations);
    }

    public function testLosowaStacjaSieOtwiera(): void
    {
        $this->client->request('GET', '/distance/random');

        self::assertResponseIsSuccessful();
    }

    public function testPanelePokazujaListeStacji(): void
    {
        $this->client->request('GET', '/panels');

        self::assertResponseIsSuccessful();
    }

    public function testPaneleOdrzucajaZlyTokenCsrf(): void
    {
        $this->client->request('POST', '/panels', [
            '_token' => 'nieprawidlowy',
            'station_id' => 1,
        ]);

        $response = $this->client->getResponse();

        // przy braku firewalla Symfony oddaje 401, nie 403 - wazne, ze zadanie
        // jest odrzucone i nie ma przekierowania na portalpasazera.pl
        self::assertGreaterThanOrEqual(400, $response->getStatusCode());
        self::assertLessThan(500, $response->getStatusCode());
        self::assertStringNotContainsString('portalpasazera.pl', (string) $response->headers->get('Location'));
    }

    /**
     * Liczy trasę przez formularz. Sprawdza jednocześnie, że graf z cache
     * daje sensowny wynik - a nie że w ogóle się zwraca.
     */
    public function testLiczenieTrasyMiedzyStacjami(): void
    {
        $crawler = $this->client->request('GET', '/distance');

        $stations = $crawler->filter('datalist option')->each(static fn ($o) => $o->attr('value'));
        self::assertGreaterThan(1, count($stations));

        $form = $crawler->selectButton('Dalej')->form();
        $form['simple_distance_form[station1]'] = 'Katowice';
        $form['simple_distance_form[station8]'] = 'Gliwice';

        $this->client->submit($form);

        self::assertResponseIsSuccessful();

        $body = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Kilometrów', $body);
        self::assertStringContainsString('Zabrze', $body, 'Trasa powinna prowadzic przez Zabrze');
    }

    /**
     * Ta sama stacja w obu polach wywracala strone na 500 - biblioteka
     * Dijkstry rzuca wyjatkiem zamiast oddac trase zerowej dlugosci.
     */
    public function testTaSamaStacjaDwaRazyNieWywracaStrony(): void
    {
        $crawler = $this->client->request('GET', '/distance');

        $form = $crawler->selectButton('Dalej')->form();
        $form['simple_distance_form[station1]'] = 'Katowice';
        $form['simple_distance_form[station8]'] = 'Katowice';

        $this->client->submit($form);

        self::assertResponseIsSuccessful();
    }

    public function testJednaStacjaDajeKomunikat(): void
    {
        $crawler = $this->client->request('GET', '/distance');

        $form = $crawler->selectButton('Dalej')->form();
        $form['simple_distance_form[station1]'] = 'Katowice';
        $form['simple_distance_form[station8]'] = 'Takiej Stacji Nie Ma';

        $this->client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            'co najmniej dwie',
            (string) $this->client->getResponse()->getContent()
        );
    }
}
