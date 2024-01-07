<?php

namespace App\Controller;

use App\Helper\BilkomHelper;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\BilkomRepository;

class BilkomController extends AbstractController
{
    #[Route('/bilkom', name: 'app_bilkom')]
    public function index(): Response
    {
        return $this->render('bilkom/index.html.twig', [
            'controller_name' => 'BilkomController',
        ]);
    }

    #[Route('/bilkom/api/{type}/{mode}/{stationId}', name: 'app_bilkom_api')]
    public function api(string $type, string $mode, string $stationId): JsonResponse
    {
        /* TEST DATA: type: departures, mode: basic, stationId: 5100069 */

        $arrivalString = "false";
        if(in_array($type,['arrivals','nextarrival'],true))
        {
            $arrivalString = "true";
        }
        if(in_array($type,['departures','nextdeparture'],true))
        {
            $arrivalString = "false";
        }
        if(!in_array($type,['arrivals','departures','nextarrival','nextdeparture'],true))
        {
            return new JsonResponse("Invalid type",400);
        }

        $customDate = null;
        if(!isset($type,$mode,$stationId)) {
            return new JsonResponse("Missing data",400);
        }
        if(!is_numeric($stationId)) {
            return new JsonResponse("Invalid station ID",400);
        }

        if (!$customDate) {
            $customDate = (new DateTime("now"))->format("dmYHi");
        }

        $url = BilkomHelper::generateBilkomUrl($stationId,$customDate,$arrivalString);

        $html = @file_get_contents($url);
        if (!$html) {
            return new JsonResponse("Connection error",503);
        }

        $crawler = new Crawler($html);
        $fromStation = $crawler->filter("#fromStation")->attr('value');

        if ($crawler->filter('ul#timetable')->count() === 0) {
            return new JsonResponse("Bilkom data download error",503);
        }

        //check if there are any trains?

        $crawler = new Crawler($crawler->filter('ul#timetable')->html());

        $trains = $crawler->filter('.el')->each(function ($el, $i) {
            return $el->filter('div')->each(function ($div, $i) {
                return trim($div->html());
            });
        }); //extracts divs from every .el, need to make it a little bit better


        $columns = BilkomHelper::getColumns();

        $trainsList = [];

        foreach ($trains as $t) {

            $trainDetails = BilkomHelper::basicTrainAnalysis($t,$columns);
            $trainDetails[$columns[94]] = $fromStation;

            if ($mode === 'extended' && !is_null($trainDetails[$columns[90]])) {
                $extraLink = 'https://bilkom.pl' . $trainDetails[$columns[90]];
                $htmlExtras = @file_get_contents($extraLink);
                if (!$htmlExtras) {
                    return new JsonResponse("Bilkom data download error",503);
                }

                $detailsCrawler = new Crawler($htmlExtras);

                $amenities = BilkomHelper::getAmenities($detailsCrawler);
                $via = BilkomHelper::getViaStations($detailsCrawler,$fromStation);

                $trainDetails[$columns[91]] = $amenities; //udogodnienia w pociągu
                $trainDetails[$columns[92]] = $via; //via stations
            }

            $trainsList[] = $trainDetails;

            if(in_array($type,['nextarrival','nextdeparture'])) {
                break;
            }
        }

        return new JsonResponse($trainsList,200);
    }

    #[Route('/bilkom/example1', name: 'app_bilkom_example1')]
    public function bilkomExample1(): Response
    {
        $data = $this->api('nextdeparture','extended','5100069');
        $error = null;
        if($data->getStatusCode() != 200)
        {
            $error = $data->getContent();
        }

        $data = $data->getContent();

        return $this->render('bilkom/example1.html.twig', [
            'data' => json_decode($data,true)[0],
            'error' => $error
        ]);
    }
}
