<?php

declare(strict_types=1);

namespace App\Characters\Domain\Guilds;

use App\Characters\Domain\Character;
use Doctrine\ORM\Mapping as ORM;

/** Un ataque de un personaje a la raid de su gremio: el daño que hizo y los sellos que le dio. */
#[ORM\Entity]
#[ORM\Table(name: 'guild_raid_attacks')]
#[ORM\Index(name: 'idx_raid_attacks_raid', columns: ['raid_id', 'character_id', 'created_at'])]
class RaidAttack
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Raid::class)]
    #[ORM\JoinColumn(name: 'raid_id', nullable: false, onDelete: 'CASCADE')]
    private Raid $raid;

    #[ORM\ManyToOne(targetEntity: Character::class)]
    #[ORM\JoinColumn(name: 'character_id', nullable: false, onDelete: 'CASCADE')]
    private Character $character;

    #[ORM\Column(type: 'integer')]
    private int $damage;

    #[ORM\Column(type: 'integer')]
    private int $seals;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, Raid $raid, Character $character, int $damage, int $seals, \DateTimeImmutable $at)
    {
        $this->id        = $id;
        $this->raid      = $raid;
        $this->character = $character;
        $this->damage    = $damage;
        $this->seals     = $seals;
        $this->createdAt = $at;
    }
}
