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
		$graph = Graph::create();
		foreach($distances as $d) {
				$graph->add($d->getStationA(),$d->getStationB(),$d->getDistance());
		}		
		
		$form = $this->createForm(SimpleDistanceFormType::class);
		$form->handleRequest($request);
		if($form->isSubmitted() && $form->isValid()) {
			//$formdata = $form->getData();
			//dd($formdata);
			$formdata = $form->getData();
			$totalRoute = [];
			$totalCost = [];
			$routeStations = $graph->search($formdata['stationFrom'],$formdata['stationTo']);
			$routeKilometers = $graph->cost($routeStations);
			//return $this->redirectToRoute('distance_result');
		}
		/*
		
		
		
		

		//
		//dd([$route,$graph->cost($route)]);
		//dd($graph->cost($route));
		*/
		
		

		

        return $this->render('distance/index.html.twig', [
            'controller_name' => 'DistanceController',
			'form' => $form->createView(),
			'formdata' => $formdata,
			'routeStations' => $routeStations,
			'routeKilometers' => $routeKilometers,
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
