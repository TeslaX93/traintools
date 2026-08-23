<?php

namespace App\Helper;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Jeden przystanek na trasie pociągu, ze strony szczegółów w serwisie bilkom.pl.
 *
 * Tak samo jak przy tablicy: czytamy elementy <div> po położeniu, a wszystkie
 * numery siedzą w stałych poniżej.
 *
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │ GDY BILKOM ZMIENI UKŁAD STRONY, POPRAWIA SIĘ WYŁĄCZNIE TĘ TABELĘ.    │
 * └──────────────────────────────────────────────────────────────────────┘
 */
final class BilkomTripRow
{
    /** Komórka przyjazdu: zawiera ".time[data-difference]" z opóźnieniem. */
    private const ARRIVAL_CELL = 3;

    /** Planowy przyjazd, znacznik czasu w milisekundach. */
    private const ARRIVAL_MS = 4;

    /** Komórka odjazdu: zawiera ".time[data-difference]" z opóźnieniem. */
    private const DEPARTURE_CELL = 8;

    /**
     * Położenie godziny odjazdu zależy od długości wiersza: przystanki bez
     * peronu mają o jedną komórkę mniej.
     */
    private const DEPARTURE_MS_SHORT_ROW = 9;

    private const DEPARTURE_MS_LONG_ROW = 10;

    private const SHORT_ROW_LENGTH = 13;

    /**
     * @param list<string> $cells zawartość kolejnych <div> w wierszu
     */
    public function __construct(private readonly array $cells)
    {
    }

    public function arrivalAt(): int|float|null
    {
        $raw = $this->cell(self::ARRIVAL_MS);

        return empty($raw) ? null : (int) $raw / 1000;
    }

    public function departureAt(): int|float|null
    {
        $index = count($this->cells) === self::SHORT_ROW_LENGTH
            ? self::DEPARTURE_MS_SHORT_ROW
            : self::DEPARTURE_MS_LONG_ROW;

        $raw = $this->cell($index);

        return empty($raw) ? null : (int) $raw / 1000;
    }

    public function arrivalDelay(): string
    {
        return trim((string) $this->attribute(self::ARRIVAL_CELL), "+' ");
    }

    public function departureDelay(): string
    {
        return trim((string) $this->attribute(self::DEPARTURE_CELL), "+' ");
    }

    /**
     * Postój w pełnych minutach, nigdy mniej niż 1.
     *
     * Uwaga na zaszłość: przy braku godziny przyjazdu lub odjazdu (stacja
     * początkowa albo końcowa) pierwotny kod ustawiał null, po czym warunek
     * "== 0" inkrementował go do jedynki - w PHP null++ daje 1. Pole nigdy
     * więc nie było puste i zachowujemy to, żeby nie zmieniać kontraktu API.
     */
    public function stopMinutes(): int
    {
        $arrival = $this->arrivalAt();
        $departure = $this->departureAt();

        if (empty($arrival) || empty($departure)) {
            return 1;
        }

        $minutes = intdiv((int) $departure - (int) $arrival, 60);

        return $minutes === 0 ? 1 : $minutes;
    }

    /**
     * Nazwa przystanku siedzi zawsze w ostatniej komórce wiersza.
     */
    public function stationName(): string
    {
        $cells = $this->cells;

        return strip_tags((string) array_pop($cells));
    }

    public function isOnDemand(): bool
    {
        return str_contains($this->stationName(), '(NŻ)');
    }

    private function cell(int $index): string
    {
        return $this->cells[$index] ?? '';
    }

    private function attribute(int $index): ?string
    {
        $crawler = (new Crawler($this->cell($index)))->filter('.time');

        return $crawler->count() > 0 ? $crawler->attr('data-difference') : null;
    }
}
