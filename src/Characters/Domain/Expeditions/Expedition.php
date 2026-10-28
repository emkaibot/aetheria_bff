<?php

declare(strict_types=1);

namespace App\Characters\Domain\Expeditions;

use App\Characters\Domain\Character;
use App\Characters\Domain\Gear\Rarity;
use Doctrine\ORM\Mapping as ORM;

/**
 * Una expedición AFK: el equipo se va a una zona y acumula botín aunque se
 * cierre la app. No se simula nada mientras tanto: al recogerla, el servidor
 * mira cuánto tiempo ha pasado (con el límite elegido) y reparte según las
 * tasas de la zona. Un personaje tiene como mucho una en marcha.
 */
#[ORM\Entity]
#[ORM\Table(name: 'expeditions')]
#[ORM\Index(name: 'idx_expeditions_character', columns: ['character_id', 'started_at'])]
#[ORM\UniqueConstraint(name: 'uniq_expeditions_active', columns: ['character_id'], options: ['where' => '(claimed_at IS NULL)'])]
class Expedition
{
    public const LIMITS = [8, 12, 24];

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Character::class)]
    #[ORM\JoinColumn(name: 'character_id', nullable: false, onDelete: 'CASCADE')]
    private Character $character;

    #[ORM\Column(type: 'string', length: 16, enumType: Zone::class)]
    private Zone $zone;

    #[ORM\Column(type: 'string', length: 16, enumType: Difficulty::class)]
    private Difficulty $difficulty;

    #[ORM\Column(name: 'limit_hours', type: 'integer')]
    private int $limitHours;

    /** Lo de esta rareza o menos se vende solo al recoger; null, nada. */
    #[ORM\Column(type: 'string', length: 16, nullable: true, enumType: Rarity::class)]
    private ?Rarity $autosell;

    #[ORM\Column(name: 'started_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(name: 'claimed_at', type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $claimedAt = null;

    /** Lo que dio al recogerla, para volver a enseñarlo. */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $summary = null;

    public function __construct(string $id, Character $character, Zone $zone, Difficulty $difficulty, int $limitHours, ?Rarity $autosell, ?\DateTimeImmutable $now = null)
    {
        $this->id         = $id;
        $this->character  = $character;
        $this->zone       = $zone;
        $this->difficulty = $difficulty;
        $this->limitHours = $limitHours;
        $this->autosell   = $autosell;
        $this->startedAt  = $now ?? new \DateTimeImmutable();
    }

    public function getId(): string                     { return $this->id; }
    public function getZone(): Zone                     { return $this->zone; }
    public function getDifficulty(): Difficulty         { return $this->difficulty; }
    public function getLimitHours(): int                { return $this->limitHours; }
    public function getAutosell(): ?Rarity              { return $this->autosell; }
    public function getStartedAt(): \DateTimeImmutable  { return $this->startedAt; }
    public function getClaimedAt(): ?\DateTimeImmutable { return $this->claimedAt; }
    public function getSummary(): ?array                { return $this->summary; }

    /** Segundos que cuentan: los pasados desde que salió, con el límite como tope. */
    public function elapsedSeconds(\DateTimeImmutable $now): int
    {
        $elapsed = $now->getTimestamp() - $this->startedAt->getTimestamp();

        return max(0, min($this->limitHours * 3600, $elapsed));
    }

    public function claim(array $summary, \DateTimeImmutable $now): void
    {
        $this->claimedAt = $now;
        $this->summary   = $summary;
    }
}
