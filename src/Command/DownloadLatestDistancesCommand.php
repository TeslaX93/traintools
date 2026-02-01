<?php

namespace App\Command;

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

    public function __construct(
        private readonly Connection $connection,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Downloading & importing distances');

        $io->section('Truncating table distances');
        $this->connection->executeStatement('TRUNCATE TABLE distance');

        $io->section('Downloading CSV');
        $response = $this->httpClient->request('GET', self::CSV_URL);
        $csvContent = $response->getContent();

        $io->section('Importing into database');

        $csvContent = str_replace(["\r\n", "\r"], "\n", $csvContent);
        $lines = explode("\n", $csvContent);

        $stmt = $this->connection->prepare(
            'INSERT INTO distance (station_a, station_b, distance) VALUES (?, ?, ?)'
        );

        $inserted = 0;

        $this->connection->beginTransaction();
        try {
            foreach ($lines as $index => $line) {
                if ($index === 0 || trim($line) === '') {
                    continue;
                }

                $cols = str_getcsv($line, ',');

                // id, station_a, station_b, distance
                if (count($cols) < 4) {
                    continue;
                }

                $stmt->executeStatement([
                    trim($cols[1]),
                    trim($cols[2]),
                    (float) $cols[3],
                ]);

                $inserted++;
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf('Imported %d rows into distances table.', $inserted));

        return Command::SUCCESS;
    }
}
