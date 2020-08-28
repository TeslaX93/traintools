<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Taniko\Dijkstra\Graph;
use App\Entity\Distance;

class DistanceController extends AbstractController
{
    /**
     * @Route("/distance", name="distance")
     */
    public function index()
    {
		
		$em = $this->getDoctrine()->getManager();
		$distances = $em->getRepository(Distance::class)->findAll();
		
		$graph = Graph::create();
		
		foreach($distances as $d) {
			$graph->add($d->getStationA(),$d->getStationB(),$d->getDistance());
		}
		
		//$graph->add('a','b',3.045);
		$route = $graph->search('Czechowice-Dziedzice','Olkusz');
		dd($route);
		//dd($graph->cost($route));
        return $this->render('distance/index.html.twig', [
            'controller_name' => 'DistanceController',
        ]);
    }
}
