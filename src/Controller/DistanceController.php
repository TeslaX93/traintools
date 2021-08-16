<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Taniko\Dijkstra\Graph;
use App\Entity\Distance;
use App\Form\SimpleDistanceFormType;
use Symfony\Component\HttpFoundation\Request;

class DistanceController extends AbstractController
{
    /**
     * @Route("/distance", name="distance")
     */
    public function index(Request $request)
    {
        $formdata = null;
        $routeStations = [];
        $totalCost = 0;
        $totalRoute = [];
        $routeKilometers = null;



        $em = $this->getDoctrine()->getManager();
        $distances = $em->getRepository(Distance::class)->findAll();





        $graph = Graph::create();
        foreach ($distances as $d) {
                $graph->add($d->getStationA(), $d->getStationB(), $d->getDistance());
        }

        $form = $this->createForm(SimpleDistanceFormType::class, null, ['attr' => ['autocomplete' => 'off']]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            //walidacja

            $formdata = $form->getData();
            $validStations = [];
            for($i=1;$i<9;$i++) {
                $checkStation = $formdata['station'.$i];
                if(!empty($checkStation) && $em->getRepository(Distance::class)->isStationExists($checkStation)) {
                    $validStations[] = $checkStation;
                }
            }
            if(count($validStations)<2) {
                //return with error
            }


            $totalRoute = [];
            $totalCost = 0;

            for($i=0;$i<count($validStations)-1;$i++) {
                if(!empty($totalRoute)) {
                    array_pop($totalRoute);
                }
                $routeStations = $graph->search($validStations[$i], $validStations[$i+1]);
                $routeKilometers = $graph->cost($routeStations);
                $totalRoute = array_merge($totalRoute,$routeStations);
                $totalCost+=$routeKilometers;
            }
            //return $this->redirectToRoute('distance_result');
        }

        $stationsList = $this->getDoctrine()->getRepository(Distance::class)->getAllStations();
        $sl = [];
        foreach($stationsList as $s) {
            $sl[] = $s['station_a'];
        }

        return $this->render('distance/index.html.twig', [
            'controller_name' => 'DistanceController',
            'form' => $form->createView(),
            'formdata' => $formdata,
            'routeStations' => $totalRoute,
            'routeKilometers' => $totalCost,
            'sl' => $sl,
        ]);
    }

    /**
     * @Route("/distance/result", name="distance_result")
     */
    public function checkPrice(Request $request)
    {
            $req = $request->request->get('stationFrom');
            dd($req);
            return $this->render('distance/result.html.twig', [

            ]);
    }

    /*
    <?php
        $inputarr = [
            "Lubliniec",
        ];
        $distance = [
        37.606,
            ];
        if(count($inputarr)!=count($distance)) die('Błąd '.count($inputarr).' '.count($distance));
        for($i=0;$i<count($inputarr)-1;$i++) {
            echo ";".$inputarr[$i].";".$inputarr[$i+1].";".($distance[$i+1]-$distance[$i])."\n";
        }
    */
}
