<?php

namespace App\Tests;

use Doctrine\DBAL\Connection;

/**
 * Testy dzielą bazę z aplikacją, więc każde wywołanie endpointu API w teście
 * dopisuje fikcyjny wiersz do prawdziwych statystyk. Ta cecha pozwala usunąć
 * dokładnie te wiersze, które powstały w trakcie testu.
 *
 * Docelowo lepszym rozwiązaniem jest osobna baza testowa — wtedy nie trzeba
 * niczego sprzątać. Do tego czasu wystarczy to.
 */
trait CzysciStatystykiApi
{
    private int $apiUsageOstatnieId = 0;

    private function zapamietajStanStatystyk(Connection $connection): void
    {
        try {
            $this->apiUsageOstatnieId = (int) $connection
                ->executeQuery('SELECT COALESCE(MAX(id), 0) FROM api_usage')
                ->fetchOne();
        } catch (\Throwable) {
            // brak tabeli albo bazy - nie ma czego sprzatac
            $this->apiUsageOstatnieId = -1;
        }
    }

    private function usunStatystykiZTestu(Connection $connection): void
    {
        if ($this->apiUsageOstatnieId < 0) {
            return;
        }

        try {
            $connection->executeStatement(
                'DELETE FROM api_usage WHERE id > :id',
                ['id' => $this->apiUsageOstatnieId]
            );
        } catch (\Throwable) {
            // sprzatanie nie moze wywracac testu
        }
    }
}
