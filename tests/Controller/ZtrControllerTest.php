<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Generator tablic przyjmował wcześniej surowy $_POST i podawał go dalej do
 * szablonu. Te testy pilnują, żeby spreparowane wejście nie wracało ani
 * błędem 500, ani własną treścią wstrzykniętą w stronę.
 */
class ZtrControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testPoprawneDaneRenderujaTablice(): void
    {
        $this->client->request('POST', '/ztrres', [
            'templateType' => 'pkpic',
            'trainNo' => 'IC 1234',
            'trainName' => 'GWAREK',
            'numberColor' => '#ff0000',
            'nameColor' => '#00ff00',
            'firstStation' => 'Katowice',
            'lastStation' => 'Gdynia Glowna',
            'st' => ['Sosnowiec Glowny', 'Warszawa Centralna'],
            'stb' => ['1' => 'on'],
        ]);

        self::assertResponseIsSuccessful();

        $body = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('IC 1234', $body);
        self::assertStringContainsString('GWAREK', $body);
        self::assertStringContainsString('Sosnowiec Glowny', $body);
        self::assertStringContainsString('#ff0000', $body);
    }

    public function testZaznaczonaStacjaJestPogrubiona(): void
    {
        $this->client->request('POST', '/ztrres', [
            'st' => ['Pierwsza', 'Druga'],
            'stb' => ['1' => 'on'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('<b>Druga</b>', (string) $this->client->getResponse()->getContent());
    }

    /**
     * Dokladnie ten przypadek dawal wczesniej 500: array_keys() na stringu.
     *
     * @dataProvider provideZlyKsztaltDanych
     */
    public function testSpreparowaneWejscieNieWywracaStrony(array $payload): void
    {
        $this->client->request('POST', '/ztrres', $payload);

        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideZlyKsztaltDanych(): iterable
    {
        yield 'stb jako string' => [['st' => ['A'], 'stb' => 'nie-tablica']];
        yield 'st jako string' => [['st' => 'nie-tablica']];
        yield 'st z zagniezdzona tablica' => [['st' => [['a']]]];
        yield 'stb z kluczami nieliczbowymi' => [['st' => ['A'], 'stb' => ['abc' => 'on']]];
        yield 'liczby zamiast tekstu' => [['trainNo' => 123, 'st' => ['A']]];
    }

    public function testTemplateTypeSpozaListyWracaDoDomyslnego(): void
    {
        $this->client->request('POST', '/ztrres', [
            'templateType' => '../../../etc/passwd',
            'st' => ['A'],
        ]);

        self::assertResponseIsSuccessful();

        $body = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('passwd', $body);
        self::assertStringContainsString('/pkpic.css', $body);
    }

    public function testKolorSpozaFormatuNieTrafiaDoStyli(): void
    {
        $this->client->request('POST', '/ztrres', [
            'numberColor' => 'red;background:url(//zly.example/x)',
            'st' => ['A'],
        ]);

        self::assertResponseIsSuccessful();

        $body = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('zly.example', $body);
        self::assertStringContainsString('#000000', $body);
    }

    public function testPustyPostWracaDoFormularza(): void
    {
        $this->client->request('POST', '/ztrres', []);

        self::assertResponseRedirects('/ztr');
    }
}
