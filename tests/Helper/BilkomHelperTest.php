<?php

namespace App\Tests\Helper;

use App\Helper\BilkomHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Parser Bilkomu czyta elementy strony po ich położeniu, więc jest z natury
 * kruchy. Te testy pilnują umowy: jakie klucze i jakie typy wychodzą z parsera,
 * skoro to właśnie one są publicznym kontraktem API (public/openapi.yaml).
 *
 * Dane wejściowe są syntetyczne - odtwarzają układ strony, na którym opiera się
 * BilkomBoardRow i BilkomTripRow.
 */
class BilkomHelperTest extends TestCase
{
    /**
     * @return list<string> wiersz tablicy: 14 komorek <div>
     */
    private function boardRow(string $delay = "+12'", bool $withPlatform = true): array
    {
        $cells = array_fill(0, 14, '');
        $cells[0] = '<span class="time" data-difference="' . $delay . '"></span>'
            . '<a href="/pociag/szczegoly?tc=IC&nr=3100">szczegóły</a>';
        $cells[4] = 'IC 3100';
        $cells[8] = '1755900000000';
        $cells[12] = 'Warszawa Wschodnia';
        $cells[13] = 'III/2';

        if (!$withPlatform) {
            // Bilkom pomija te komorke, gdy peron nie jest znany
            unset($cells[11]);
        }

        return $cells;
    }

    public function testZwracaKompletKluczyKontraktuApi(): void
    {
        $train = BilkomHelper::basicTrainAnalysis($this->boardRow());

        self::assertSame(
            ['extraLink', 'trainCode', 'timestamp', 'track', 'platform', 'arrivalStation', 'delay', 'calculatedTime'],
            array_keys($train),
            'Klucze i ich kolejnosc to publiczny kontrakt API'
        );
    }

    public function testWyciagaDanePodstawowe(): void
    {
        $train = BilkomHelper::basicTrainAnalysis($this->boardRow());

        self::assertSame('IC 3100', $train['trainCode']);
        self::assertSame('Warszawa Wschodnia', $train['arrivalStation']);
        self::assertSame('/pociag/szczegoly?tc=IC&nr=3100', $train['extraLink']);
        self::assertEquals(1755900000, $train['timestamp']);
    }

    public function testRozdzielaPeronOdToru(): void
    {
        $train = BilkomHelper::basicTrainAnalysis($this->boardRow());

        self::assertSame('III', $train['platform']);
        self::assertSame('2', $train['track']);
    }

    public function testBrakPeronuDajePusteCiagi(): void
    {
        $train = BilkomHelper::basicTrainAnalysis($this->boardRow('+0\'', false));

        self::assertSame('', $train['platform']);
        self::assertSame('', $train['track']);
    }

    /**
     * Opoznienie jest w minutach, a znacznik czasu w sekundach - bez mnozenia
     * przez 60 godzina przesuwala sie o kilkanascie sekund zamiast minut.
     */
    public function testOpoznienieDoliczaneJestWMinutach(): void
    {
        $train = BilkomHelper::basicTrainAnalysis($this->boardRow("+12'"));

        self::assertSame(12, $train['delay']);
        self::assertEquals(1755900000 + 12 * 60, $train['calculatedTime']);
    }

    public function testBrakOpoznieniaDajeZero(): void
    {
        $cells = $this->boardRow();
        $cells[0] = '<a href="/x">szczegóły</a>'; // brak elementu .time

        $train = BilkomHelper::basicTrainAnalysis($cells);

        self::assertSame(0, $train['delay']);
        self::assertEquals(1755900000, $train['calculatedTime']);
    }

