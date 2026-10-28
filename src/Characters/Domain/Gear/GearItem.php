<?php

declare(strict_types=1);

namespace App\Characters\Domain\Gear;

use App\Characters\Domain\Character;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un objeto del inventario de un personaje: una pieza del catálogo con su
 * rareza, su nivel y sus atributos ya tirados. Lo lleva puesto el líder
 * (`leader`), un héroe (su id) o nadie.
 */
#[ORM\Entity]
#[ORM\Table(name: 'gear_items')]
#[ORM\Index(name: 'idx_gear_items_character', columns: ['character_id', 'created_at'])]
// Una sola cosa por ranura en cada uno que lleva algo puesto.
#[ORM\UniqueConstraint(name: 'uniq_gear_items_worn', columns: ['character_id', 'equipped_by', 'slot'], options: ['where' => '(equipped_by IS NOT NULL)'])]
class GearItem
{
    public const MAX_UPGRADE = 10;
    /** Cada mejora sube un 8 % todos sus valores. */
    private const UPGRADE_STEP = 0.08;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Character::class)]
    #[ORM\JoinColumn(name: 'character_id', nullable: false, onDelete: 'CASCADE')]
    private Character $character;

    #[ORM\Column(type: 'string', length: 32, enumType: Gear::class)]
    private Gear $gear;

    /** Copia de la ranura de la pieza, para el índice único de lo equipado. */
    #[ORM\Column(type: 'string', length: 16, enumType: Slot::class)]
    private Slot $slot;

    #[ORM\Column(type: 'string', length: 16, enumType: Rarity::class)]
    private Rarity $rarity;

    #[ORM\Column(type: 'integer')]
    private int $level;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $upgrade = 0;

    /** @var array<string, float> atributo => valor, sin las mejoras */
    #[ORM\Column(type: 'json')]
    private array $attributes;

    #[ORM\Column(name: 'equipped_by', type: 'string', length: 16, nullable: true)]
    private ?string $equippedBy = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $locked = false;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @param array<string, float> $attributes */
    public function __construct(string $id, Character $character, Gear $gear, Rarity $rarity, int $level, array $attributes)
    {
        $this->id         = $id;
        $this->character  = $character;
        $this->gear       = $gear;
        $this->slot       = $gear->slot();
        $this->rarity     = $rarity;
        $this->level      = $level;
        $this->attributes = $attributes;
        $this->createdAt  = new \DateTimeImmutable();
    }

    public function getId(): string                    { return $this->id; }
    public function getCharacterId(): string           { return $this->character->getId(); }
    public function getGear(): Gear                    { return $this->gear; }
    public function getSlot(): Slot                    { return $this->slot; }
    public function getRarity(): Rarity               { return $this->rarity; }
    public function getLevel(): int                    { return $this->level; }
    public function getUpgrade(): int                  { return $this->upgrade; }
    public function getEquippedBy(): ?string           { return $this->equippedBy; }
    public function isLocked(): bool                   { return $this->locked; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** Los primeros son los principales de su ranura; el resto, secundarios. @return string[] */
    public function mainKeys(): array
    {
        return array_keys($this->gear->mains(0));
    }

    /** @return array<string, float> atributo => valor con las mejoras, a un decimal */
    public function attributes(): array
    {
        $factor = 1 + self::UPGRADE_STEP * $this->upgrade;

        return array_map(fn (float|int $v) => round($v * $factor, 1), $this->attributes);
    }

    /** Un número para elegir el mejor de una ranura (autoequipar) y comparar. */
    public function power(): int
    {
        $weights = ['atk' => 3, 'def' => 2, 'hp' => 0.25, 'spd' => 1, 'crit' => 4, 'crit_damage' => 1.5, 'lifesteal' => 4, 'healing' => 1, 'dodge' => 4, 'skill' => 2];
        $power   = 0.0;
        foreach ($this->attributes() as $attr => $value) {
            $power += $value * ($weights[$attr] ?? 1);
        }

        return (int) round($power);
    }

    public function sellPrice(): int
    {
        return (int) round($this->rarity->value() * (1 + $this->level / 10));
    }

    /** Oro de la siguiente mejora, o null si ya está al máximo. */
    public function upgradeCost(): ?int
    {
        return $this->upgrade >= self::MAX_UPGRADE ? null : $this->rarity->value() * 2 * ($this->upgrade + 1);
    }

    public function upgrade(): void
    {
        if ($this->upgrade < self::MAX_UPGRADE) {
            $this->upgrade++;
        }
    }

    public function equipTo(?string $wearer): void
    {
        $this->equippedBy = $wearer;
    }

    public function setLocked(bool $locked): void
    {
        $this->locked = $locked;
    }
}
