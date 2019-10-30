<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraints\DateTime;
use App\Entity\FrequencyLastUpdate;
use App\Entity\Frequency;

class FrequencyCrawlerCommand extends Command
{
    protected static $defaultName = 'FrequencyCrawler';
    private $container;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure()
    {
        $this
            ->setDescription('Crawler for PKP Intercity Frequency Monitor')
            //->addArgument('arg1', InputArgument::OPTIONAL, 'Download new data even if there was not any new update')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Download new data even if there was not any new update');
    }

    protected function execute(InputInterface $input, OutputInterface $output) //
    {
        date_default_timezone_set('Europe/Berlin');
        $io = new SymfonyStyle($input, $output);
        $em = $this->container->get('doctrine');
        $io->note('Rozpoczęto crawling o ' . date('Y-m-d H:i:s'));
        $force = $input->getOption('force');


        $html = file_get_contents('https://www.intercity.pl/pl/site/dla-pasazera/informacje/frekwencja.html');
        $crawler = new Crawler($html);
        $lastUpdate = date_create_from_format('d.m.Y H:i', $crawler->filter('div.text-right strong')->text(), null);
        $lastSavedUpdate = $em->getRepository(FrequencyLastUpdate::class)->findOneBy([], ['id' => 'DESC']);

        if (empty($lastSavedUpdate) || ($lastUpdate != $lastSavedUpdate->getUpdatedAt()) || $force) {

            $lastSavedUpdate = new FrequencyLastUpdate();
            $lastSavedUpdate->setUpdatedAt($lastUpdate);
            $em->getManager()->persist($lastSavedUpdate);
            $em->getManager()->flush();


            $maxPages = $crawler->filter('.pagination-max')->text();
            $maxPages = str_replace('z ', '', $maxPages);
            $progress = new ProgressBar($output, $maxPages * 2);
            $progress->start();
            $crawler = $crawler->filter('table tbody')->first();
            $totalHtml = $crawler->html();

            $progress->advance();
            for ($i = 2; $i <= $maxPages; $i++) {
                $html = file_get_contents('https://www.intercity.pl/pl/site/dla-pasazera/informacje/frekwencja.html?page=' . $i);
                $nextCrawler = new Crawler($html);
                $nextCrawler = $nextCrawler->filter('table tbody')->first();
                $totalHtml .= $nextCrawler->html();
                $progress->advance();
            }

            $result = "<table>" . $totalHtml . "</table>";

            $resultFiltered = new Crawler($result);
            $resultFiltered = $resultFiltered->filter('table');
            $allTrains = [];
            $progress->setMaxSteps($maxPages + count($resultFiltered->children()));
            foreach ($resultFiltered->children() as $train) {
                $trainDetails = $train->childNodes;

                $trainDetailsArray = [];

                $trainDetailsArray['type'] = ($trainDetails[0]->nodeValue != 'Krajowy'); //true if international
                $trainDetailsArray['number'] = trim($trainDetails[1]->nodeValue);
                $trainDetailsArray['category'] = trim($trainDetails[2]->nodeValue); //ic, tlk, ...
                if (strlen($trainDetailsArray['number']) == 3 && $trainDetailsArray['type']) $trainDetailsArray['category'] .= '-BUS';
                $trainDetailsArray['name'] = trim($trainDetails[1]->nodeValue); // train [temporary, because IC. It was [3])
                $trainDetailsArray['from'] = trim($trainDetails[3]->nodeValue); // from
                $trainDetailsArray['to'] = trim($trainDetails[5]->nodeValue); // to

                $trainDetailsArray['updated'] = $lastUpdate; //attach last modification date
                $trainDetailsArray['departure'] = date('Y-m-d'); //departure date
                $trainDetailsArray['crawled'] = date('Y-m-d H:i:s'); //crawled date
                //$trainDetailsArray['status'] = $train->getAttribute('title');
                if ($train->getAttribute('title') == "Szacowana frekwencja poniżej 50%") {
                    $trainDetailsArray['status'] = 0;
                } elseif ($train->getAttribute('title') == "Szacowana frekwencja powyżej 80%") {
                    $trainDetailsArray['status'] = 2;
                } else $trainDetailsArray['status'] = 1;

                //repair some station names... or maybe later


                $archivedTrain = new Frequency();
                $archivedTrain->setType($trainDetailsArray['type']);
                $archivedTrain->setNumber($trainDetailsArray['number']);
                $archivedTrain->setCategory($trainDetailsArray['category']);
                $archivedTrain->setName($trainDetailsArray['name']);
                $archivedTrain->setFromStation($trainDetailsArray['from']);
                $archivedTrain->setToStation($trainDetailsArray['to']);
                $archivedTrain->setUpdatedAt($lastUpdate);
                $archivedTrain->setDeparture(new \DateTime());
                $archivedTrain->setCrawledAt(new \DateTime());
                $archivedTrain->setStatus($trainDetailsArray['status']);
                $em->getManager()->persist($archivedTrain);
                $em->getManager()->flush();
                $progress->advance();
            }

            $progress->finish();
        } else {
            $io->note('Operacja zakończona - brak aktualizacji frekwencji');
        }


        $io->success('Operacja zakończona pomyślnie dnia ' . date('Y-m-d H:i:s'));
    }
}