    /**
     * Bilkom ma trzy sposoby na powiedzenie "bez opoznienia": brak elementu,
     * "+0'" oraz "0". Wszystkie maja dawac liczbe zero, nie ciag znakow.
     *
     * @dataProvider provideBrakOpoznienia
     */
    public function testKazdyZapisBrakuOpoznieniaDajeLiczbeZero(string $naglowek): void
    {
        $cells = $this->boardRow();
        $cells[0] = $naglowek;

        $train = BilkomHelper::basicTrainAnalysis($cells);

        self::assertSame(0, $train['delay']);
        self::assertIsInt($train['delay']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideBrakOpoznienia(): iterable
    {
        yield 'brak elementu .time' => ['<a href="/x">szczegóły</a>'];
        yield 'zapis +0 z apostrofem' => ["<span class=\"time\" data-difference=\"+0'\"></span>"];
        yield 'samo zero' => ['<span class="time" data-difference="0"></span>'];
    }

    public function testOpoznienieUjemneDlaPociaguPrzedCzasem(): void
    {
        $cells = $this->boardRow();
        $cells[0] = "<span class=\"time\" data-difference=\"-4'\"></span>";

        $train = BilkomHelper::basicTrainAnalysis($cells);

        self::assertSame(-4, $train['delay']);
        self::assertEquals(1755900000 - 4 * 60, $train['calculatedTime']);
    }

    public function testBrakLinkuDoSzczegolowNieWywracaParsera(): void
    {
        $cells = $this->boardRow();
        $cells[0] = '<span class="time" data-difference="+0\'"></span>';

        $train = BilkomHelper::basicTrainAnalysis($cells);

        self::assertNull($train['extraLink']);
    }

    /**
     * @return string strona szczegolow z trasa pociagu
     */
    private function tripHtml(): string
    {
        $stacja = static function (string $nazwa, ?string $przyjazdMs, ?string $odjazdMs): string {
            // Uwaga na przesuniecie: filter('div') w Symfony dziala jak
            // descendant-or-self, wiec sam element .trip trafia na indeks 0,
            // a kolejne <div> zaczynaja sie od 1 - komorka nr N to wewnetrzny
            // div nr N-1.
            $inner = array_fill(0, 13, '');
            $inner[2] = "<span class=\"time\" data-difference=\"+3'\"></span>";  // komorka 3
            $inner[3] = $przyjazdMs ?? '';                                    // komorka 4
            $inner[7] = "<span class=\"time\" data-difference=\"+5'\"></span>";  // komorka 8
            $inner[9] = $odjazdMs ?? '';                                      // komorka 10
            $inner[12] = $nazwa;                                              // komorka 13

            return '<div class="trip">' . implode('', array_map(
                static fn (string $c): string => '<div>' . $c . '</div>',
                $inner
            )) . '</div>';
        };

        return '<div>'
            . $stacja('Wrocław Główny', null, '1755900000000')
            . $stacja('Opole Główne', '1755903600000', '1755903720000')
            . $stacja('Katowice (NŻ)', '1755907200000', null)
            . '</div>';
    }

    public function testTrasaMaKompletKluczy(): void
    {
        $via = BilkomHelper::getViaStations(new Crawler($this->tripHtml()), 'Opole Główne');

        self::assertCount(2, $via, 'Stacja koncowa nie jest posrednia');
        self::assertSame(
            ['arrival', 'departure', 'delayonarrival', 'delayondeparture', 'stop', 'station', 'ondemand', 'thisStation', 'beforeThis'],
            array_keys($via[0])
        );
    }

    public function testZnajdujeBiezacaStacjeIStronyTrasy(): void
    {
        $via = BilkomHelper::getViaStations(new Crawler($this->tripHtml()), 'Opole Główne');

        self::assertTrue($via[0]['beforeThis'], 'Wrocław jest przed Opolem');
        self::assertFalse($via[0]['thisStation']);

        self::assertTrue($via[1]['thisStation'], 'Opole to stacja zapytania');
        self::assertFalse($via[1]['beforeThis']);
    }

    /**
     * Stacja koncowa jest zwracana osobno jako arrivalStation i nie ma powodu,
     * zeby dublowala sie na liscie stacji posrednich.
     */
    public function testStacjaKoncowaNieJestStacjaPosrednia(): void
    {
        $via = BilkomHelper::getViaStations(new Crawler($this->tripHtml()), 'Opole Glowne');

        $nazwy = array_column($via, 'station');

        self::assertNotContains('Katowice (NŻ)', $nazwy, 'Katowice to stacja koncowa tego pociagu');
        self::assertSame(['Wrocław Główny', 'Opole Główne'], $nazwy);
    }

    public function testRozpoznajeStacjeNaZadanie(): void
    {
        $via = BilkomHelper::getViaStations(new Crawler($this->tripHtml()), 'Opole Główne');

        self::assertFalse($via[0]['ondemand']);
        self::assertFalse($via[1]['ondemand']);
    }

    public function testLiczyPostojIObcinaOpoznienia(): void
    {
        $via = BilkomHelper::getViaStations(new Crawler($this->tripHtml()), 'Opole Główne');

        self::assertSame(2, $via[1]['stop'], '120 sekund postoju to 2 minuty');
        self::assertSame('3', $via[1]['delayonarrival']);
        self::assertSame('5', $via[1]['delayondeparture']);
    }

    /**
     * Zaszlosc: na stacji poczatkowej i koncowej postoju nie da sie wyliczyc,
     * a mimo to API zawsze zwracalo tam 1. Zachowujemy zgodnosc.
     */
    public function testPostojNigdyNieJestPusty(): void
    {
        $via = BilkomHelper::getViaStations(new Crawler($this->tripHtml()), 'Opole Główne');

        self::assertSame(1, $via[0]['stop'], 'stacja poczatkowa');
    }

    public function testStacjaPoczatkowaNieMaPrzyjazduAKoncowaOdjazdu(): void
    {
        $via = BilkomHelper::getViaStations(new Crawler($this->tripHtml()), 'Opole Główne');

        self::assertNull($via[0]['arrival'], 'stacja poczatkowa nie ma przyjazdu');
        self::assertEquals(1755900000, $via[0]['departure']);
    }
}
