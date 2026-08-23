<?php

namespace App\Helper;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Jeden wiersz tablicy odjazdów/przyjazdów ze strony bilkom.pl.
 *
 * Bilkom nie ma API, więc czytamy kolejne elementy <div> wewnątrz ".el" po ich
 * położeniu. Cała wiedza o tym, co gdzie leży, jest w stałych poniżej — reszta
 * kodu posługuje się już nazwami.
 *
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │ GDY BILKOM ZMIENI UKŁAD STRONY, POPRAWIA SIĘ WYŁĄCZNIE TĘ TABELĘ.    │
 * └──────────────────────────────────────────────────────────────────────┘
 */
final class BilkomBoardRow
{
    /** Nagłówek wiersza: zawiera ".time[data-difference]" oraz <a> do szczegółów. */
    private const HEADER = 0;

    /** Kod przewoźnika i numer pociągu, np. "IC 3100". */
    private const TRAIN_CODE = 4;

    /** Planowa godzina jako znacznik czasu w milisekundach. */
    private const TIMESTAMP_MS = 8;

    /** Komórka obecna tylko wtedy, gdy Bilkom zna peron. Sama w sobie nieużywana. */
    private const PLATFORM_PRESENT = 11;

    /** Stacja docelowa pociągu. */
    private const DESTINATION = 12;

    /** Peron i tor razem, w zapisie "peron/tor", np. "III/2". */
    private const PLATFORM_AND_TRACK = 13;

    /**
     * @param list<string> $cells zawartość kolejnych <div> w wierszu
     */
    public function __construct(private readonly array $cells)
    {
    }

    public function trainCode(): string
    {
        return $this->cell(self::TRAIN_CODE);
    }

    public function destination(): string
    {
        return $this->cell(self::DESTINATION);
    }

    /**
     * Planowa godzina jako uniksowy znacznik czasu w sekundach.
     */
    public function scheduledAt(): int|float
    {
        return (int) $this->cell(self::TIMESTAMP_MS) / 1000;
    }

    /**
     * Opóźnienie w pełnych minutach, ujemne gdy pociąg jedzie przed czasem.
     *
     * Bilkom podaje je jako "+25'", a przy braku opóźnienia albo "+0'", albo
     * w ogóle pomija element. Zwracamy zawsze liczbę - wcześniej wychodziły
     * stąd trzy różne reprezentacje tego samego: 0, "0" oraz "25".
     */
    public function delay(): int
    {
        $delay = $this->attribute(self::HEADER, '.time', 'data-difference');

        return $delay === null ? 0 : (int) trim($delay, "+' ");
    }

    /**
     * Względny adres strony ze szczegółami pociągu.
     */
    public function detailsLink(): ?string
    {
        $header = new Crawler($this->cell(self::HEADER));
        $link = $header->filter('a');

        return $link->count() > 0 ? $link->first()->attr('href') : null;
    }

    public function hasPlatform(): bool
    {
        return isset($this->cells[self::PLATFORM_PRESENT]);
    }

    public function platform(): string
    {
        return $this->platformAndTrack()[0] ?? '';
    }

    public function track(): string
    {
        return $this->platformAndTrack()[1] ?? '';
    }

    /**
     * @return list<string>
     */
    private function platformAndTrack(): array
    {
        if (!$this->hasPlatform()) {
            return [];
        }

        return explode('/', $this->cell(self::PLATFORM_AND_TRACK));
    }

    private function cell(int $index): string
    {
        return $this->cells[$index] ?? '';
    }

    private function attribute(int $index, string $selector, string $attribute): ?string
    {
        $crawler = (new Crawler($this->cell($index)))->filter($selector);

        return $crawler->count() > 0 ? $crawler->attr($attribute) : null;
    }
}
