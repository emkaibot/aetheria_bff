<?php

declare(strict_types=1);

namespace App\Characters\Domain;

use Doctrine\ORM\Mapping as ORM;

/** Un objeto de la tienda que ya tiene un héroe. Cada objeto, una vez por héroe. */
#[ORM\Entity]
#[ORM\Table(name: 'character_items')]
#[ORM\UniqueConstraint(name: 'uniq_character_items', columns: ['character_id', 'item'])]
class CharacterItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Character::class)]
    #[ORM\JoinColumn(name: 'character_id', nullable: false, onDelete: 'CASCADE')]
    private Character $character;

    #[ORM\Column(type: 'string', length: 32, enumType: Item::class)]
    private Item $item;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, Character $character, Item $item)
    {
        $this->id        = $id;
        $this->character = $character;
        $this->item      = $item;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getItem(): Item { return $this->item; }
}
