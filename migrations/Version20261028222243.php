<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028222243 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Compañía de héroes de cada personaje, con su EXP y su equipo';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE character_heroes (id UUID NOT NULL, character_id UUID NOT NULL, hero VARCHAR(16) NOT NULL, exp INT DEFAULT 0 NOT NULL, in_party BOOLEAN DEFAULT false NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_character_heroes ON character_heroes (character_id, hero)');
        $this->addSql('CREATE INDEX IDX_F4CE4C541136BE75 ON character_heroes (character_id)');
        $this->addSql('COMMENT ON COLUMN character_heroes.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE character_heroes ADD CONSTRAINT FK_F4CE4C541136BE75 FOREIGN KEY (character_id) REFERENCES characters (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE character_heroes ADD CONSTRAINT chk_character_heroes_exp CHECK (exp >= 0)');

        // Los personajes de antes reciben los héroes iniciales y los de los
        // jefes que ya vencieron, con tanta EXP como éter tiene el líder (la
        // misma curva): así llegan a su nivel y no a lastrar al equipo. Van
        // todos en el equipo menos Elyn, la del dragón, que no cabe (3 + 6 = 9).
        $this->addSql("INSERT INTO character_heroes (id, character_id, hero, exp, in_party, created_at)
            SELECT gen_random_uuid(), c.id, h.hero, c.energy, TRUE, NOW() + h.ord * INTERVAL '1 second'
              FROM characters c
             CROSS JOIN (VALUES ('darius', 0), ('seraphina', 1), ('aelyn', 2)) AS h(hero, ord)");
        $this->addSql("INSERT INTO character_heroes (id, character_id, hero, exp, in_party, created_at)
            SELECT gen_random_uuid(), c.id, h.hero, c.energy, h.hero <> 'elyn', NOW() + h.ord * INTERVAL '1 second'
              FROM characters c
              JOIN (VALUES ('rat_queen', 'sylas', 3), ('goblin', 'thorin', 4), ('skeleton', 'aldric', 5),
                           ('spider', 'morgana', 6), ('golem', 'kairon', 7), ('lich', 'malak', 8), ('dragon', 'elyn', 9))
                   AS h(boss, hero, ord) ON EXISTS (SELECT 1 FROM battles b WHERE b.character_id = c.id AND b.boss_id = h.boss AND b.won)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE character_heroes');
    }
}
