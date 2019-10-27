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

        return $this->render('infopasazer/index.html.twig', [

        ]);
    }

    /**
     * @Route("/infopasazer/trains/{type}/{station}")
     * @param Request $request
     * @return Response
     */
    public function getTrains(Request $request)
    {
        $response = new Response();
        if (!in_array($request->attributes->get('type'), ['arrivals', 'departures'])) {
            $response->setContent(json_encode(['error' => 'Zły parametr {type}']));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }
        $arrivals = ('arrivals' == $request->attributes->get('type'));

        $stationId = $request->attributes->get('station');

        if (!$stationId) $stationId = 33605; //Warszawa Centralna
        $html = @file_get_contents('https://infopasazer.intercity.pl/?p=station&id=' . $stationId); //73312
        if ($html === FALSE) {
            $response->setContent(json_encode(['error' => 'Brak połączenia z serwerem infopasażera']));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }
        $crawler = new Crawler($html);

        if ($crawler->filter('div.error')->count() != 0) {
            $response->setContent(json_encode(['error' => $crawler->filter('div.error')->first()->text()]));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        $lastUpdate = str_replace('Aktualizacja: ', '', $crawler->filter('div.CustomColor-06 p')->text());
        $currentStation = trim($crawler->filter('p.h4')->first()->text());
        $currentStation = str_replace("Rozkład stacyjny dla stacji ", "", $currentStation);
        if ($arrivals) {
            $scheduleTable = $crawler->filter('table.table.table-delay.mbn tbody')->first()->html();
        } else {
            $scheduleTable = $crawler->filter('table.table.table-delay.mbn tbody')->last()->html();
        }


        $crawler = new Crawler($scheduleTable);
        $trains = $crawler->filter('tr')->each(function ($tr, $i) {
            return $tr->filter('td span')->each(function ($td, $i) {
                return trim($td->html());
            });
        });

        $trainsHeader = ['currentStation' => $currentStation, 'lastUpdate' => $lastUpdate];
        $trainAA = [];
        foreach ($trains as $tr) {
            $thisTrain = [];
            foreach ($tr as $idx => $td) {
                if ($idx == 0) {
                    $trainDetails = explode("\"", $td);
                    $thisTrain['trainId'] = str_replace("?p=train&amp;id=", "", $trainDetails[1]);
                    $trainDetails = str_replace("<br>", ";", $td);
                    $trainDetails = trim(strip_tags($trainDetails));
                    $trainDetails = explode(';', $trainDetails);
                    $thisTrain['trainNo'] = $trainDetails[0];
                    if (count($trainDetails) > 1) $thisTrain['trainName'] = $trainDetails[1]; else $thisTrain['trainName'] = "";
                }
                if ($idx == 1) {
                    $thisTrain['company'] = trim(strip_tags($td));
                }
                if ($idx == 2) {
                    $thisTrain['scheduleTime'] = $td;
                }
                if ($idx == 3) {
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
                        if (($thisTrain['from'] == $tvia) || ($idx3 == $howManyVia - 1)) unset($thisTrain['via'][$idx3]);

                        if ($arrivals) {
                            if ($idx3 >= $whereStation) unset($thisTrain['via'][$idx3]); //arrivals
                        } else {
                            if ($idx3 <= $whereStation) unset($thisTrain['via'][$idx3]); //departures

                        }
                    }
                    $thisTrain['via'] = array_values($thisTrain['via']);
                }
                if ($idx == 4) {
                    $thisTrain['scheduleTime'] .= ' ' . $td;
                }
                if ($idx == 5) {
                    $thisTrain['delayTime'] = intval(str_replace(" min", "", $td));
                    $realDate = date_create_from_format('Y-m-d H:i', $thisTrain['scheduleTime']);
                    $realDate->modify("+ " . $thisTrain['delayTime'] . " minutes");
                    $thisTrain['realTime'] = $realDate->format("Y-m-d H:i");
                }
            }
            array_push($trainAA, $thisTrain);
        }

        $json = $trainsHeader + ['trains' => array_values($trainAA)]; //remove pseudo-array-keys
        dd($json);

        $response = new Response();
        $response->setContent(json_encode($json));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


}
