<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ICStationsCrawler',
    description: 'Add a short description for your command',
)]
class ICStationsCrawlerCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Pobiera plik station.js i zapisuje go lokalnie')
            ->setHelp('Ta komenda pobiera plik station.js z adresu https://www.intercity.pl/js/station.js i zapisuje go w lokalizacji public/js/station.js')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $url = 'https://www.intercity.pl/js/station.js';
        $destinationPath = 'public/js/station.js';

        // Pobieranie pliku
        $io->info('Pobieram plik station.js...');

        try {
            $fileContents = file_get_contents($url);

            if ($fileContents === false) {
                $io->error('Nie udało się pobrać pliku station.js');
                return Command::FAILURE;
            }

            // Sprawdzanie rozmiaru pliku
            if (strlen($fileContents) <= 1024) {
                $io->error('Pobrany plik ma rozmiar mniejszy niż 1 KB');
                return Command::FAILURE;
            }

            // Zapis pliku do lokalizacji public/js/station.js
            if (!is_dir(dirname($destinationPath))) {
                mkdir(dirname($destinationPath), 0777, true);
            }

            file_put_contents($destinationPath, $fileContents);

            $io->success('Plik station.js został pomyślnie pobrany i zapisany.');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Wystąpił błąd podczas pobierania pliku: ' . $e->getMessage());
            return Command::FAILURE;
        }

    }
}
