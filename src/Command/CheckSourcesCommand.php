<?php

namespace App\Command;

use App\Helper\BilkomHelper;
use App\Helper\Constants;
use App\Service\HtmlFetcher;
use DateTime;
use DateTimeZone;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Sprawdza, czy parser Bilkomu nadal rozumie stronę źródłową.
 *
 * Serwis stoi na czytaniu cudzego HTML-a po położeniu elementów, więc każdy
 * redesign po tamtej stronie potrafi go zepsuć po cichu. Ta komenda jest po to,
 * żeby dowiedzieć się o tym z maila, a nie od użytkownika.
 *
 * Przeznaczona do crona co 6 godzin:
 *
 *     0 star/6 star star star  php /sciezka/bin/console app:check-sources --env=prod
 *
 * Mail wychodzi wyłącznie przy wykrytej awarii.
 */
#[AsCommand(
    name: 'app:check-sources',
    description: 'Sprawdza, czy parser Bilkomu nadal dziala, i wysyla maila gdy cos jest nie tak',
)]
class CheckSourcesCommand extends Command
{
    /** Wrocław Główny - duża stacja, o każdej porze ma ruch. */
    private const PROBE_STATION = '5100069';

    /** Poniżej tylu pociągów na tablicy uznajemy, że coś jest nie tak. */
    private const MIN_TRAINS = 1;

    /** Sonda ma widzieć bieżący stan, nie odpowiedź sprzed pół godziny. */
    private const TTL = 0;

    public function __construct(
        private readonly HtmlFetcher $fetcher,
        private readonly MailerInterface $mailer,
        private readonly ?string $alertEmail,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nie wysylaj maila, tylko pokaz wynik');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $problems = $this->checkBilkom();

        if ($problems === []) {
            $io->success('Bilkom: parser dziala poprawnie.');

            return Command::SUCCESS;
        }

        $io->error('Wykryto problemy:');
        $io->listing($problems);

        if ($input->getOption('dry-run')) {
            $io->note('Tryb dry-run - mail nie zostal wyslany.');

            return Command::FAILURE;
        }

        if (!$this->alertEmail) {
            $io->warning('Nie ustawiono ALERT_EMAIL - mail nie zostal wyslany.');

            return Command::FAILURE;
        }

        try {
            $this->mailer->send($this->buildEmail($problems));
            $io->note(sprintf('Alert wyslany na %s.', $this->alertEmail));
        } catch (TransportExceptionInterface $e) {
            $io->error('Nie udalo sie wyslac alertu: ' . $e->getMessage());
        }

        return Command::FAILURE;
    }

    /**
     * @return list<string> lista problemow, pusta gdy wszystko dziala
     */
    private function checkBilkom(): array
    {
        $now = (new DateTime('now', new DateTimeZone(Constants::TIMEZONE)))->format('dmYHi');
        $url = BilkomHelper::generateBilkomUrl(self::PROBE_STATION, $now, 'false');

        $html = $this->fetcher->fetch($url, self::TTL);
        if ($html === null) {
            return ['Nie udalo sie pobrac strony ' . $url];
        }

        $crawler = new Crawler($html);

        if ($crawler->filter('#fromStation')->count() === 0) {
            return ['Strona nie zawiera pola #fromStation - uklad zrodla prawdopodobnie sie zmienil.'];
        }

        if ($crawler->filter('ul#timetable')->count() === 0) {
            return ['Strona nie zawiera listy ul#timetable - uklad zrodla prawdopodobnie sie zmienil.'];
        }

        $rows = (new Crawler($crawler->filter('ul#timetable')->html()))
            ->filter('.el')
            ->each(fn ($el) => $el->filter('div')->each(fn ($div) => trim($div->html())));

        if (count($rows) < self::MIN_TRAINS) {
            return [sprintf('Tablica jest pusta (%d pociagow) - selektor .el moze juz nie pasowac.', count($rows))];
        }

        // Sam fakt, ze wiersze istnieja, nie wystarczy - sprawdzamy czy da sie
        // z nich wyciagnac sensowne dane. To wlasnie tu widac przesuniete kolumny.
        $problems = [];
        $train = BilkomHelper::basicTrainAnalysis($rows[0]);

        if (trim((string) $train['trainCode']) === '') {
            $problems[] = 'Pierwszy pociag nie ma numeru - komorka z kodem pociagu moze byc przesunieta.';
        }

        if ((int) $train['timestamp'] <= 0) {
            $problems[] = 'Pierwszy pociag nie ma poprawnej godziny - komorka ze znacznikiem czasu moze byc przesunieta.';
        }

        if (trim((string) $train['arrivalStation']) === '') {
            $problems[] = 'Pierwszy pociag nie ma stacji docelowej - komorka z relacja moze byc przesunieta.';
        }

        return $problems;
    }

    /**
     * @param list<string> $problems
     */
    private function buildEmail(array $problems): Email
    {
        $tresc = "Automatyczna kontrola zrodel danych wykryla problem.\n\n"
            . "Serwis: bilkom.pl\n"
            . 'Stacja testowa: ' . self::PROBE_STATION . "\n"
            . 'Czas kontroli: ' . (new DateTime('now', new DateTimeZone(Constants::TIMEZONE)))->format('Y-m-d H:i') . "\n\n"
            . "Wykryte problemy:\n"
            . implode("\n", array_map(static fn (string $p): string => ' - ' . $p, $problems))
            . "\n\nJesli uklad strony zrodlowej sie zmienil, numery komorek poprawia sie\n"
            . "w src/Helper/BilkomBoardRow.php oraz src/Helper/BilkomTripRow.php.\n";

        return (new Email())
            ->from($this->alertEmail)
            ->to($this->alertEmail)
            ->subject('[KalkulatorKolejowy] Parser Bilkomu nie dziala poprawnie')
            ->text($tresc);
    }
}
