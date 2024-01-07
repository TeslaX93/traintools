<?php

namespace App\Helper;

use Symfony\Component\DomCrawler\Crawler;

class BilkomHelper
{
    public static function getColumns(): array
    {
        return [
            0 => 'all',
            3 => 'trainCode',
            5 => 'dateDetails',
            7 => 'timestamp1000',
            8 => 'timeString',
            9 => 'dateString',
            11 => 'arrivalStation',
            12 => 'trackPlatform',
            89 => 'beforeCurrent',
            90 => 'extraLink',
            91 => 'amenities',
            92 => 'via',
            93 => 'company',
            94 => 'currentStation',
            95 => 'calculatedTime',
            96 => 'timestamp',
            97 => 'track',
            98 => 'platform',
            99 => 'delay'
        ];
    }
    public static function isOnDemand(string $name): bool
    {
        if (str_contains($name, "(NŻ)")) {
            return true;
        }
        return false;
    }

    public static function getAmenities(Crawler $htmlStructure): array
    {
        $amenities = $htmlStructure->filter('.services ul')->each(function ($el, $i) {
            return $el->filter('li')->each(function ($li, $i) {
                return trim($li->attr('title'));
            });
        });
        return str_replace("<hr/>", ": ", $amenities[0]);
    }

    public static function getViaStations(Crawler $htmlStructure, ?string $currentStation): array
    {
        $viatable = $htmlStructure->filter('.trip')->each(function ($el, $i) {
            return $el->filter('div')->each(function ($li, $i) {
                return trim($li->html());
            });
        });
        $via = [];

        $beforeThisStation = true;
        $thisStation = false;

        foreach ($viatable as $viaelement) {
            $viastation = [];

            // 4 & 9 if 13, 4 & 10 if 14
            $viastation['arrival'] = empty($viaelement[4]) ? null : ((int)$viaelement[4] / 1000);
            if (count($viaelement) == 13) {
                $viastation['departure'] = empty($viaelement[9]) ? null : ((int)$viaelement[9] / 1000);
            } else {
                $viastation['departure'] = empty($viaelement[10]) ? null : ((int)$viaelement[10] / 1000);
            }


            $detailsCrawler = new Crawler($viaelement[3]);
            $viastation['delayonarrival'] = $detailsCrawler->filter('.time')->attr('data-difference');
            $detailsCrawler = new Crawler($viaelement[8]);
            $viastation['delayondeparture'] = $detailsCrawler->filter('.time')->attr('data-difference');

            $viastation['delayonarrival'] = trim($viastation['delayonarrival'], "+' ");
            $viastation['delayondeparture'] = trim($viastation['delayondeparture'], "+' ");

            if ((empty($viastation['arrival']) || (empty($viastation['departure'])))) {
                $viastation['stop'] = null;
            } else {
                $viastation['stop'] = intdiv($viastation['departure'] - $viastation['arrival'], 60);
            }
            if (($viastation['stop']) == 0) {
                $viastation['stop']++;
            }
            $viastation['station'] = strip_tags(array_pop($viaelement));
            $viastation['ondemand'] = self::isOnDemand($viastation['station']);
            $viastation['thisStation'] = false;

            if($viastation['station'] == $currentStation) {
                $beforeThisStation = false;
                $viastation['thisStation'] = true;

            }
            $viastation['beforeThis'] = $beforeThisStation;

            $via[] = $viastation;
        }
        return $via;
    }

    public static function getCurrentStationPosition(string $currentStation, array $stations): int
    {
        foreach($stations as $s)
        {
            //if($s['name'])
        }
    }

    public static function basicTrainAnalysis(array $train, array $columns): array
    {
        $trainDetails = [];
        $delay = (new Crawler($train[0]))->filter('.time')->attr('data-difference');
        $extraLink = (new Crawler($train[0]))->filter('a')->first()->attr('href');
        $trainDetails[$columns[90]] = $extraLink;

        $trainDetails[$columns[3]] = $train[3];
        $trainDetails[$columns[96]] = (int)$train[7] / 1000;
        if (isset($train[11])) {
            $trainDetails[$columns[97]] = explode("/", $train[12])[1];
            $trainDetails[$columns[98]] = explode("/", $train[12])[0]; //RomanToNumber::rtn(explode("/",$t[11])[0]);
        } else {
            $trainDetails[$columns[97]] = '';
            $trainDetails[$columns[98]] = '';
        }

        $trainDetails[$columns[11]] = $train[11];
        if ($delay) {
            $trainDetails[$columns[99]] = trim($delay, "+' ");
        } else {
            $trainDetails[$columns[99]] = 0;
        }

        $trainDetails[$columns[95]] = $trainDetails[$columns[96]] + $trainDetails[$columns[99]];

        return $trainDetails;
    }

    public static function generateBilkomUrl(string $stationId, string $customDate, string $arrivalString)
    {
        return "https://bilkom.pl/stacje/tablica?stacja=" . $stationId . "&data=" . $customDate . "&time=&przyjazd=" . $arrivalString;

    }
}