<?php

namespace App\Tests\Controller;

use App\Entity\ApiUsage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Statystyki użycia API. Zapis dzieje się po odesłaniu odpowiedzi, więc
 * sprawdzamy, czy w bazie faktycznie coś wylądowało — i czy niepowodzenie
 * zapisu nie jest w stanie zepsuć samej odpowiedzi.
 */
class ApiUsageTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    /** Najwyzsze id sprzed testu - wszystko powyzej sprzatamy po sobie. */
    private int $idPrzedTestem = 0;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        try {
            $this->entityManager->getConnection()->executeQuery('SELECT 1 FROM api_usage LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Baza niedostepna albo brak tabeli api_usage: ' . $e->getMessage());
        }

        $this->idPrzedTestem = (int) $this->entityManager->getConnection()
            ->executeQuery('SELECT COALESCE(MAX(id), 0) FROM api_usage')
            ->fetchOne();
    }

    /**
     * Testy dziela baze z aplikacja, wiec kazdy przebieg dopisywalby fikcyjne
     * wywolania do prawdziwych statystyk. Usuwamy dokladnie te wiersze, ktore
     * powstaly w trakcie testu.
     */
    protected function tearDown(): void
    {
        if ($this->idPrzedTestem > 0 || $this->entityManager->getConnection()->isConnected()) {
            $this->entityManager->getConnection()->executeStatement(
                'DELETE FROM api_usage WHERE id > :id',
                ['id' => $this->idPrzedTestem]
            );
        }

        parent::tearDown();
    }

    private function policz(): int
    {
        return (int) $this->entityManager->getConnection()
            ->executeQuery('SELECT COUNT(*) FROM api_usage')
            ->fetchOne();
    }

    /**
     * Uzywamy odpowiedzi 400, bo powstaje bez odpytywania bilkom.pl - a wpis
     * ma powstac niezaleznie od tego, czy zadanie zakonczylo sie sukcesem.
     */
    public function testWywolanieApiZapisujeStatystyke(): void
    {
        $przed = $this->policz();

        $this->client->request('GET', '/bilkom/api/zlytyp/extended/5100069');

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame($przed + 1, $this->policz(), 'Powinien przybyc dokladnie jeden wpis');

        $wpis = $this->entityManager->getRepository(ApiUsage::class)
            ->findOneBy([], ['id' => 'DESC']);

        self::assertNotNull($wpis);
        self::assertSame('app_bilkom_api', $wpis->getEndpoint());
        self::assertSame('zlytyp', $wpis->getType());
        self::assertSame('extended', $wpis->getMode());
        self::assertSame('5100069', $wpis->getStationId());
        self::assertEqualsWithDelta(
            (new \DateTimeImmutable())->getTimestamp(),
            $wpis->getRequestedAt()->getTimestamp(),
            60,
            'Data zapisu powinna byc biezaca'
        );
    }

    public function testZwykleStronyNieSaZapisywane(): void
    {
        $przed = $this->policz();

        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame($przed, $this->policz(), 'Strona glowna nie jest endpointem API');
    }

    /**
     * Spreparowane, bardzo dlugie parametry nie moga wywrocic zapisu przez
     * przekroczenie dlugosci kolumny.
     */
    public function testDlugieParametrySaObcinane(): void
    {
        $przed = $this->policz();

        $this->client->request('GET', '/bilkom/api/' . str_repeat('x', 200) . '/basic/5100069');

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame($przed + 1, $this->policz());

        $wpis = $this->entityManager->getRepository(ApiUsage::class)->findOneBy([], ['id' => 'DESC']);

        self::assertNotNull($wpis);
        self::assertSame(32, mb_strlen((string) $wpis->getType()));
    }
}
