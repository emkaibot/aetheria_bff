<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012111450 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Personajes y su histórico de éter';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE characters (id UUID NOT NULL, owner_id VARCHAR(64) NOT NULL, name VARCHAR(24) NOT NULL, class VARCHAR(16) NOT NULL, energy INT DEFAULT 0 NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_characters_owner ON characters (owner_id)');
        $this->addSql('COMMENT ON COLUMN characters.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN characters.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('CREATE TABLE energy_movements (id UUID NOT NULL, character_id UUID NOT NULL, amount INT NOT NULL, balance_after INT NOT NULL, kind VARCHAR(16) NOT NULL, note VARCHAR(140) DEFAULT NULL, boss_id VARCHAR(32) DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_energy_movements_character ON energy_movements (character_id, created_at)');
        $this->addSql('CREATE INDEX IDX_9DA83B621136BE75 ON energy_movements (character_id)');
        $this->addSql('COMMENT ON COLUMN energy_movements.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE energy_movements ADD CONSTRAINT FK_9DA83B621136BE75 FOREIGN KEY (character_id) REFERENCES characters (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        // Red por debajo de Character::applyEnergy: un saldo negativo no
        // debería poder escribirse ni por un bug ni a mano desde Adminer.
        $this->addSql('ALTER TABLE characters ADD CONSTRAINT chk_characters_energy CHECK (energy >= 0)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE energy_movements DROP CONSTRAINT FK_9DA83B621136BE75');
        $this->addSql('DROP TABLE characters');
        $this->addSql('DROP TABLE energy_movements');
    }
}
