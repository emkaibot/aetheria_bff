<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028230150 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fuera la tienda antigua del líder: se devuelve el oro de lo comprado';
    }

    public function up(Schema $schema): void
    {
        // Lo que se pagó por cada objeto vuelve al personaje, con los precios
        // que tenía la tienda.
        $this->addSql("UPDATE characters c
               SET gold = gold + r.refund
              FROM (SELECT character_id, SUM(CASE item
                        WHEN 'iron_sword' THEN 60 WHEN 'chain_mail' THEN 60
                        WHEN 'life_amulet' THEN 90 WHEN 'wind_boots' THEN 90
                        WHEN 'arcane_tome' THEN 250 WHEN 'dragon_aegis' THEN 400
                        WHEN 'fallen_crown' THEN 800 ELSE 0 END) AS refund
                      FROM character_items GROUP BY character_id) r
             WHERE r.character_id = c.id");
        $this->addSql('DROP TABLE character_items');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('La tienda antigua no vuelve: su oro ya se devolvió.');
    }
}
