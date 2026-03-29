<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table reservation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reservation (
            id SERIAL NOT NULL,
            vehicle_id INT NOT NULL,
            user_id INT NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            total_price NUMERIC(10, 2) NOT NULL,
            confirmation_token VARCHAR(64) NOT NULL,
            confirmed_at TIMESTAMP DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_42C849559C271E5 ON reservation (confirmation_token)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_RESERVATION_VEHICLE FOREIGN KEY (vehicle_id) REFERENCES vehicle (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_RESERVATION_USER FOREIGN KEY (user_id) REFERENCES "user" (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP CONSTRAINT FK_RESERVATION_VEHICLE');
        $this->addSql('ALTER TABLE reservation DROP CONSTRAINT FK_RESERVATION_USER');
        $this->addSql('DROP TABLE reservation');
    }
}
