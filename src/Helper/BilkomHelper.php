<?php

namespace App\Helper;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Zamiana stron bilkom.pl na strukturę zwracaną przez API.
 *
 * Nazwy kluczy w zwracanych tablicach to publiczny kontrakt API - opisuje go
 * public/openapi.yaml. Wiedza o tym, gdzie na stronie leżą poszczególne dane,
 * siedzi w BilkomBoardRow i BilkomTripRow.
 */
class BilkomHelper
{
    public static function isOnDemand(string $name): bool
    {
        return str_contains($name, '(NŻ)');
    }

    /**
     * Udogodnienia w pociągu ze strony szczegółów.
     *
     * @return list<string>
     */
    public static function getAmenities(Crawler $htmlStructure): array
    {
        $amenities = $htmlStructure->filter('.services ul')->each(function ($el) {
            return $el->filter('li')->each(function ($li) {
                return trim((string) $li->attr('title'));
            });
        });

        if (!isset($amenities[0])) {
            return [];
        }

        return str_replace('<hr/>', ': ', $amenities[0]);
    }

    /**
     * Stacje pośrednie na trasie pociągu.
     *
     * Stacja końcowa nie jest stacją pośrednią - trafia osobno do pola
     * arrivalStation, więc nie ma powodu, żeby dublowała się na tej liście.
     * Stacja początkowa pozostaje, rozpoznasz ją po pustym polu "arrival".
     *
     * @return list<array<string, mixed>>
     */
    public static function getViaStations(Crawler $htmlStructure, ?string $currentStation): array
    {
        $rows = $htmlStructure->filter('.trip')->each(function ($el) {
            return $el->filter('div')->each(function ($div) {
                return trim($div->html());
            });
        });

        $via = [];
        $beforeThisStation = true;
        $lastIndex = count($rows) - 1;

        foreach ($rows as $index => $cells) {
            $row = new BilkomTripRow($cells);
            $station = $row->stationName();

            $isThisStation = $station === $currentStation;
            if ($isThisStation) {
                $beforeThisStation = false;
            }

            if ($index === $lastIndex) {
                continue; // stacja koncowa, nie posrednia
            }

            $via[] = [
                'arrival' => $row->arrivalAt(),
                'departure' => $row->departureAt(),
                'delayonarrival' => $row->arrivalDelay(),
                'delayondeparture' => $row->departureDelay(),
                'stop' => $row->stopMinutes(),
                'station' => $station,
                'ondemand' => $row->isOnDemand(),
                'thisStation' => $isThisStation,
                'beforeThis' => $beforeThisStation,
            ];
        }

        return $via;
    }

    /**
     * Podstawowe dane pociągu z wiersza tablicy.
     *
     * @param list<string> $train zawartość kolejnych <div> w wierszu
     *
     * @return array<string, mixed>
     */
    public static function basicTrainAnalysis(array $train): array
    {
        $row = new BilkomBoardRow($train);

        $scheduledAt = $row->scheduledAt();
        $delay = $row->delay();

        return [
            'extraLink' => $row->detailsLink(),
            'trainCode' => $row->trainCode(),
            'timestamp' => $scheduledAt,
            'track' => $row->track(),
            'platform' => $row->platform(),
            'arrivalStation' => $row->destination(),
            'delay' => $delay,
            // opoznienie jest w minutach, znacznik czasu w sekundach
            'calculatedTime' => $scheduledAt + ($delay * 60),
        ];
    }

    public static function generateBilkomUrl(string $stationId, string $customDate, string $arrivalString): string
    {
        return 'https://bilkom.pl/stacje/tablica?stacja=' . $stationId
            . '&data=' . $customDate
            . '&time=&przyjazd=' . $arrivalString;
    }
}
