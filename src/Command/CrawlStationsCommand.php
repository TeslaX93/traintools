<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'CrawlStations',
    description: 'Crawls Portal Pasażera station catalog and saves stations to DB (memory-safe)',
)]
class CrawlStationsCommand extends Command
{
    private const BASE = 'https://portalpasazera.pl';

    private const LETTERS = [
        'a','b','c','ć','d','e','f','g','h','i','j','k','l','ł','m','n','o','p','r','s','ś','t','u','w','z','ź','ż',
    ];

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly Connection $db,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('truncate', null, InputOption::VALUE_NONE, 'Truncate station table before crawling')
            ->addOption('pages', null, InputOption::VALUE_REQUIRED, 'Max pages per letter (default 20)', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Crawling stations (memory-safe)');


        $maxPages = max(1, (int) $input->getOption('pages'));

        if ($input->getOption('truncate')) {
            $io->section('Truncating station table');
            $this->db->executeStatement('TRUNCATE TABLE station');
        }

        $platform = $this->db->getDatabasePlatform()->getName();

            // MySQL/MariaDB (i zwykle też pasuje do wielu innych jako "IGNORE", ale tu celujemy w MySQL-family)
            $insertSql = <<<SQL
INSERT IGNORE INTO station (name, address, gps_lat, gps_lng, display_url, station_url)
VALUES (:name, :address, :gps_lat, :gps_lng, :display_url, :station_url)
SQL;


        $insertStmt = $this->db->prepare($insertSql);

        $added = 0;
        $dupes = 0;
        $errors = 0;
        $visitedStationPages = 0;

        // (opcjonalny) mały “seen” na poziomie całego runu – trzyma md5 zamiast pełnych URL
        // Jeśli chcesz maksymalnie oszczędzać RAM, możesz to wyłączyć (ustawiając na null i pomijając użycie).
        $seen = [];

        foreach (self::LETTERS as $letter) {
            for ($p = 1; $p <= $maxPages; $p++) {
                $listUrl = self::BASE . '/KatalogStacji?nazwa=' . rawurlencode($letter) . '&p=' . $p;
                try {
                    $html = $this->http->request('GET', $listUrl)->getContent();
                } catch (\Throwable $e) {
                    $errors++;
                    $io->warning(sprintf('List fetch failed (%s): %s', $listUrl, $e->getMessage()));
                    continue;
                }

                $crawler = new Crawler($html, $listUrl, null, false);

                // Linki do stron stacji: /KatalogStacji/Index?stacja=...
                $stationHrefs = $crawler
                    ->filter('a[href^="/KatalogStacji/Index?stacja="]')
                    ->each(static fn(Crawler $a) => $a->attr('href'));

                // Zwolnij ciężkie zmienne ASAP
                unset($crawler, $html);

                if (!$stationHrefs) {
                    continue;
                }

                foreach ($stationHrefs as $href) {
                    if (!$href) {
                        continue;
                    }

                    $stationUrl = self::BASE . $href;

                    // opcjonalna deduplikacja w pamięci (hash), żeby nie wchodzić drugi raz w tę samą stronę
                    $hash = md5($stationUrl);
                    if (isset($seen[$hash])) {
                        continue;
                    }
                    $seen[$hash] = true;

                    $visitedStationPages++;

                    try {
                        $stationHtml = $this->http->request('GET', $stationUrl)->getContent();
                    } catch (\Throwable $e) {
                        $errors++;
                        $io->warning(sprintf('Station fetch failed (%s): %s', $stationUrl, $e->getMessage()));
                        continue;
                    }

                    $stationCrawler = new Crawler($stationHtml, $stationUrl, null, false);

                    // Link do wyświetlacza: /Wyswietlacz?sid=...
                    $displayHref = $stationCrawler
                        ->filter('a[href^="/Wyswietlacz?sid="]')
                        ->first()
                        ->attr('href');

                    if (!$displayHref) {
                        $errors++;
                        $io->warning(sprintf('No displayHref in (%s): %s', $stationUrl, $e->getMessage()));
                        unset($stationCrawler, $stationHtml);
                        continue;
                    }

                    $displayUrl = substr($displayHref,17);

                    // Wyciąganie pól – odporne na drobne zmiany layoutu: parsujemy tekst BODY
                    $bodyText = preg_replace('/[ \t]+/u', ' ', $stationCrawler->filter('body')->text(''));

                    unset($stationCrawler, $stationHtml);

                    // Nazwa: między "Nazwa" a "Adres"
                    if (!preg_match('/\bNazwa\s+(.+?)\s+Adres\b/u', $bodyText, $mName)) {
                        $errors++;
                        unset($bodyText);
                        continue;
                    }
                    $name = trim($mName[1]);

                    // Adres: między "Adres" a "Współrzędne GPS"
                    if (!preg_match('/\bAdres\s+(.+?)\s+Współrzędne GPS\b/us', $bodyText, $mAddr)) {
                        $errors++;
                        unset($bodyText);
                        continue;
                    }
                    $address = trim($mAddr[1]);

                    // GPS: "Współrzędne GPS 52,1037664 16,1548458" (często przecinki)
                    if (!preg_match('/\bWspółrzędne GPS\s+([0-9.,]+)\s+([0-9.,]+)/u', $bodyText, $mGps)) {
                        $errors++;
                        unset($bodyText);
                        continue;
                    }
                    $lat = (float) str_replace(',', '.', $mGps[1]);
                    $lng = (float) str_replace(',', '.', $mGps[2]);

                    unset($bodyText);

                    // Zapis do DB: dedupe robi UNIQUE(station_url) + IGNORE/ON CONFLICT
                    try {
                        $affected = $insertStmt->executeStatement([
                            'name' => $name,
                            'address' => $address,
                            'gps_lat' => $lat,
                            'gps_lng' => $lng,
                            'display_url' => $displayUrl,
                            'station_url' => md5($stationUrl),
                        ]);

                        // MySQL: 1 = inserted, 0 = ignored (duplikat)
                        if ($affected === 1) {
                            $added++;
                        } else {
                            $dupes++;
                        }
                    } catch (\Throwable $e) {
                        $errors++;
                        $io->warning(sprintf('DB insert failed (%s): %s', $stationUrl, $e->getMessage()));
                        continue;
                    }

                    // Co jakiś czas pokaż postęp (bez progress bar, żeby było lekko)
                    if (($added + $dupes) % 500 === 0) {
                        $io->writeln(sprintf(
                            'Progress: inserted=%d, dupes=%d, errors=%d, visitedStationPages=%d',
                            $added, $dupes, $errors, $visitedStationPages
                        ));
                    }
                }

                // Uwolnij tablicę linków z danej strony listingu
                unset($stationHrefs);
            }
        }

        $io->success(sprintf(
            'Done. Inserted=%d, dupes=%d, errors=%d, station pages visited=%d',
            $added, $dupes, $errors, $visitedStationPages
        ));

        return Command::SUCCESS;
    }
}
