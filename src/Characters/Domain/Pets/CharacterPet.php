<?php

declare(strict_types=1);

namespace App\Characters\Domain\Pets;

use App\Characters\Domain\Character;
use App\Characters\Domain\Progression;
use Doctrine\ORM\Mapping as ORM;

/** Una mascota que ya tiene un personaje, con su EXP y si va con el equipo. */
#[ORM\Entity]
#[ORM\Table(name: 'character_pets')]
#[ORM\UniqueConstraint(name: 'uniq_character_pets', columns: ['character_id', 'pet'])]
class CharacterPet
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Character::class)]
    #[ORM\JoinColumn(name: 'character_id', nullable: false, onDelete: 'CASCADE')]
    private Character $character;

    #[ORM\Column(type: 'string', length: 16, enumType: Pet::class)]
    private Pet $pet;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $exp;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $active;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, Character $character, Pet $pet, int $exp = 0, bool $active = false)
    {
        $this->id        = $id;
        $this->character = $character;
        $this->pet       = $pet;
        $this->exp       = max(0, $exp);
        $this->active    = $active;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getCharacterId(): string { return $this->character->getId(); }
    public function getPet(): Pet            { return $this->pet; }
    public function getExp(): int            { return $this->exp; }
    public function isActive(): bool         { return $this->active; }
    public function level(): int             { return Progression::level($this->exp); }

    /** Suma EXP y devuelve cuántas formas ha cambiado (0 o más: ha evolucionado). */
    public function gainExp(int $amount): int
    {
        $before     = Pet::stage($this->level());
        $this->exp += max(0, $amount);

        return Pet::stage($this->level()) - $before;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}
