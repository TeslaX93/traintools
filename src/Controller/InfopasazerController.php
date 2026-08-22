<?php

namespace App\Controller;

use App\Service\HtmlFetcher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DomCrawler\Crawler;

class InfopasazerController extends AbstractController
{
    private const STATION_BOARD_URL = 'https://infopasazer.intercity.pl/?p=station&id=';

    private const TRAIN_URL = 'https://infopasazer.intercity.pl/?p=train&id=';

    /** Tablica stacyjna z opóźnieniami - odświeżamy często. */
    private const BOARD_TTL = 30;

    /** Ze strony pociągu bierzemy tylko listę stacji, a ta się nie zmienia. */
    private const TRAIN_TTL = 900;

    /** Kody stacji zmieniają się kilka razy w roku. */
    private const STATION_CODES_TTL = 3600;

    private const STATION_CODES_URL = 'https://gist.githubusercontent.com/TeslaX93/96fd7c44b630771563bdfc3af3d960fc/raw/049bc9c98cb524d81ee5b4c62704cc3d89d261ec/InfopasazerStationCodes.txt';

    private const FETCH_ERROR = 'Brak połączenia z serwerem infopasażera';

    public function __construct(private readonly HtmlFetcher $fetcher)
    {
    }

    /**
     * Pobiera i parsuje listę kodów stacji.
     *
     * @return array<string, string>|null null, gdy nie udało się pobrać pliku
     */
    private function fetchStationCodes(): ?array
    {
        $stationsFile = $this->fetcher->fetch(self::STATION_CODES_URL, self::STATION_CODES_TTL);

        if ($stationsFile === null) {
            return null;
        }

        $stationsList = [];
        foreach (explode("\n", trim($stationsFile)) as $sfl) {
            $line = explode(",", $sfl);
            if (count($line) < 2) {
                continue;
            }
            $stationsList[trim($line[0])] = trim($line[1]);
        }

        return $stationsList;
    }

    #[Route('/infopasazer', name: 'infopasazer')]
    public function index(): Response
    {
        $stationsList = $this->fetchStationCodes();

        return $this->render('infopasazer/index.html.twig', [
            'stationsList' => $stationsList ?? [],
            'error' => $stationsList === null ? self::FETCH_ERROR : null,
        ]);
    }

    #[Route('/infopasazer/trains/{type}/{station}')]
    public function getTrains(Request $request): Response
    {
        return $this->jsonResponse($this->fetchTrains(
            (string) $request->attributes->get('type'),
            $request->attributes->get('station')
        ));
    }

