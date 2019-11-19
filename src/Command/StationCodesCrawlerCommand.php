<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler;

class StationCodesCrawlerCommand extends Command
{
    protected static $defaultName = 'stationCodesCrawler';
    private $container;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure()
    {
        $this
            ->setDescription('Crawler for PKP Intercity Stations')
            //->addArgument('arg1', InputArgument::OPTIONAL, 'Download new data even if there was not any new update')
            //->addOption('force', null, InputOption::VALUE_NONE, 'Download new data even if there was not any new update');
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $stationsFile = "https://gist.githubusercontent.com/TeslaX93/96fd7c44b630771563bdfc3af3d960fc/raw/049bc9c98cb524d81ee5b4c62704cc3d89d261ec/InfopasazerStationCodes.txt";
        $stationsFile = @file_get_contents($stationsFile);

        if ($stationsFile === FALSE) {

            $io->error('Nie znaleziono GISTa');
        }

        $stationsFile = explode("\n", trim($stationsFile));
        $stationsList = [];
        foreach ($stationsFile as $sfl) {
            $line = explode(",", $sfl);
            $stationsList[$line[0]] = $line[1];
        }
        $io->note("Pobrano GISTa");
        $progress = new ProgressBar($output, count($stationsList));
        $progress->setFormat('debug');
        $allTrainIDs = [];
        $activeStations = 0;
        $progress->start();

        foreach ($stationsList as $sli => $st) {
            $progress->advance();
            $html = file_get_contents("https://infopasazer.intercity.pl/index.php?p=station&id=" . $sli);
            $crawler = new Crawler($html);
            if ($crawler->filter('div.error')->count() != 0) {
                continue;
            }
            $activeStations++;
            $scheduleTable = $crawler->filter('table.table.table-delay.mbn tbody')->first()->html();
            $scheduleTable .= $crawler->filter('table.table.table-delay.mbn tbody')->last()->html();
            //get table content
            $crawler = new Crawler($scheduleTable);
            $trains = $crawler->filter('tr')->each(function ($tr, $i) {
                return $tr->filter('td span')->each(function ($td, $i) {
                    return trim($td->html());
                });
            });

            foreach ($trains as $tr) {
                $thisTrain = [];
                foreach ($tr as $idx => $td) {
                    if ($idx == 0) { //train number and name
                        $trainDetails = explode("\"", $td);
                        $thisTrain['trainId'] = str_replace("?p=train&amp;id=", "", $trainDetails[1]);
                        array_push($allTrainIDs, $thisTrain['trainId']);
                    }
                }
            }
        }
        $io->note('Aktywnych stacji: ' . $activeStations);
        //mamy listę pociągów, fajnie, co? teraz trzeba z nich wyciągnąć stację
        $allTrainIDs = array_unique($allTrainIDs);
        $progress = new ProgressBar($output, count($allTrainIDs));
        $progress->setFormat('debug');
        $progress->start();
        $allTrainStations = [];
        $stationsFound = 0;
        foreach ($allTrainIDs as $train) {
            $html = file_get_contents('https://infopasazer.intercity.pl/?p=train&id=' . $thisTrain['trainId']);
            $crawler2 = new Crawler($html);
            $delayTable = $crawler2->filter('table.table-delay tbody')->first()->html();
            $crawler2 = new Crawler($delayTable);
            $stationsTable = $crawler2->filter('tr')->each(function ($tr, $i) {
                return $tr->filter('td span')->each(function ($td, $i) {
                    return trim($td->html());
                });
            });
            $progress->advance();
            foreach ($stationsTable as $stations) {
                //if(!isset($stationsTable[]))
                $station = trim(strip_tags($stations[3]));
                $trainDetails = explode("\"", $stations[3]);
                $trainDetails = str_replace("?p=station&amp;id=", "", $trainDetails[1]);
                if (!isset($allTrainStations[$trainDetails]) && (!in_array($station, $stationsList))) {
                    $allTrainStations[$trainDetails] = $station;
                    $stationsFound++;
                }
            }
        }
        $missingStationsFile = fopen('/missingstations.txt', 'a');

        foreach ($allTrainStations as $idx => $ats) {
            $missingStation = $idx . ' ' . $ats;
            fwrite($missingStationsFile, $missingStation);
        }
        fclose($missingStationsFile);
        $io->success('Ukończono crawling, znaleziono ' . $stationsFound . ' stacji, których jeszcze nie ma na liście');
    }
}
