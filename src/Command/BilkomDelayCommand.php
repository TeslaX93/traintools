<?php

namespace App\Command;

use App\Helper\BilkomHelper;
use App\Helper\Constants;
use App\Service\HtmlFetcher;
use DateTime;
use DateTimeZone;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Podgląd tablicy Bilkomu z wiersza poleceń.
 *
 * Komenda korzysta z tego samego BilkomHelpera co API. Wcześniej miała własną,
 * skopiowaną kopię parsera - z innymi numerami kolumn, więc obie wersje
 * rozjechały się i dawały różne wyniki.
 */
#[AsCommand(
    name: 'BilkomDelay',
    description: 'Pokazuje tablice odjazdow/przyjazdow ze stacji wraz z opoznieniami',
)]
class BilkomDelayCommand extends Command
{
    private const BOARD_TTL = 30;

    private const DETAILS_TTL = 30;

    /** Wrocław Główny. */
    private const DEFAULT_STATION = '5100069';

    public function __construct(private readonly HtmlFetcher $fetcher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('station-id', InputArgument::OPTIONAL, 'Numer stacji wg bilkom.pl')
            ->addArgument('custom-date', InputArgument::OPTIONAL, 'Data i godzina w formacie ddmmyyyyhhmm')
            ->addOption('arrival', null, InputOption::VALUE_NONE, 'Przyjazdy zamiast odjazdow')
            ->addOption('extras', null, InputOption::VALUE_NONE, 'Dociagnij udogodnienia i stacje posrednie');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $stationId = (string) ($input->getArgument('station-id') ?: self::DEFAULT_STATION);
        $customDate = (string) ($input->getArgument('custom-date')
            ?: (new DateTime('now', new DateTimeZone(Constants::TIMEZONE)))->format('dmYHi'));
        $arrivals = $input->getOption('arrival') ? 'true' : 'false';

        $url = BilkomHelper::generateBilkomUrl($stationId, $customDate, $arrivals);
        $html = $this->fetcher->fetch($url, self::BOARD_TTL);

        if ($html === null) {
            $io->error('Blad laczenia z serwisem BILKOM.');

            return Command::FAILURE;
        }

        $crawler = new Crawler($html);

        if ($crawler->filter('ul#timetable')->count() === 0) {
            $io->error('Strona nie zawiera tablicy - Bilkom mogl zmienic uklad albo stacja nie istnieje.');

            return Command::FAILURE;
        }

        $fromStation = $crawler->filter('#fromStation')->attr('value');

        $rows = (new Crawler($crawler->filter('ul#timetable')->html()))
            ->filter('.el')
            ->each(fn ($el) => $el->filter('div')->each(fn ($div) => trim($div->html())));

        $trains = [];
        foreach ($rows as $row) {
            $train = BilkomHelper::basicTrainAnalysis($row);
            $train['currentStation'] = $fromStation;
            $trains[] = $train;
        }

        if ($input->getOption('extras')) {
            $this->addExtras($trains, $fromStation);
        }

        $io->title(sprintf('%s - %s', $fromStation, $input->getOption('arrival') ? 'przyjazdy' : 'odjazdy'));
        $io->table(
            ['Godzina', 'Pociag', 'Relacja', 'Peron/tor', 'Opoznienie'],
            array_map(static fn (array $t): array => [
                date('H:i', (int) $t['timestamp']),
                $t['trainCode'],
                $t['arrivalStation'],
                trim($t['platform'] . '/' . $t['track'], '/'),
                (int) $t['delay'] > 0 ? '+' . $t['delay'] . ' min' : '-',
            ], $trains)
        );

        if ($input->getOption('extras')) {
            foreach ($trains as $train) {
                if (empty($train['via'])) {
                    continue;
                }
                $io->section($train['trainCode']);
                $io->listing(array_map(
                    static fn (array $v): string => $v['station'] . ($v['ondemand'] ? ' (NZ)' : ''),
                    $train['via']
                ));
            }
        }

        $io->success(sprintf('Pociagow na tablicy: %d', count($trains)));

        return Command::SUCCESS;
    }

    /**
     * @param list<array<string, mixed>> $trains
     */
    private function addExtras(array &$trains, ?string $fromStation): void
    {
        $urls = [];
        foreach ($trains as $i => $train) {
            if (!empty($train['extraLink'])) {
                $urls[$i] = 'https://bilkom.pl' . $train['extraLink'];
            }
        }

        $pages = $urls ? $this->fetcher->fetchMany($urls, self::DETAILS_TTL) : [];

        foreach ($urls as $i => $url) {
            if ($pages[$i] === null) {
                continue;
            }

            $details = new Crawler($pages[$i]);
            $trains[$i]['amenities'] = BilkomHelper::getAmenities($details);
            $trains[$i]['via'] = BilkomHelper::getViaStations($details, $fromStation);
        }
    }
}
