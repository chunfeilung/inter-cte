<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230729123032 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create GTFS and routing tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE agency (id INT AUTO_INCREMENT NOT NULL, external_id VARCHAR(20) NOT NULL, name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_70C0C6E69F75D7B0 (external_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE edge (id INT AUTO_INCREMENT NOT NULL, from_node_id INT NOT NULL, to_node_id INT NOT NULL, distance INT NOT NULL, stops INT NOT NULL, INDEX IDX_7506D366C0537C78 (from_node_id), INDEX IDX_7506D366C895A222 (to_node_id), INDEX stops_idx (from_node_id, stops), INDEX distance_idx (from_node_id, distance), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE node (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_857FE8455E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE route (id INT AUTO_INCREMENT NOT NULL, agency_id INT NOT NULL, external_id VARCHAR(20) NOT NULL, short_name VARCHAR(255) NOT NULL, long_name VARCHAR(255) NOT NULL, type INT NOT NULL, INDEX IDX_2C42079CDEADB2A (agency_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE stop (id INT AUTO_INCREMENT NOT NULL, node_id INT DEFAULT NULL, external_id VARCHAR(20) NOT NULL, name VARCHAR(255) NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, parent VARCHAR(20) DEFAULT NULL, platform VARCHAR(40) DEFAULT NULL, UNIQUE INDEX UNIQ_B95616B69F75D7B0 (external_id), INDEX IDX_B95616B6460D9FD7 (node_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE stop_time (id INT AUTO_INCREMENT NOT NULL, trip_id INT NOT NULL, stop_id INT DEFAULT NULL, stop_sequence INT NOT NULL, stop_headsign VARCHAR(255) DEFAULT NULL, arrival_time DATETIME DEFAULT NULL, departure_time DATETIME DEFAULT NULL, shape_dist_traveled DOUBLE PRECISION NOT NULL, INDEX IDX_85725A5AA5BC2E0E (trip_id), INDEX IDX_85725A5A3902063D (stop_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE transfer (id INT AUTO_INCREMENT NOT NULL, from_stop_id INT NOT NULL, to_stop_id INT NOT NULL, min_transfer_time INT NOT NULL, INDEX IDX_4034A3C0BF5CE592 (from_stop_id), INDEX IDX_4034A3C0B79A3BC8 (to_stop_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE trip (id INT AUTO_INCREMENT NOT NULL, route_id INT NOT NULL, external_id INT NOT NULL, headsign VARCHAR(255) DEFAULT NULL, short_name VARCHAR(255) DEFAULT NULL, long_name VARCHAR(255) DEFAULT NULL, INDEX IDX_7656F53B34ECB4E6 (route_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE edge ADD CONSTRAINT FK_7506D366C0537C78 FOREIGN KEY (from_node_id) REFERENCES node (id)');
        $this->addSql('ALTER TABLE edge ADD CONSTRAINT FK_7506D366C895A222 FOREIGN KEY (to_node_id) REFERENCES node (id)');
        $this->addSql('ALTER TABLE route ADD CONSTRAINT FK_2C42079CDEADB2A FOREIGN KEY (agency_id) REFERENCES agency (id)');
        $this->addSql('ALTER TABLE stop ADD CONSTRAINT FK_B95616B6460D9FD7 FOREIGN KEY (node_id) REFERENCES node (id)');
        $this->addSql('ALTER TABLE stop_time ADD CONSTRAINT FK_85725A5AA5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id)');
        $this->addSql('ALTER TABLE stop_time ADD CONSTRAINT FK_85725A5A3902063D FOREIGN KEY (stop_id) REFERENCES stop (id)');
        $this->addSql('ALTER TABLE transfer ADD CONSTRAINT FK_4034A3C0BF5CE592 FOREIGN KEY (from_stop_id) REFERENCES stop (id)');
        $this->addSql('ALTER TABLE transfer ADD CONSTRAINT FK_4034A3C0B79A3BC8 FOREIGN KEY (to_stop_id) REFERENCES stop (id)');
        $this->addSql('ALTER TABLE trip ADD CONSTRAINT FK_7656F53B34ECB4E6 FOREIGN KEY (route_id) REFERENCES route (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE edge DROP FOREIGN KEY FK_7506D366C0537C78');
        $this->addSql('ALTER TABLE edge DROP FOREIGN KEY FK_7506D366C895A222');
        $this->addSql('ALTER TABLE route DROP FOREIGN KEY FK_2C42079CDEADB2A');
        $this->addSql('ALTER TABLE stop DROP FOREIGN KEY FK_B95616B6460D9FD7');
        $this->addSql('ALTER TABLE stop_time DROP FOREIGN KEY FK_85725A5AA5BC2E0E');
        $this->addSql('ALTER TABLE stop_time DROP FOREIGN KEY FK_85725A5A3902063D');
        $this->addSql('ALTER TABLE transfer DROP FOREIGN KEY FK_4034A3C0BF5CE592');
        $this->addSql('ALTER TABLE transfer DROP FOREIGN KEY FK_4034A3C0B79A3BC8');
        $this->addSql('ALTER TABLE trip DROP FOREIGN KEY FK_7656F53B34ECB4E6');
        $this->addSql('DROP TABLE agency');
        $this->addSql('DROP TABLE edge');
        $this->addSql('DROP TABLE node');
        $this->addSql('DROP TABLE route');
        $this->addSql('DROP TABLE stop');
        $this->addSql('DROP TABLE stop_time');
        $this->addSql('DROP TABLE transfer');
        $this->addSql('DROP TABLE trip');
    }
}
