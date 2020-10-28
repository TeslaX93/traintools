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
		$routeKilometers = null;
		
		
		
		$em = $this->getDoctrine()->getManager();
		$distances = $em->getRepository(Distance::class)->findAll();
		
		
		$allStations = $em->getRepository(Distance::class)->getAllStations();
		$asTemp = [];
		$allStationsFormatted = '[';
		foreach($allStations as $as) {
			array_push($asTemp,$as['station_a']);
			$allStationsFormatted .= '"'.$as['station_a'].'",';
		}
		$allStations = $asTemp;
		$allStationsFormatted = rtrim($allStationsFormatted,",");
		$allStationsFormatted .= '];';
		
		
		$graph = Graph::create();
		foreach($distances as $d) {
				$graph->add($d->getStationA(),$d->getStationB(),$d->getDistance());
		}		
		
		$form = $this->createForm(SimpleDistanceFormType::class, null, ['attr' => ['autocomplete'=>'off']]);
		$form->handleRequest($request);
		if($form->isSubmitted() && $form->isValid()) {
			//$formdata = $form->getData();
			//dd($formdata);
			$formdata = $form->getData();
			$totalRoute = [];
			$totalCost = 0;
			
			$routeStations = $graph->search($formdata['station1'],$formdata['station2']);
			$routeKilometers = $graph->cost($routeStations);
			//return $this->redirectToRoute('distance_result');
		}
		/*
		

		*/
		
		

		

        return $this->render('distance/index.html.twig', [
            'controller_name' => 'DistanceController',
			'form' => $form->createView(),
			'formdata' => $formdata,
			'routeStations' => $routeStations,
			'routeKilometers' => $routeKilometers,
			'allStationsFormatted' => $allStationsFormatted,
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
