<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DomCrawler\Crawler;

class InfopasazerController extends AbstractController
{
    /**
     * @Route("/infopasazer", name="infopasazer")
     */
    public function index()
    {

        $stationsFile = "https://gist.githubusercontent.com/TeslaX93/96fd7c44b630771563bdfc3af3d960fc/raw/049bc9c98cb524d81ee5b4c62704cc3d89d261ec/InfopasazerStationCodes.txt";
        $stationsFile = @file_get_contents($stationsFile);

        $stationsFile = explode("\n", trim($stationsFile));
        $stationsList = [];
        foreach ($stationsFile as $sfl) {
            $line = explode(",", $sfl);
            $stationsList[$line[0]] = $line[1];
        }


        return $this->render('infopasazer/index.html.twig', [
            'stationsList' => $stationsList
        ]);
    }

    /**
     * @Route("/infopasazer/trains/{type}/{station}")
     * @param Request $request
     * @return Response
     */
    public function getTrains(Request $request)
    {
        // check {type} parameter
        $response = new Response();
        if (!in_array($request->attributes->get('type'), ['arrivals', 'departures', 'nearestdep', 'nearestarr', 'narrdelay', 'ndepdelay'])) {
            $response->setContent(json_encode(['error' => 'Zły parametr {type}']));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        $arrivals = $request->attributes->get('type');

        switch ($arrivals) {
            case 'arrivals':
                {
                    $arrivals = 1;
                    break;
            }
            case 'departures':
                {
                    $arrivals = 0;
                    break;
            }
            case 'nearestarr':
                {
                    $arrivals = 3;
                    break;
            }
            case 'nearestdep':
                {
                    $arrivals = 2;
                    break;
            }
            case 'narrdelay':
                {
                    $arrivals = 5;
                    break;
            }
            case 'ndepdelay':
                {
                    $arrivals = 4;
                    break;
            }
            default:
                {
                    $arrivals = 0;
            }
        }


        //check {station} parameter
        $stationId = $request->attributes->get('station');

        if (!$stationId) {
            $stationId = 33605; //Warszawa Centralna
        }
        $html = @file_get_contents('https://infopasazer.intercity.pl/?p=station&id=' . $stationId); //73312
        if ($html === false) {
            $response->setContent(json_encode(['error' => 'Brak połączenia z serwerem infopasażera']));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }
        $crawler = new Crawler($html);

        //check if there are any trains
        if ($crawler->filter('div.error')->count() != 0) {
            $response->setContent(json_encode(['error' => trim($crawler->filter('div.error')->first()->text())]));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
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

        //loop through columns and cells
        foreach ($trains as $tr) {
            $thisTrain = [];
            foreach ($tr as $idx => $td) {
                if ($idx == 0) { //train number and name
                    $trainDetails = explode("\"", $td);
                    $thisTrain['trainId'] = str_replace("?p=train&amp;id=", "", $trainDetails[1]);
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
                    $thisTrain['to'] = $trainDetails[1];
                    $thisTrain['via'] = [];

                    //via
                    $html = file_get_contents('https://infopasazer.intercity.pl/?p=train&id=' . $thisTrain['trainId']);
                    $crawler2 = new Crawler($html);
                    $delayTable = $crawler2->filter('table.table-delay tbody')->first()->html();
                    $crawler2 = new Crawler($delayTable);
                    $stationsTable = $crawler2->filter('tr')->each(function ($tr, $i) {
                        return $tr->filter('td span')->each(function ($td, $i) {
                            return trim($td->text());
                        });
                    });
                    foreach ($stationsTable as $stations) {
                        array_push($thisTrain['via'], trim($stations[3]));
                    }
                    $whereStation = array_search($currentStation, $thisTrain['via']);
                    $howManyVia = count($thisTrain['via']);
                    foreach ($thisTrain['via'] as $idx3 => $tvia) {
                        if (($thisTrain['from'] == $tvia) || ($idx3 == $howManyVia - 1)) {
                            unset($thisTrain['via'][$idx3]);
                        }

                        if ($arrivals % 2 != 0) {
                            if ($idx3 >= $whereStation) {
                                unset($thisTrain['via'][$idx3]); //arrivals
                            }
                        } else {
                            if ($idx3 <= $whereStation) {
                                unset($thisTrain['via'][$idx3]); //departures
                            }
                        }
                    }
                    $thisTrain['via'] = array_values($thisTrain['via']);
                }
                if ($idx == 4) { //arrival, departure time
                    $thisTrain['scheduleTime'] .= ' ' . $td;
                }
                if ($idx == 5) { //delay
                    $thisTrain['delayTime'] = intval(str_replace(" min", "", $td));
                    $realDate = date_create_from_format('Y-m-d H:i', $thisTrain['scheduleTime']);
                    $realDate->modify("+ " . $thisTrain['delayTime'] . " minutes");
                    $thisTrain['realTime'] = $realDate->format("Y-m-d H:i");
                }
            }
            array_push($trainAA, $thisTrain);
            if ($arrivals == 2 || $arrivals == 3) {
                break;
            } //nearest departure/arrival
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


        $json = $trainsHeader + ['trains' => array_values($trainAA)]; //remove pseudo-array-keys

        $response = new Response();
        $response->setContent(json_encode($json));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * @Route("infopasazer/list/", name="stationslist")
     * @param Request $request
     * @return string|Response
     */
    public function stationsList(Request $request)
    {
        $response = '';
        $stationsFile = "https://gist.githubusercontent.com/TeslaX93/96fd7c44b630771563bdfc3af3d960fc/raw/049bc9c98cb524d81ee5b4c62704cc3d89d261ec/InfopasazerStationCodes.txt";
        $stationsFile = @file_get_contents($stationsFile);

        if ($stationsFile === false) {
            $response->setContent(json_encode(['error' => 'Brak połączenia z serwerem infopasażera']));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }
        $stationsFile = explode("\n", trim($stationsFile));
        $stationsList = [];
        foreach ($stationsFile as $sfl) {
            $line = explode(",", $sfl);
            $stationsList[$line[0]] = $line[1];
        }

        return $this->render('infopasazer/list.html.twig', ['stations' => $stationsList]);
    }

    /**
     * @param Request $request
     * @Route("infopasazer/examples/departureDisplay", name="exampleDepartureDisplayRedirector")
     */
    public function departureDisplayRedirector(Request $request)
    {

        $stationId = $request->request->all()['stationId'];
        return $this->redirectToRoute('exampleDepartureDisplay', ['station' => $stationId]);
    }

    /**
     * @Route("infopasazer/examples/departureDisplay/{station}", name="exampleDepartureDisplay")
     * @param Request $request
     * @return Response
     */
    public function departureDisplay(Request $request)
    {
        $error = "";
        $stationId = $request->attributes->get('station');
        $getUrl = "http://traintools.bmalarz.pl/infopasazer/trains/departures/" . $stationId;
        $json = file_get_contents($getUrl);
        $trains = json_decode($json, true);
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
