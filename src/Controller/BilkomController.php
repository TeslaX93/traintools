<?php

namespace App\Controller;

use App\Helper\BilkomHelper;
use App\Helper\Constants;
use App\Service\HtmlFetcher;
use DateTime;
use DateTimeZone;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BilkomController extends AbstractController
{
    /** Tablica odjazdów zmienia się na bieżąco - trzymamy ją krótko. */
    private const BOARD_TTL = 30;

    /** Strona szczegółów pociągu zawiera opóźnienia, więc też krótko. */
    private const DETAILS_TTL = 30;

    public function __construct(private readonly HtmlFetcher $fetcher)
    {
    }

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
            $customDate = (new DateTime("now", new DateTimeZone(Constants::TIMEZONE)))->format("dmYHi");
        }

        $url = BilkomHelper::generateBilkomUrl($stationId,$customDate,$arrivalString);

        $html = $this->fetcher->fetch($url, self::BOARD_TTL);
        if ($html === null) {
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

        $onlyFirst = in_array($type,['nextarrival','nextdeparture']);

        $parsedTrains = [];
        foreach ($trains as $t) {
            $parsedTrains[] = BilkomHelper::basicTrainAnalysis($t,$columns);

            if ($onlyFirst) {
                break;
            }
        }

        // Tryb extended potrzebuje osobnej strony dla każdego pociągu. Zbieramy
        // adresy i pobieramy je jednym równoległym strzałem, zamiast czekać na
        // każdy z osobna w pętli.
        $detailUrls = [];
        if ($mode === 'extended') {
            foreach ($parsedTrains as $i => $trainDetails) {
                if (!is_null($trainDetails[$columns[90]])) {
                    $detailUrls[$i] = 'https://bilkom.pl' . $trainDetails[$columns[90]];
                }
            }
        }
        $detailsHtml = $detailUrls ? $this->fetcher->fetchMany($detailUrls, self::DETAILS_TTL) : [];

        $trainsList = [];

        foreach ($parsedTrains as $i => $trainDetails) {
            $trainDetails[$columns[94]] = $fromStation;

            if (isset($detailUrls[$i])) {
                if ($detailsHtml[$i] === null) {
                    return new JsonResponse("Bilkom data download error",503);
                }

                $detailsCrawler = new Crawler($detailsHtml[$i]);

                $amenities = BilkomHelper::getAmenities($detailsCrawler);
                $via = BilkomHelper::getViaStations($detailsCrawler,$fromStation);

                $trainDetails[$columns[91]] = $amenities; //udogodnienia w pociągu
                $trainDetails[$columns[92]] = $via; //via stations
            }

            $trainsList[] = $trainDetails;
        }

        return new JsonResponse($trainsList,200);
    }

    #[Route('/bilkom/example1', name: 'app_bilkom_example1')]
    public function bilkomExample1(): Response
    {
        $response = $this->api('nextdeparture','extended','5100069');
        $error = null;
        $data = null;

        if ($response->getStatusCode() !== 200) {
            $error = $response->getContent();
        } else {
            $decoded = json_decode($response->getContent(), true);
            // pusta tablica = stacja bez najbliższych odjazdów
            $data = $decoded[0] ?? null;
            if ($data === null) {
                $error = 'Brak najbliższych odjazdów';
            }
        }

        return $this->render('bilkom/example1.html.twig', [
            'data' => $data,
            'error' => $error
        ]);
    }
}
