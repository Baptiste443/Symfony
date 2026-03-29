<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250309143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE feature (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE type_vehicle (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE vehicle (id SERIAL NOT NULL, type_vehicle_id INT NOT NULL, name VARCHAR(255) NOT NULL, capacity INT NOT NULL, price NUMERIC(10, 2) NOT NULL, image_path VARCHAR(255), PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_1B80E4864950C1F4 ON vehicle (type_vehicle_id)');
        $this->addSql('CREATE TABLE vehicle_feature (vehicle_id INT NOT NULL, feature_id INT NOT NULL, PRIMARY KEY(vehicle_id, feature_id))');
        $this->addSql('CREATE INDEX IDX_A42809545317D1 ON vehicle_feature (vehicle_id)');
        $this->addSql('CREATE INDEX IDX_A4280960E4B879 ON vehicle_feature (feature_id)');
        $this->addSql(<<<SQL
            CREATE TABLE messenger_messages (
                id BIGSERIAL NOT NULL,
                body TEXT NOT NULL,
                headers TEXT NOT NULL,
                queue_name VARCHAR(190) NOT NULL,
                created_at TIMESTAMP NOT NULL,
                available_at TIMESTAMP NOT NULL,
                delivered_at TIMESTAMP DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('ALTER TABLE vehicle ADD CONSTRAINT FK_1B80E4864950C1F4 FOREIGN KEY (type_vehicle_id) REFERENCES type_vehicle (id)');
        $this->addSql('ALTER TABLE vehicle_feature ADD CONSTRAINT FK_A42809545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicle (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicle_feature ADD CONSTRAINT FK_A4280960E4B879 FOREIGN KEY (feature_id) REFERENCES feature (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vehicle DROP CONSTRAINT FK_1B80E4864950C1F4');
        $this->addSql('ALTER TABLE vehicle_feature DROP CONSTRAINT FK_A42809545317D1');
        $this->addSql('ALTER TABLE vehicle_feature DROP CONSTRAINT FK_A4280960E4B879');
        $this->addSql('DROP TABLE feature');
        $this->addSql('DROP TABLE type_vehicle');
        $this->addSql('DROP TABLE vehicle');
        $this->addSql('DROP TABLE vehicle_feature');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
