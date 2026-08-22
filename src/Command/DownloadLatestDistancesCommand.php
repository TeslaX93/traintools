<?php

namespace App\Command;

use App\Service\DistanceGraphProvider;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'DownloadLatestDistances',
    description: 'Add a short description for your command',
)]
class DownloadLatestDistancesCommand extends Command
{
    private const CSV_URL = 'https://raw.githubusercontent.com/TeslaX93/pkp-distances/refs/heads/main/distances-commas.csv';

    /** Zabezpieczenie przed obcietym albo pustym plikiem - realny ma ~3200 wierszy. */
    private const MIN_ROWS = 100;

    public function __construct(
        private readonly Connection $connection,
        private readonly HttpClientInterface $httpClient,
        private readonly DistanceGraphProvider $graphProvider,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Downloading & importing distances');

        // Kolejność jest tu istotna: pobieramy i sprawdzamy dane ZANIM tkniemy
        // tabelę. Wcześniej TRUNCATE szedł jako pierwszy, więc jeden nieudany
        // strzał do GitHuba zostawiał pustą tabelę odległości bez możliwości
        // cofnięcia (TRUNCATE to DDL z niejawnym commitem).
        $io->section('Downloading CSV');
        try {
            $csvContent = $this->httpClient->request('GET', self::CSV_URL)->getContent();
        } catch (\Throwable $e) {
            $io->error('Nie udało się pobrać pliku CSV: ' . $e->getMessage());

            return Command::FAILURE;
        }

        $io->section('Parsing CSV');
        $rows = $this->parseCsv($csvContent);

        if (count($rows) < self::MIN_ROWS) {
            $io->error(sprintf(
                'Plik zawiera tylko %d poprawnych wierszy (minimum %d) - przerywam bez ruszania tabeli.',
                count($rows),
                self::MIN_ROWS
            ));

            return Command::FAILURE;
        }

        $io->section('Importing into database');

        // DELETE zamiast TRUNCATE, bo tylko DELETE bierze udział w transakcji -
        // przy błędzie w połowie importu stare dane wracają na miejsce.
        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement('DELETE FROM distance');

            $stmt = $this->connection->prepare(
                'INSERT INTO distance (station_a, station_b, distance) VALUES (?, ?, ?)'
            );

            foreach ($rows as $row) {
                $stmt->executeStatement($row);
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        // graf i lista stacji w cache opisuja juz nieaktualne dane
        $this->graphProvider->invalidate();
        $io->note('Cache grafu odleglosci wyczyszczony.');

        $io->success(sprintf('Imported %d rows into distances table.', count($rows)));

        return Command::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: string, 2: float}>
     */
    private function parseCsv(string $csvContent): array
    {
        $csvContent = str_replace(["\r\n", "\r"], "\n", $csvContent);
        $lines = explode("\n", $csvContent);

        $rows = [];
        foreach ($lines as $index => $line) {
            if ($index === 0 || trim($line) === '') {
                continue;
            }

            $cols = str_getcsv($line, ',');

            // id, station_a, station_b, distance
            if (count($cols) < 4) {
                continue;
            }

            $rows[] = [trim($cols[1]), trim($cols[2]), (float) $cols[3]];
        }

        return $rows;
    }
}
