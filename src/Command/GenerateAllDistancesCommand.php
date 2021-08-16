<?php

namespace App\Command;

use App\Entity\Distance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Taniko\Dijkstra\Graph;

class GenerateAllDistancesCommand extends Command
{
    protected static $defaultName = 'GenerateAllDistances';
    protected static $defaultDescription = 'Add a short description for your command';
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(string $name = null,EntityManagerInterface $entityManager)
    {
        parent::__construct($name);
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $conn = $this->entityManager->getConnection();
        $sql = "INSERT INTO distance_all (station_a,station_b,distance) VALUES(:sta,:stb,:dist)";
        $stmt = $conn->prepare($sql);
        $distances = $this->entityManager->getRepository(Distance::class)->findAll();
        $allStations = $this->entityManager->getRepository(Distance::class)->getAllStations();
        $stationsLeft = $allStations;
        $stationsRight = $allStations;
        //$progress = new ProgressBar($output, pow(count($allStations),2)+1);
        $graph = Graph::create();
        foreach ($distances as $d) {
            $graph->add($d->getStationA(), $d->getStationB(), $d->getDistance());
        }
        //$progress->advance();

        foreach($stationsLeft as $sl) {
            foreach($stationsRight as $sr) {
                if($sl['station_a']!=$sr['station_a']) {
                    $tmp[0] = $sl['station_a'];
                    $tmp[1] = $sr['station_a'];
                    $route = $graph->search($tmp[0], $tmp[1]);
                    $tmp[2] = $graph->cost($route);
                    //$io->text($tmp[0].' -> '.$tmp[1].' = '.$tmp[2]);
                    $stmt->execute(['sta'=>$tmp[0], 'stb' => $tmp[1], 'dist' => $tmp[2]]);
                    //$progress->advance();
                } else {
                    //$progress->advance();
                }
            }
        }
        //$progress->finish();



        $io->success('Operacja została wykonana pomyślnie!');

        return 0;
    }
}