    /**
     * Buduje dane tablicy przyjazdów/odjazdów dla podanej stacji.
     *
     * @return array<string, mixed> dane tablicy albo ['error' => komunikat]
     */
    private function fetchTrains(string $type, ?string $stationId): array
    {
        if (!in_array($type, ['arrivals', 'departures', 'nearestdep', 'nearestarr', 'narrdelay', 'ndepdelay'])) {
            return ['error' => 'Zły parametr {type}'];
        }

        $arrivals = match ($type) {
            'arrivals' => 1,
            'nearestdep' => 2,
            'nearestarr' => 3,
            'ndepdelay' => 4,
            'narrdelay' => 5,
            default => 0, // departures
        };

        if (!$stationId) {
            $stationId = '33605'; //Warszawa Centralna
        }

        $html = $this->fetcher->fetch(self::STATION_BOARD_URL . $stationId, self::BOARD_TTL);
        if ($html === null) {
            return ['error' => self::FETCH_ERROR];
        }
        $crawler = new Crawler($html);

        //check if there are any trains
        if ($crawler->filter('div.error')->count() != 0) {
            return ['error' => trim($crawler->filter('div.error')->first()->text())];
        }

        //get current station and update time
        $lastUpdate = str_replace('Aktualizacja: ', '', $crawler->filter('div.CustomColor-06 p')->text());
        $currentStation = trim($crawler->filter('p.h4')->first()->text());
        $currentStation = str_replace("Rozkład stacyjny dla stacji ", "", $currentStation);

        //get first or second table (arrivals or departures)
        if (($arrivals % 2) != 0) {
            $scheduleTable = $crawler->filter('table.table.table-delay.mbn tbody')->first()->html();
        } else {
            $scheduleTable = $crawler->filter('table.table.table-delay.mbn tbody')->last()->html();
        }

        //get table content
        $crawler = new Crawler($scheduleTable);
        $trains = $crawler->filter('tr')->each(function ($tr, $i) {
            return $tr->filter('td span')->each(function ($td, $i) {
                return trim($td->html());
            });
        });

        //prepare final table
        $trainsHeader = ['currentStation' => $currentStation, 'lastUpdate' => $lastUpdate];
        $trainAA = [];

        // PRZEBIEG 1: parsujemy wiersze tablicy. Stacje pośrednie zostawiamy puste -
        // każda wymaga osobnej strony, a te pobieramy niżej, wszystkie naraz.
        foreach ($trains as $tr) {
            $thisTrain = [];
            foreach ($tr as $idx => $td) {
                if ($idx == 0) { //train number and name
                    $trainDetails = explode("\"", $td);
                    $thisTrain['trainId'] = str_replace("?p=train&amp;id=", "", $trainDetails[1] ?? '');
                    $trainDetails = str_replace("<br>", ";", $td);
                    $trainDetails = trim(strip_tags($trainDetails));
                    $trainDetails = explode(';', $trainDetails);
                    $thisTrain['trainNo'] = trim($trainDetails[0]);
                    if (count($trainDetails) > 1) {
                        $thisTrain['trainName'] = $trainDetails[1];
                    } else {
                        $thisTrain['trainName'] = "";
                    }
                }
                if ($idx == 1) { //train company
                    $thisTrain['company'] = trim(strip_tags($td));
                }
                if ($idx == 2) { //departure or arrival date
                    $thisTrain['scheduleTime'] = $td;
                }
                if ($idx == 3) { //from, to, via
                    $trainDetails = explode(' - ', $td);
                    $thisTrain['from'] = $trainDetails[0];
                    $thisTrain['to'] = $trainDetails[1] ?? null;
                    $thisTrain['via'] = []; //uzupełniane w przebiegu 2
                }
                if ($idx == 4) { //arrival, departure time
                    $thisTrain['scheduleTime'] .= ' ' . $td;
                }
                if ($idx == 5) { //delay
                    $thisTrain['delayTime'] = intval(str_replace(" min", "", $td));
                    $realDate = date_create_from_format('Y-m-d H:i', $thisTrain['scheduleTime']);
                    if ($realDate === false) {
                        $thisTrain['realTime'] = $thisTrain['scheduleTime'];
                    } else {
                        $realDate->modify("+ " . $thisTrain['delayTime'] . " minutes");
                        $thisTrain['realTime'] = $realDate->format("Y-m-d H:i");
                    }
                }
            }
            array_push($trainAA, $thisTrain);
            if ($arrivals == 2 || $arrivals == 3) {
                break;
            } //nearest departure/arrival
        }

        // PRZEBIEG 2: jedno równoległe pobranie stron wszystkich pociągów zamiast
        // osobnego żądania w środku pętli (było N+1 zapytań, jedno po drugim).
        $trainUrls = [];
        foreach ($trainAA as $i => $thisTrain) {
            if (array_key_exists('via', $thisTrain) && !empty($thisTrain['trainId'])) {
                $trainUrls[$i] = self::TRAIN_URL . $thisTrain['trainId'];
            }
        }
        $trainsHtml = $trainUrls ? $this->fetcher->fetchMany($trainUrls, self::TRAIN_TTL) : [];

        foreach ($trainUrls as $i => $url) {
            $trainAA[$i]['via'] = $this->extractViaStations(
                $trainsHtml[$i],
                $currentStation,
                (string) $trainAA[$i]['from'],
                $arrivals
            );
        }

        if ($arrivals == 4 || $arrivals == 5) {
            $nearestTime = date_create_from_format("Y-m-d H:i", "2038-01-18 00:00"); //32-bit secure
            foreach ($trainAA as $trainIdx => $train) {
                $trainTime = date_create_from_format("Y-m-d H:i", $train['realTime']);
                if ($trainTime < $nearestTime) {
                    $nearestTime = $trainTime;
                } else {
                    unset($trainAA[$trainIdx]);
                }
            }
        }

        return $trainsHeader + ['trains' => array_values($trainAA)]; //remove pseudo-array-keys
    }

