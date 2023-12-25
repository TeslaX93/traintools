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
        if($type==='arrivals')
        {
            $arrivalString = "true";
        }

        $customDate = null;
        if(!isset($type,$mode,$stationId)) {
            return new JsonResponse("Invalid data",400);
        }
        if(!is_numeric($stationId)) {
            return new JsonResponse("Invalid station ID",400);
        }

        if (!$customDate) {
            $customDate = (new DateTime("now"))->format("dmYHi");
        }

        $url = "https://bilkom.pl/stacje/tablica?stacja=" . $stationId . "&data=" . $customDate . "&time=&przyjazd=" . $arrivalString;

        $html = @file_get_contents($url);
        if (!$html) {
            return new JsonResponse("Connection error",503);
        }

        $crawler = new Crawler($html);


        $fromStation = $crawler->filter("#fromStation")->attr('value');
        $extraLink = 'https://bilkom.pl' . $crawler->filter(".btn-primary")->first()->attr('href');

        /* bugged?
        parse_str(parse_url($extraLink)['query'], $tc);
        $company = $tc['tc'];
        */

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
            //$trainDetails[$columns[93]] = $company;
            $trainDetails[$columns[94]] = $fromStation;

            if ($mode === 'detailed') {
                $htmlExtras = @file_get_contents($extraLink);
                if (!$htmlExtras) {
                    return new JsonResponse("Bilkom data download error",503);
                }

                $detailsCrawler = new Crawler($htmlExtras);

                $amenities = BilkomHelper::getAmenities($detailsCrawler);
                $via = BilkomHelper::getViaStations($detailsCrawler);

                $trainDetails[$columns[91]] = $amenities; //udogodnienia w pociągu
                $trainDetails[$columns[92]] = $via; //via stations
            }

            $trainsList[] = $trainDetails;

            if(in_array($mode,['nextarrival','nextdeparture'])) {
                break;
            }
        }

        return new JsonResponse($trainsList,200);
    }

}
