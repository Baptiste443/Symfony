<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table user avec les champs personnels (firstName, lastName, address, phone)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "user" (
            id SERIAL NOT NULL,
            email VARCHAR(180) NOT NULL,
            roles JSON NOT NULL,
            password VARCHAR(255) NOT NULL,
            is_verified BOOLEAN NOT NULL DEFAULT FALSE,
            first_name VARCHAR(100) DEFAULT NULL,
            last_name VARCHAR(100) DEFAULT NULL,
            address VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "user" (email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE "user"');
    }
}
