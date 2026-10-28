<?php

declare(strict_types=1);

namespace App\Characters\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Un héroe que ya tiene un personaje, con su EXP y si va en el equipo. Cada
 * héroe, una vez por personaje.
 */
#[ORM\Entity]
#[ORM\Table(name: 'character_heroes')]
#[ORM\UniqueConstraint(name: 'uniq_character_heroes', columns: ['character_id', 'hero'])]
class CharacterHero
{
    /** Héroes en el equipo además del líder: diez en combate contando con él. */
    public const PARTY_MAX = 9;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Character::class)]
    #[ORM\JoinColumn(name: 'character_id', nullable: false, onDelete: 'CASCADE')]
    private Character $character;

    #[ORM\Column(type: 'string', length: 16, enumType: Hero::class)]
    private Hero $hero;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $exp;

    #[ORM\Column(name: 'in_party', type: 'boolean', options: ['default' => false])]
    private bool $inParty;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, Character $character, Hero $hero, int $exp = 0, bool $inParty = false)
    {
        $this->id        = $id;
        $this->character = $character;
        $this->hero      = $hero;
        $this->exp       = max(0, $exp);
        $this->inParty   = $inParty;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getCharacterId(): string { return $this->character->getId(); }
    public function getHero(): Hero      { return $this->hero; }
    public function getExp(): int        { return $this->exp; }
    public function isInParty(): bool    { return $this->inParty; }
    public function level(): int         { return Progression::level($this->exp); }
    public function stats(): Stats       { return Progression::heroStats($this->hero, $this->exp); }

    /** Suma EXP y devuelve cuántos niveles ha subido. */
    public function gainExp(int $amount): int
    {
        $before     = $this->level();
        $this->exp += max(0, $amount);

        return $this->level() - $before;
    }

    public function setInParty(bool $inParty): void
    {
        $this->inParty = $inParty;
    }
}
