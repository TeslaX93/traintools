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
        $routeError = null;


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
            $totalRoute = [];
            $totalCost = 0;

            if (count($validStations) < 2) {
                $routeError = 'Podaj co najmniej dwie istniejące stacje.';
            }

            for ($i = 0; $i < count($validStations) - 1 && $routeError === null; $i++) {
                $from = $validStations[$i];
                $to = $validStations[$i + 1];

                // ta sama stacja dwa razy pod rząd - biblioteka rzuca wyjątkiem
                // zamiast oddać trasę zerowej długości
                if ($from === $to) {
                    if ($totalRoute === []) {
                        $totalRoute = [$from];
                    }
                    continue;
                }

                try {
                    $routeStations = $graph->search($from, $to);
                } catch (\Throwable) {
                    // stacje istnieją, ale nie łączy ich żadna trasa w danych
                    $routeError = sprintf('Nie znaleziono połączenia: %s - %s.', $from, $to);
                    $totalRoute = [];
                    $totalCost = 0;
                    break;
                }

                if (!empty($totalRoute)) {
                    array_pop($totalRoute);
                }
                $totalRoute = array_merge($totalRoute, $routeStations);
                $totalCost += $graph->cost($routeStations);
            }
        }

        $sl = $this->graphProvider->getStations();


        return $this->render('distance/index.html.twig', [
            'controller_name' => 'DistanceController',
            'form' => $form->createView(),
            'formdata' => $formdata,
            'routeStations' => $totalRoute,
            'routeKilometers' => $totalCost,
            'routeError' => $routeError,
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
