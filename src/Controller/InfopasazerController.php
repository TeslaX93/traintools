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
     * @Route("/infopasazer/allArrivals/{station}")
     * @param Request $request
     * @return Response
     */
    public function allArrivals(Request $request)
    {

        $situation = 'arrivals';
        $situation = ($situation == 'arrivals');

        $stationId = $request->attributes->get('station');

        if(!$stationId) $stationId = 33605; //Warszawa Centralna
        $html = file_get_contents('https://infopasazer.intercity.pl/?p=station&id='.$stationId); //73312
        $crawler = new Crawler($html);
        $lastUpdate = str_replace('Aktualizacja: ','',$crawler->filter('div.CustomColor-06 p')->text());
        $currentStation = trim($crawler->filter('p.h4')->first()->text());
        $currentStation = str_replace("Rozkład stacyjny dla stacji ","",$currentStation);
        //$lastUpdate = date_create_from_format("Y-m-d H:i",$lastUpdate);
        $arrivalTable = $crawler->filter('table.table.table-delay.mbn tbody')->first()->html();

        $crawler = new Crawler($arrivalTable);
        $trains = $crawler->filter('tr')->each(function($tr,$i) {return $tr->filter('td span')->each(function($td,$i) {return trim($td->html());});});

        $indexNames = ['trainId','trainNo'];
        $trainAA = ['currentStation' => $currentStation, 'lastUpdate' => $lastUpdate];
        //$trainAA = [];
        foreach($trains as $tr) {
            $thisTrain = [];
            foreach($tr as $idx=>$td) {
                if($idx == 0) {
                    $trainDetails = explode("\"",$td);
                    $thisTrain['trainId'] = str_replace("?p=train&amp;id=","",$trainDetails[1]);
                    $trainDetails = str_replace("<br>",";",$td);
                    $trainDetails = trim(strip_tags($trainDetails));
                    $trainDetails = explode(';',$trainDetails);
                    $thisTrain['trainNo'] = $trainDetails[0];
                    if(count($trainDetails)>1) $thisTrain['trainName'] = $trainDetails[1]; else $thisTrain['trainName'] = "";
                }
                if($idx == 1) {
                    $thisTrain['company'] = trim(strip_tags($td));
                }
                if($idx == 2) {
                    $thisTrain['arrivalTime'] = $td;
                }
                if($idx == 3) {
                    $trainDetails = explode(' - ',$td);
                    $thisTrain['from'] = $trainDetails[0];
                    $thisTrain['to'] = $trainDetails[1];
                    $thisTrain['via'] = [];
                        if($thisTrain['to']!=$currentStation) {
                            $html = file_get_contents('https://infopasazer.intercity.pl/?p=train&id='.$thisTrain['trainId']);
                            $crawler2 = new Crawler($html);
                            $delayTable = $crawler2->filter('table.table-delay tbody')->first()->html();
                            $crawler2 = new Crawler($delayTable);
                            $stationsTable = $crawler2->filter('tr')->each(function($tr,$i) {return $tr->filter('td span')->each(function($td,$i) {return trim($td->text());});});
                            foreach($stationsTable as $stations) {
                                array_push($thisTrain['via'],trim($stations[3]));
                            }
                            $whereStation = array_search($currentStation,$thisTrain['via']);
                            $howManyVia = count($thisTrain['via']);
                            if($whereStation) {
                                foreach($thisTrain['via'] as $idx3=>$tvia) {
                                    if($idx3<=$whereStation) unset($thisTrain['via'][$idx3]);
                                    if($idx3==$howManyVia-1) unset($thisTrain['via'][$idx3]);
                                }
                              $thisTrain['via'] = array_values($thisTrain['via']);  //does not work and don't know why :(
                            }
                        }
                }
                if($idx == 4) {
                    $thisTrain['arrivalTime'] .=' '.$td;
                }
                if($idx == 5) {
                    $thisTrain['delayTime'] = str_replace(" min","",$td);
                    $realDate = date_create_from_format('Y-m-d H:i',$thisTrain['arrivalTime']);
                    $realDate->modify("+ ".$thisTrain['delayTime']." minutes");
                    $thisTrain['arrivalRealTime'] = $realDate->format("Y-m-d H:i");
                }
            }
            array_push($trainAA,$thisTrain);
        }
        dd($trainAA);
        $response = new Response();

        $response->setContent(json_encode($trainAA));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Route("/infopasazer/allDepartures/{station}")
     * @param Request $request
     * @return Response
     */
    public function allDepartures(Request $request)
    {
        $response = new Response();
        $json = ['data' => 123];
        $response->setContent(json_encode($json));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

}
