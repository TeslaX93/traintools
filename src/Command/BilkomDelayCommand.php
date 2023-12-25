<?php

namespace App\Command;

use DateTime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;
use App\Helper\RomanToNumber;

#[AsCommand(
    name: 'BilkomDelay',
    description: 'Add a short description for your command',
)]
class BilkomDelayCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('station-id', InputArgument::OPTIONAL, 'Station ID')
            ->addArgument('custom-date', InputArgument::OPTIONAL, 'Custom date and time (format ddmmyyyyhhmm)')
            ->addOption('arrival', null, InputOption::VALUE_NONE, 'Arrival? (default: no)')
            ->addOption('extras', null, InputOption::VALUE_NONE, 'Extra informations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        //@TODO: rework


        $io = new SymfonyStyle($input, $output);
        $stationId = $input->getArgument('station-id');
        $customDate = $input->getArgument('custom-date');
        $arrivalStatus = false;
        $arrivalString = "false";
        $extras = false;

        if (!$stationId) {
            $stationId = "5100069";
        }

        if ($input->getOption('arrival')) {
            $arrivalStatus = true;
            $arrivalString = "true";
            // ... do something
        }

        if ($input->getOption('extras')) {
            $extras = true;
        }

        if (!$customDate) {
            $customDate = (new DateTime("now"))->format("dmYHi");
        }

        //...
        $stationsFile = "";

        $url = "https://bilkom.pl/stacje/tablica?stacja=" . $stationId . "&data=" . $customDate . "&time=&przyjazd=" . $arrivalString;

        $html = @file_get_contents($url);
        if (!$html) {
            $io->error('Błąd łączenia z serwisem BILKOM.');
            return Command::FAILURE;
        }

        //passed?

        $crawler = new Crawler($html);


        $fromStation = $crawler->filter("#fromStation")->attr('value');
        $extraLink = 'https://bilkom.pl' . $crawler->filter(".btn-primary")->first()->attr('href');

        parse_str(parse_url($extraLink)['query'], $tc);
        $company = $tc['tc'];

        if ($crawler->filter('ul#timetable')->count() === 0) {
            $io->error('Błąd pobierania danych z serwisu BILKOM.');
            return Command::FAILURE;
        }

        //check if there are any trains?

        $crawler = new Crawler($crawler->filter('ul#timetable')->html());

        $trains = $crawler->filter('.el')->each(function ($el, $i) {
            return $el->filter('div')->each(function ($div, $i) {
                return trim($div->html());
            });
        }); //extracts divs from every .el, need to make a little bit better


        $columns = [
            0 => 'all',
            3 => 'trainCode',
            5 => 'dateDetails',
            7 => 'timestamp1000',
            8 => 'timeString',
            9 => 'dateString',
            11 => 'arrivalStation',
            12 => 'trackPlatform',
            91 => 'amenities',
            92 => 'via',
            93 => 'company',
            94 => 'currentStation',
            95 => 'calculatedTime',
            96 => 'timestamp',
            97 => 'track',
            98 => 'platform',
            99 => 'delay'
        ];

        $trainsList = [];

        foreach ($trains as $t) {
            $trainDetails = [];
            $delay = (new Crawler($t[0]))->filter('.time')->attr('data-difference');

            $trainDetails[$columns[3]] = $t[3];
            $trainDetails[$columns[96]] = (int)$t[7] / 1000;
            if (isset($t[11])) {
                $trainDetails[$columns[97]] = explode("/", $t[12])[1];
                $trainDetails[$columns[98]] = explode("/", $t[12])[0]; //RomanToNumber::rtn(explode("/",$t[11])[0]);
            } else {
                $trainDetails[$columns[97]] = '';
                $trainDetails[$columns[98]] = '';
            }
            $trainDetails[$columns[94]] = $fromStation;
            $trainDetails[$columns[11]] = $t[11];
            if ($delay) {
                $trainDetails[$columns[99]] = trim($delay, "+' ");
            } else {
                $trainDetails[$columns[99]] = 0;
            }

            $trainDetails[$columns[95]] = $trainDetails[$columns[96]] + $trainDetails[$columns[99]];
            $trainDetails[$columns[93]] = $company;

            //@TODO: turn off
            $extras = true;

            if ($extras) {
                $htmlExtras = @file_get_contents($extraLink);
                if (!$htmlExtras) {
                    $io->error('Błąd łączenia z serwisem BILKOM.');
                    return Command::FAILURE;
                }

                $detailsCrawler = new Crawler($htmlExtras);

                $amenities = $detailsCrawler->filter('.services ul')->each(function ($el, $i) {
                    return $el->filter('li')->each(function ($li, $i) {
                        return trim($li->attr('title'));
                    });
                });
                $amenities = $amenities[0];
                $amenities = str_replace("<hr/>", ": ", $amenities);

                $viatable = $detailsCrawler->filter('.trip')->each(function ($el, $i) {
                    return $el->filter('div')->each(function ($li, $i) {
                        return trim($li->html());
                    });
                });
                $via = [];

                foreach ($viatable as $viaelement) {
                    $viastation = [];

                    // 4 & 9 if 13, 4 & 10 if 14
                    $viastation['arrival'] = empty($viaelement[4]) ? null : ((int)$viaelement[4] / 1000);
                    if (count($viaelement) == 13) {
                        $viastation['departure'] = empty($viaelement[9]) ? null : ((int)$viaelement[9] / 1000);
                    } else {
                        $viastation['departure'] = empty($viaelement[10]) ? null : ((int)$viaelement[10] / 1000);
                    }


                    $detailsCrawler = new Crawler($viaelement[3]);
                    $viastation['delayonarrival'] = $detailsCrawler->filter('.time')->attr('data-difference');
                    $detailsCrawler = new Crawler($viaelement[8]);
                    $viastation['delayondeparture'] = $detailsCrawler->filter('.time')->attr('data-difference');

                    $viastation['delayonarrival'] = trim($viastation['delayonarrival'], "+' ");
                    $viastation['delayondeparture'] = trim($viastation['delayondeparture'], "+' ");

                    if ((empty($viastation['arrival']) || (empty($viastation['departure'])))) {
                        $viastation['stop'] = null;
                    } else {
                        $viastation['stop'] = intdiv($viastation['departure'] - $viastation['arrival'], 60);
                    }
                    if (($viastation['stop']) == 0) {
                        $viastation['stop']++;
                    }
                    $viastation['station'] = strip_tags(array_pop($viaelement));
                    $viastation['ondemand'] = false;
                    if (str_contains($viastation['station'], "(NŻ)")) {
                        $viastation['ondemand'] = true;
                    }
                    $via[] = $viastation;
                }
                $trainDetails[$columns[91]] = $amenities; //udogodnienia w pociągu
                $trainDetails[$columns[92]] = $via; //via stations
            }

            $trainsList[] = $trainDetails;
        }

        $io->info($trainsList);

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }
}
