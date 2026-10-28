<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028224418 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Equipamiento (gear_items), mascotas (character_pets) y expediciones AFK';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE character_pets (id UUID NOT NULL, character_id UUID NOT NULL, pet VARCHAR(16) NOT NULL, exp INT DEFAULT 0 NOT NULL, active BOOLEAN DEFAULT false NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_character_pets ON character_pets (character_id, pet)');
        $this->addSql('CREATE INDEX IDX_91C4FEA71136BE75 ON character_pets (character_id)');
        $this->addSql('COMMENT ON COLUMN character_pets.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('CREATE TABLE expeditions (id UUID NOT NULL, character_id UUID NOT NULL, zone VARCHAR(16) NOT NULL, difficulty VARCHAR(16) NOT NULL, limit_hours INT NOT NULL, autosell VARCHAR(16) DEFAULT NULL, started_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, claimed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, summary JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_expeditions_character ON expeditions (character_id, started_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_expeditions_active ON expeditions (character_id) WHERE (claimed_at IS NULL)');
        $this->addSql('CREATE INDEX IDX_ACB501A01136BE75 ON expeditions (character_id)');
        $this->addSql('COMMENT ON COLUMN expeditions.started_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN expeditions.claimed_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('CREATE TABLE gear_items (id UUID NOT NULL, character_id UUID NOT NULL, gear VARCHAR(32) NOT NULL, slot VARCHAR(16) NOT NULL, rarity VARCHAR(16) NOT NULL, level INT NOT NULL, upgrade INT DEFAULT 0 NOT NULL, attributes JSON NOT NULL, equipped_by VARCHAR(16) DEFAULT NULL, locked BOOLEAN DEFAULT false NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_gear_items_character ON gear_items (character_id, created_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_gear_items_worn ON gear_items (character_id, equipped_by, slot) WHERE (equipped_by IS NOT NULL)');
        $this->addSql('CREATE INDEX IDX_7B1776C11136BE75 ON gear_items (character_id)');
        $this->addSql('COMMENT ON COLUMN gear_items.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE character_pets ADD CONSTRAINT FK_91C4FEA71136BE75 FOREIGN KEY (character_id) REFERENCES characters (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE expeditions ADD CONSTRAINT FK_ACB501A01136BE75 FOREIGN KEY (character_id) REFERENCES characters (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE gear_items ADD CONSTRAINT FK_7B1776C11136BE75 FOREIGN KEY (character_id) REFERENCES characters (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE character_pets ADD CONSTRAINT chk_character_pets_exp CHECK (exp >= 0)');
        $this->addSql('ALTER TABLE gear_items ADD CONSTRAINT chk_gear_items_upgrade CHECK (upgrade BETWEEN 0 AND 10)');

        // Los personajes de antes reciben a Azurion, la de serie, y las
        // mascotas de los jefes que ya vencieron, con la EXP del éter del
        // líder (la misma curva, como sus héroes). Activas las tres primeras.
        $this->addSql("INSERT INTO character_pets (id, character_id, pet, exp, active, created_at)
            SELECT gen_random_uuid(), c.id, p.pet, c.energy, p.ord < 3, NOW() + p.ord * INTERVAL '1 second'
              FROM characters c
              JOIN (VALUES (NULL, 'azurion', 0), ('skeleton', 'lunaris', 1), ('spider', 'solaria', 2),
                           ('golem', 'bastion', 3), ('lich', 'noctua', 4), ('dragon', 'umbra', 5))
                   AS p(boss, pet, ord)
                ON p.boss IS NULL
                OR EXISTS (SELECT 1 FROM battles b WHERE b.character_id = c.id AND b.boss_id = p.boss AND b.won)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE gear_items');
        $this->addSql('DROP TABLE expeditions');
        $this->addSql('DROP TABLE character_pets');
    }
}
