<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028230638 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Gremios, raid semanal y sellos de incursión; estrellas de los héroes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE guild_members (id UUID NOT NULL, guild_id UUID NOT NULL, character_id UUID NOT NULL, is_leader BOOLEAN DEFAULT false NOT NULL, joined_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_guild_members_guild ON guild_members (guild_id, joined_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_guild_members_character ON guild_members (character_id)');
        $this->addSql('CREATE INDEX IDX_751A1C605F2131EF ON guild_members (guild_id)');
        $this->addSql('COMMENT ON COLUMN guild_members.joined_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('CREATE TABLE guild_raid_attacks (id UUID NOT NULL, raid_id UUID NOT NULL, character_id UUID NOT NULL, damage INT NOT NULL, seals INT NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_raid_attacks_raid ON guild_raid_attacks (raid_id, character_id, created_at)');
        $this->addSql('CREATE INDEX IDX_67E0D3399C55ABC9 ON guild_raid_attacks (raid_id)');
        $this->addSql('CREATE INDEX IDX_67E0D3391136BE75 ON guild_raid_attacks (character_id)');
        $this->addSql('COMMENT ON COLUMN guild_raid_attacks.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('CREATE TABLE guild_raids (id UUID NOT NULL, guild_id UUID NOT NULL, week VARCHAR(8) NOT NULL, boss VARCHAR(32) NOT NULL, max_hp BIGINT NOT NULL, damage BIGINT DEFAULT 0 NOT NULL, defeated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_guild_raids_week ON guild_raids (guild_id, week)');
        $this->addSql('CREATE INDEX IDX_78BE71175F2131EF ON guild_raids (guild_id)');
        $this->addSql('COMMENT ON COLUMN guild_raids.defeated_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('CREATE TABLE guilds (id UUID NOT NULL, name VARCHAR(24) NOT NULL, name_key VARCHAR(24) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_guilds_name_key ON guilds (name_key)');
        $this->addSql('COMMENT ON COLUMN guilds.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE guild_members ADD CONSTRAINT FK_751A1C605F2131EF FOREIGN KEY (guild_id) REFERENCES guilds (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE guild_members ADD CONSTRAINT FK_751A1C601136BE75 FOREIGN KEY (character_id) REFERENCES characters (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE guild_raid_attacks ADD CONSTRAINT FK_67E0D3399C55ABC9 FOREIGN KEY (raid_id) REFERENCES guild_raids (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE guild_raid_attacks ADD CONSTRAINT FK_67E0D3391136BE75 FOREIGN KEY (character_id) REFERENCES characters (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE guild_raids ADD CONSTRAINT FK_78BE71175F2131EF FOREIGN KEY (guild_id) REFERENCES guilds (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE character_heroes ADD stars INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE characters ADD seals INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE characters ADD CONSTRAINT chk_characters_seals CHECK (seals >= 0)');
        $this->addSql('ALTER TABLE character_heroes ADD CONSTRAINT chk_character_heroes_stars CHECK (stars BETWEEN 1 AND 5)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE guild_raid_attacks');
        $this->addSql('DROP TABLE guild_raids');
        $this->addSql('DROP TABLE guild_members');
        $this->addSql('DROP TABLE guilds');
        $this->addSql('ALTER TABLE character_heroes DROP stars');
        $this->addSql('ALTER TABLE characters DROP seals');
    }
}
