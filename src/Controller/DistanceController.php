<?php

namespace App\Controller;

use App\Repository\StationRepository;
use App\Service\DistanceGraphProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use App\Form\SimpleDistanceFormType;
use Symfony\Component\HttpFoundation\Request;

class DistanceController extends AbstractController
{

    public function __construct(private readonly DistanceGraphProvider $graphProvider)
    {
    }

    #[Route('/distance', name: 'distance')]
    public function index(Request $request): Response
    {
        $formdata = null;
        $routeStations = [];
        $totalCost = 0;
        $totalRoute = [];
        $routeKilometers = null;


        $graph = $this->graphProvider->getGraph();

        $form = $this->createForm(SimpleDistanceFormType::class, null, ['attr' => ['autocomplete' => 'off']]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            //walidacja

            $formdata = $form->getData();
            $validStations = [];
            for ($i = 1; $i < 9; $i++) {
                $checkStation = $formdata['station' . $i];
                if (!empty($checkStation) && $this->graphProvider->stationExists($checkStation)) {
                    $validStations[] = $checkStation;
                }
            }
            if (count($validStations) < 2) {
                //return with error
            }


            $totalRoute = [];
            $totalCost = 0;

            for ($i = 0; $i < count($validStations) - 1; $i++) {
                if (!empty($totalRoute)) {
                    array_pop($totalRoute);
                }
                $routeStations = $graph->search($validStations[$i], $validStations[$i + 1]);
                $routeKilometers = $graph->cost($routeStations);
                $totalRoute = array_merge($totalRoute, $routeStations);
                $totalCost += $routeKilometers;
            }
        }

        $sl = $this->graphProvider->getStations();


        return $this->render('distance/index.html.twig', [
            'controller_name' => 'DistanceController',
            'form' => $form->createView(),
            'formdata' => $formdata,
            'routeStations' => $totalRoute,
            'routeKilometers' => $totalCost,
            'sl' => $sl,
        ]);
    }

    #[Route('/distance/api/stations', name: 'app_stations_api')]
    public function apistations(): JsonResponse
    {
        return new JsonResponse($this->graphProvider->getStations());
    }

    #[Route('/distance/random', name: 'app_random_station')]
    public function randomStation(): Response
    {
        $stations = json_encode($this->graphProvider->getStations());
        return $this->render('distance/random.html.twig', [
            'stations' => $stations,
        ]);
    }

    #[Route('/panels', name: 'station_panels', methods: ['GET', 'POST'])]
    public function panels(Request $request, StationRepository $stationRepository): Response
    {
        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('panel_select', $token)) {
                throw new AccessDeniedException('Invalid CSRF token.');
            }

            $stationId = (int) $request->request->get('station_id', 0);
            $station = $stationRepository->find($stationId);

            if (!$station || !$station->getDisplayUrl()) {
                $this->addFlash('error', 'Nie znaleziono stacji lub brak display_url.');
                return $this->redirectToRoute('station_panels');
            }

            return $this->redirect('https://portalpasazera.pl/Wyswietlacz?sid='.$station->getDisplayUrl());
        }

        // GET -> render listy
        $stations = $stationRepository->findForPanels();

        return $this->render('distance/panels.html.twig', [
            'stations' => $stations,
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
