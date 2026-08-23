# KalkulatorKolejowy.pl

Zestaw narzędzi dla miłośników kolei, działający pod adresem
[kalkulatorkolejowy.pl](https://kalkulatorkolejowy.pl).

## Co tu jest

| Moduł | Adres | Opis |
|---|---|---|
| Odległości między stacjami | `/distance` | Najkrótsza trasa po sieci PKP, także przez wskazane stacje pośrednie. Algorytm Dijkstry na ~3200 krawędziach. |
| Mapa stacji | `/mapa` | Blisko 3000 stacji i przystanków na mapie OpenStreetMap, z wyszukiwarką. |
| Wyświetlacze stacyjne PLK | `/panels` | Skrót do tablicy odjazdów dowolnej stacji w Portalu Pasażera. |
| Generator tablic relacyjnych | `/ztr` | Tablica w stylu PKP Intercity albo Kolei Śląskich. |
| API | `/bilkom` | Odjazdy, przyjazdy i opóźnienia oraz katalogi stacji w formacie JSON. Dokumentacja w standardzie OpenAPI 3.1, z możliwością wywołania endpointów wprost ze strony. |
| Losowa stacja | `/distance/random` | Losowanie stacji, głównie dla zabawy. |

## Wymagania

- PHP 8.4+ z rozszerzeniami `ctype`, `iconv`, `json`
- MySQL lub MariaDB
- Composer

## Uruchomienie

```bash
composer install
```

Skonfiguruj połączenie z bazą w `.env.local` (ten plik nie trafia do repozytorium):

```dotenv
DATABASE_URL="mysql://uzytkownik:haslo@127.0.0.1:3306/traintools"
APP_ENV=prod
APP_SECRET=wygeneruj_wlasny
```

Następnie struktura bazy i dane:

```bash
php bin/console doctrine:migrations:migrate
php bin/console DownloadLatestDistances
php bin/console CrawlStations
```

Serwer deweloperski:

```bash
php -S 127.0.0.1:8000 -t public
```

## Komendy

| Komenda | Co robi |
|---|---|
| `DownloadLatestDistances` | Pobiera odległości z [pkp-distances](https://github.com/TeslaX93/pkp-distances) i wymienia zawartość tabeli `distance`. Pobranie i sprawdzenie pliku dzieje się **przed** dotknięciem bazy, a sama wymiana idzie w transakcji. Na koniec czyści cache grafu. |
| `CrawlStations` | Przechodzi katalog stacji Portalu Pasażera i zapisuje nazwy, adresy i współrzędne. Ponowne uruchomienie **dodaje nowe stacje i poprawia zmienione, nie usuwa żadnej**. Opcja `--truncate` czyści tabelę przed startem, ale trzeba o nią poprosić wprost. |
| `app:check-sources` | Sprawdza, czy parser Bilkomu nadal rozumie stronę źródłową. Przy problemie wysyła maila. Opcja `--dry-run` pokazuje wynik bez wysyłki. |
| `BilkomDelay` | Podgląd tablicy odjazdów z wiersza poleceń. Przydatne przy diagnozowaniu parsera. |

### Zadania cykliczne

```cron
# kontrola parserów co 6 godzin - mail wychodzi tylko przy awarii
0 */6 * * *  php /sciezka/do/projektu/bin/console app:check-sources --env=prod

# odświeżenie katalogu stacji, raz w miesiącu wystarczy
0 4 1 * *    php /sciezka/do/projektu/bin/console CrawlStations --env=prod
```

Adres alertów ustawia zmienna `ALERT_EMAIL`, a wysyłkę `MAILER_DSN`. Obie są w
`.env` celowo puste — to repozytorium jest publiczne, więc adres wpisany tam
trafiłby prosto do zbieraczy adresów. Ustaw je w `.env.local` na serwerze:

```dotenv
ALERT_EMAIL=twoj@adres.pl
MAILER_DSN=smtp://uzytkownik:haslo@serwer:587
```

Bez `ALERT_EMAIL` komenda wykona kontrolę i wypisze wynik, ale nie wyśle maila.

## API

Dokumentacja: [`/bilkom`](https://kalkulatorkolejowy.pl/bilkom) (Swagger UI),
specyfikacja maszynowa: [`public/openapi.yaml`](public/openapi.yaml).

Endpointy wysyłają nagłówek `Access-Control-Allow-Origin: *`, więc można je
odpytywać także z cudzej strony w przeglądarce.

Trzy publiczne endpointy:

```bash
# tablica odjazdow z opoznieniami
curl https://kalkulatorkolejowy.pl/bilkom/api/nextdeparture/basic/5100069

# nazwy stacji znane kalkulatorowi odleglosci
curl https://kalkulatorkolejowy.pl/distance/api/stations

# katalog stacji ze wspolrzednymi
curl https://kalkulatorkolejowy.pl/mapa/stacje.json
```

Endpointy `/infopasazer/*` są **wycofane** — serwis infopasazer.intercity.pl
przestał istnieć i te trasy zwracają 410 Gone.

## Testy

```bash
php vendor/bin/phpunit
```

Testy korzystające z bazy pomijają się same, gdy baza jest nieosiągalna, więc
zestaw przechodzi także na świeżym klonie repozytorium.

## O co warto zahaczyć przy zmianach

**Serwis stoi na cudzym HTML-u.** Bilkom nie ma API, więc dane powstają z analizy
strony — a elementy czytane są po ich położeniu. Cała wiedza o tym, co gdzie
leży, jest w dwóch plikach i tylko tam poprawia się numery, gdy źródło zmieni
układ:

- [`src/Helper/BilkomBoardRow.php`](src/Helper/BilkomBoardRow.php) — wiersz tablicy odjazdów
- [`src/Helper/BilkomTripRow.php`](src/Helper/BilkomTripRow.php) — przystanek na trasie pociągu

Komenda `app:check-sources` istnieje właśnie po to, żeby wyłapać taką zmianę
zanim zrobią to użytkownicy.

**Zapytania na zewnątrz idą przez jeden serwis.**
[`HtmlFetcher`](src/Service/HtmlFetcher.php) pilnuje timeoutu, cache'uje
odpowiedzi i pobiera wiele adresów równolegle. Nie używaj `file_get_contents`
bezpośrednio — bez timeoutu potrafi zablokować proces PHP na minutę.

**Ciężkie rzeczy są cache'owane.** Graf odległości i lista stacji leżą w
`cache.app` ([`DistanceGraphProvider`](src/Service/DistanceGraphProvider.php)),
bo budowanie grafu od zera kosztuje ~18 ms przy każdym żądaniu. Cache czyści się
sam po imporcie nowych odległości.

**Strefa czasowa jest ustawiona jawnie** (`Constants::TIMEZONE`), bo rozkłady są
w czasie polskim, a `php.ini` na serwerze bywa ustawiony inaczej niż lokalnie.

## Źródła danych

- Odległości: [pkp-distances](https://github.com/TeslaX93/pkp-distances)
- Katalog stacji: [Portal Pasażera](https://portalpasazera.pl)
- Odjazdy i opóźnienia: [bilkom.pl](https://bilkom.pl)
- Podkład mapy: [OpenStreetMap](https://www.openstreetmap.org/copyright)

Żadne z tych źródeł nie udostępnia oficjalnego API dla tych danych — serwis
korzysta z nich na zasadzie analizy publicznie dostępnych stron.