    /**
     * Wyciąga listę stacji pośrednich ze strony pociągu i przycina ją do
     * odcinka za/przed bieżącą stacją.
     *
     * @return list<string>
     */
    private function extractViaStations(?string $html, string $currentStation, string $fromStation, int $arrivals): array
    {
        if ($html === null) {
            return [];
        }

        $crawler = new Crawler($html);
        $delayTable = $crawler->filter('table.table-delay tbody');
        if ($delayTable->count() === 0) {
            return [];
        }

        $stationsTable = (new Crawler($delayTable->first()->html()))->filter('tr')->each(function ($tr, $i) {
            return $tr->filter('td span')->each(function ($td, $i) {
                return trim($td->text());
            });
        });

        $via = [];
        foreach ($stationsTable as $stations) {
            if (isset($stations[3])) {
                $via[] = trim($stations[3]);
            }
        }

        $whereStation = array_search($currentStation, $via);
        $howManyVia = count($via);

        foreach ($via as $idx => $tvia) {
            if (($fromStation == $tvia) || ($idx == $howManyVia - 1)) {
                unset($via[$idx]);
            }

            // Gdy bieżącej stacji nie ma w trasie, przyjazdy nie mają czego pokazać,
            // a odjazdy obcinają tylko pierwszy wiersz - tak działało to od początku.
            if ($arrivals % 2 != 0) {
                if ($whereStation === false || $idx >= $whereStation) {
                    unset($via[$idx]); //arrivals
                }
            } else {
                if ($idx <= ($whereStation === false ? 0 : $whereStation)) {
                    unset($via[$idx]); //departures
                }
            }
        }

        return array_values($via);
    }

    private function jsonResponse(array $data): Response
    {
        $response = new Response(json_encode($data));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    #[Route('infopasazer/list/', name: 'stationslist')]
    public function stationsList(): Response
    {
        $stationsList = $this->fetchStationCodes();

        return $this->render('infopasazer/list.html.twig', [
            'stations' => $stationsList ?? [],
            'error' => $stationsList === null ? self::FETCH_ERROR : null,
        ]);
    }

    #[Route('infopasazer/examples/departureDisplay', name: 'exampleDepartureDisplayRedirector')]
    public function departureDisplayRedirector(Request $request)
    {

        $stationId = $request->request->all()['stationId'];
        return $this->redirectToRoute('exampleDepartureDisplay', ['station' => $stationId]);
    }

    #[Route('infopasazer/examples/departureDisplay/{station}', name: 'exampleDepartureDisplay')]
    public function departureDisplay(Request $request): Response
    {
        $error = "";
        // Wcześniej szło to przez publiczny internet na starą domenę - teraz
        // wołamy tę samą logikę bezpośrednio.
        $trains = $this->fetchTrains('departures', $request->attributes->get('station'));
        if (!empty($trains['error'])) {
            $error = $trains['error'];
        }

        return $this->render(
            'infopasazer/examples/departuresDisplay.html.twig',
            [
                'trainsInfo' => $trains,
                'error' => $error,
            ]
        );
    }
}
