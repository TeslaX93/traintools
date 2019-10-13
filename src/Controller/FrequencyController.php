<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FrequencyController extends AbstractController
{
    /**
	 * @Route("/frequency/{type}", name="frequency", requirements={"type": "html|json"}, defaults={"type": "html"})
     */
    public function index(string $type)
    {
		
		
		/*
		//$replacements = ["Warszawa Zach."=>"Warszawa Zachodnia","Warszawa Wsch."=>"Warszawa Wschodnia"];
		$html = file_get_contents('https://www.intercity.pl/pl/site/dla-pasazera/informacje/frekwencja.html');
		
		$crawler = new Crawler($html);
		$maxPages = $crawler->filter('.pagination-max')->text();
		$maxPages = str_replace('z ','',$maxPages);
		$lastUpdate = date('Y-m-d H:i:s',strtotime($crawler->filter('div.text-right strong')->text()));
		
		$crawler = $crawler->filter('table tbody')->first();
		$totalHtml = $crawler->html();
		
		for($i=2;$i<=$maxPages;$i++) {
			$html = file_get_contents('https://www.intercity.pl/pl/site/dla-pasazera/informacje/frekwencja.html?page='.$i);
			$nextCrawler = new Crawler($html);
			$nextCrawler = $nextCrawler->filter('table tbody')->first();
			$totalHtml .= $nextCrawler->html();
		}
		
		$result = "<table>".$totalHtml."</table>";
		
		//get trains and their frequency
		$resultFiltered = new Crawler($result);
		$resultFiltered = $resultFiltered->filter('table');
		$allTrains = [];
		foreach($resultFiltered->children() as $train) {
		$trainDetails = $train->childNodes;
		$trainDetailsArray = [];

				$trainDetailsArray['type'] = ($trainDetails[0]->nodeValue!='Krajowy'); //true if international
				$trainDetailsArray['number'] = trim($trainDetails[1]->nodeValue);
				$trainDetailsArray['category'] = trim($trainDetails[2]->nodeValue); //ic, tlk, ...
				if(strlen($trainDetailsArray['category'])==3 && $trainDetailsArray['type']==0) $trainDetailsArray['category'] .= '-BUS';
				$trainDetailsArray['name'] = trim($trainDetails[3]->nodeValue); // train
				$trainDetailsArray['from'] = trim($trainDetails[4]->nodeValue); // from
				$trainDetailsArray['to'] = trim($trainDetails[6]->nodeValue); // to 

			$trainDetailsArray['updated'] = $lastUpdate; //attach last modification date
			$trainDetailsArray['crawled'] = date('Y-m-d H:i:s'); //crawled date
			//$trainDetailsArray['status'] = $train->getAttribute('title');
			if($train->getAttribute('title')=="Szacowana frekwencja poniżej 50%") {
				$trainDetailsArray['status'] = 0;
			}	elseif($train->getAttribute('title')=="Szacowana frekwencja powyżej 80%") {
				$trainDetailsArray['status'] = 2;
			}	else $trainDetailsArray['status'] = 1;
			
			//repair some station names... or maybe later
			
			
			array_push($allTrains,$trainDetailsArray);
		
		}
		
		$response = new Response();
		$response->setContent(json_encode($allTrains));
		$response->headers->set('Content-Type','application/json');
		
		
		//dd($allTrains);
        //return $this->render('frequency/index.html.twig', ['result' => $result,]);
		return $response;
		*/
		
    }
}
