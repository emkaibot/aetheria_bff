<?php

declare(strict_types=1);

namespace App\Characters\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Un combate contra un jefe: quién ganó y el oro que dio. De aquí salen las
 * victorias y derrotas de un héroe y qué jefes tiene desbloqueados.
 */
#[ORM\Entity]
#[ORM\Table(name: 'battles')]
#[ORM\Index(name: 'idx_battles_character_boss', columns: ['character_id', 'boss_id'])]
class BattleRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Character::class)]
    #[ORM\JoinColumn(name: 'character_id', nullable: false, onDelete: 'CASCADE')]
    private Character $character;

    #[ORM\Column(name: 'boss_id', type: 'string', length: 32)]
    private string $bossId;

    #[ORM\Column(type: 'boolean')]
    private bool $won;

    #[ORM\Column(type: 'integer')]
    private int $rounds;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $gold;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, Character $character, string $bossId, bool $won, int $rounds, int $gold)
    {
        $this->id        = $id;
        $this->character = $character;
        $this->bossId    = $bossId;
        $this->won       = $won;
        $this->rounds    = $rounds;
        $this->gold      = $gold;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getGold(): int { return $this->gold; }
}
