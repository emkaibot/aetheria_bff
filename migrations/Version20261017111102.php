<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261017111102 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Oro, tienda de equipo y registro de combates (los combates ya no dan éter)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE battles (id UUID NOT NULL, character_id UUID NOT NULL, boss_id VARCHAR(32) NOT NULL, won BOOLEAN NOT NULL, rounds INT NOT NULL, gold INT DEFAULT 0 NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_battles_character_boss ON battles (character_id, boss_id)');
        $this->addSql('CREATE INDEX IDX_3AA9A2A91136BE75 ON battles (character_id)');
        $this->addSql('COMMENT ON COLUMN battles.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('CREATE TABLE character_items (id UUID NOT NULL, character_id UUID NOT NULL, item VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_character_items ON character_items (character_id, item)');
        $this->addSql('CREATE INDEX IDX_1FDD0E2F1136BE75 ON character_items (character_id)');
        $this->addSql('COMMENT ON COLUMN character_items.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE battles ADD CONSTRAINT FK_3AA9A2A91136BE75 FOREIGN KEY (character_id) REFERENCES characters (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE character_items ADD CONSTRAINT FK_1FDD0E2F1136BE75 FOREIGN KEY (character_id) REFERENCES characters (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE characters ADD gold INT DEFAULT 0 NOT NULL');

        // Los combates de antes vivían como movimientos de éter (victoria o
        // derrota contra un jefe). Pasan a la tabla nueva para que nadie pierda
        // los jefes que ya tenía desbloqueados. No dieron oro.
        $this->addSql("INSERT INTO battles (id, character_id, boss_id, won, rounds, gold, created_at)
            SELECT id, character_id, boss_id, kind = 'victory', 0, 0, created_at
              FROM energy_movements
             WHERE kind IN ('victory', 'defeat') AND boss_id IS NOT NULL");

        $this->addSql('ALTER TABLE characters ADD CONSTRAINT chk_characters_gold CHECK (gold >= 0)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE battles DROP CONSTRAINT FK_3AA9A2A91136BE75');
        $this->addSql('ALTER TABLE character_items DROP CONSTRAINT FK_1FDD0E2F1136BE75');
        $this->addSql('DROP TABLE battles');
        $this->addSql('DROP TABLE character_items');
        $this->addSql('ALTER TABLE characters DROP gold');
    }
}
