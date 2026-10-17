<?php

declare(strict_types=1);

namespace App\Characters\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Un personaje de una cuenta. `owner_id` es el `sub` de Keycloak.
 *
 * `energy` es el saldo de éter y **sólo cambia junto a un movimiento** (ver
 * EnergyLedger): el histórico es la verdad y la columna es su suma, guardada
 * para no tener que recalcularla en cada listado. Cada movimiento lleva el
 * saldo resultante, así que un descuadre se vería en la propia tabla.
 */
#[ORM\Entity]
#[ORM\Table(name: 'characters')]
#[ORM\Index(name: 'idx_characters_owner', columns: ['owner_id'])]
class Character
{
    public const NAME_MIN = 2;
    public const NAME_MAX = 24;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(name: 'owner_id', type: 'string', length: 64)]
    private string $ownerId;

    #[ORM\Column(type: 'string', length: self::NAME_MAX)]
    private string $name;

    #[ORM\Column(name: 'class', type: 'string', length: 16, enumType: CharacterClass::class)]
    private CharacterClass $class;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $energy = 0;

    /** Oro: se gana venciendo jefes y se gasta en la tienda. No tiene histórico. */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $gold = 0;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $id, string $ownerId, string $name, CharacterClass $class)
    {
        $this->id        = $id;
        $this->ownerId   = $ownerId;
        $this->name      = self::validName($name);
        $this->class     = $class;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): string                    { return $this->id; }
    public function getOwnerId(): string               { return $this->ownerId; }
    public function getName(): string                  { return $this->name; }
    public function getClass(): CharacterClass         { return $this->class; }
    public function getEnergy(): int                   { return $this->energy; }
    public function getGold(): int                     { return $this->gold; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function isOwnedBy(string $ownerId): bool
    {
        return $this->ownerId === $ownerId;
    }

    public function rename(string $name): void
    {
        $this->name      = self::validName($name);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function changeClass(CharacterClass $class): void
    {
        $this->class     = $class;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Suma (o resta) éter y devuelve el saldo nuevo. Nunca baja de cero: quitar
     * más de lo que hay es un error, no un saldo negativo.
     *
     * Sólo lo llama EnergyLedger, que es quien deja el movimiento escrito.
     */
    public function applyEnergy(int $delta): int
    {
        if ($this->energy + $delta < 0) {
            throw new InsufficientEnergy($this->energy, -$delta);
        }

        $this->energy   += $delta;
        $this->updatedAt = new \DateTimeImmutable();

        return $this->energy;
    }

    public function earnGold(int $amount): void
    {
        $this->gold     += max(0, $amount);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function spendGold(int $amount): void
    {
        if ($amount > $this->gold) {
            throw new NotEnoughGold($this->gold, $amount);
        }
        $this->gold     -= $amount;
        $this->updatedAt = new \DateTimeImmutable();
    }

    private static function validName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $len  = mb_strlen($name);

        if ($len < self::NAME_MIN || $len > self::NAME_MAX) {
            throw new InvalidCharacter(sprintf('El nombre debe tener entre %d y %d caracteres.', self::NAME_MIN, self::NAME_MAX));
        }

        return $name;
    }
}
