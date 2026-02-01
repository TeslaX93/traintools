<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260105000637 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE distance_all');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE distance_all (id INT AUTO_INCREMENT NOT NULL, station_a VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'0\' NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, station_b VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'0\' NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, distance DOUBLE PRECISION DEFAULT \'0\' NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
    }
}
