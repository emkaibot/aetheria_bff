<?php

declare(strict_types=1);

namespace App\Characters\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Una entrada del histórico de éter. Inmutable: lo hecho no se edita, se
 * compensa con otro movimiento. Se borra sólo con su personaje (cascade).
 */
#[ORM\Entity]
#[ORM\Table(name: 'energy_movements')]
#[ORM\Index(name: 'idx_energy_movements_character', columns: ['character_id', 'created_at'])]
class EnergyMovement
{
    public const NOTE_MAX = 140;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Character::class)]
    #[ORM\JoinColumn(name: 'character_id', nullable: false, onDelete: 'CASCADE')]
    private Character $character;

    /** Con signo: positivo suma, negativo resta. Cero sólo en una derrota sin saldo que perder. */
    #[ORM\Column(type: 'integer')]
    private int $amount;

    #[ORM\Column(name: 'balance_after', type: 'integer')]
    private int $balanceAfter;

    #[ORM\Column(type: 'string', length: 16, enumType: MovementKind::class)]
    private MovementKind $kind;

    #[ORM\Column(type: 'string', length: self::NOTE_MAX, nullable: true)]
    private ?string $note;

    /** El jefe del combate, en victorias y derrotas. */
    #[ORM\Column(name: 'boss_id', type: 'string', length: 32, nullable: true)]
    private ?string $bossId;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        Character $character,
        int $amount,
        int $balanceAfter,
        MovementKind $kind,
        ?string $note = null,
        ?string $bossId = null,
    ) {
        $note = $note !== null ? trim($note) : null;
        if ($note !== null && mb_strlen($note) > self::NOTE_MAX) {
            throw new InvalidCharacter(sprintf('La nota no puede pasar de %d caracteres.', self::NOTE_MAX));
        }

        $this->id           = $id;
        $this->character    = $character;
        $this->amount       = $amount;
        $this->balanceAfter = $balanceAfter;
        $this->kind         = $kind;
        $this->note         = $note === '' ? null : $note;
        $this->bossId       = $bossId;
        $this->createdAt    = new \DateTimeImmutable();
    }

    public function getId(): string                    { return $this->id; }
    public function getCharacter(): Character          { return $this->character; }
    public function getAmount(): int                   { return $this->amount; }
    public function getBalanceAfter(): int             { return $this->balanceAfter; }
    public function getKind(): MovementKind            { return $this->kind; }
    public function getNote(): ?string                 { return $this->note; }
    public function getBossId(): ?string               { return $this->bossId; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
