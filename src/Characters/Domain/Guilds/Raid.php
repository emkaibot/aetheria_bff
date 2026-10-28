<?php

declare(strict_types=1);

namespace App\Characters\Domain\Guilds;

use App\Characters\Domain\Battle\Boss;
use Doctrine\ORM\Mapping as ORM;

/**
 * La raid de un gremio en una semana: un jefe con una vida enorme que el
 * gremio va bajando entre todos. Cada semana (ISO) toca un jefe distinto y
 * la vida sale del número de miembros cuando se abre.
 */
#[ORM\Entity]
#[ORM\Table(name: 'guild_raids')]
#[ORM\UniqueConstraint(name: 'uniq_guild_raids_week', columns: ['guild_id', 'week'])]
class Raid
{
    /** Vida del jefe por cada miembro del gremio al abrirse. */
    public const HP_PER_MEMBER = 25000;
    public const ATTACKS_PER_DAY = 3;
    /** Rondas de cada ataque: el jefe no cae en uno, se le va quitando vida. */
    public const ROUNDS = 15;
    /** Daño por cada sello (como poco 1 por ataque), y los de todos los que pegaron cuando cae. */
    public const DAMAGE_PER_SEAL = 500;
    public const KILL_SEALS = 25;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Guild::class)]
    #[ORM\JoinColumn(name: 'guild_id', nullable: false, onDelete: 'CASCADE')]
    private Guild $guild;

    /** Semana ISO, `2026-W44`. */
    #[ORM\Column(type: 'string', length: 8)]
    private string $week;

    #[ORM\Column(type: 'string', length: 32, enumType: Boss::class)]
    private Boss $boss;

    #[ORM\Column(name: 'max_hp', type: 'bigint')]
    private int $maxHp;

    #[ORM\Column(type: 'bigint', options: ['default' => 0])]
    private int $damage = 0;

    #[ORM\Column(name: 'defeated_at', type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $defeatedAt = null;

    public function __construct(string $id, Guild $guild, string $week, Boss $boss, int $maxHp)
    {
        $this->id    = $id;
        $this->guild = $guild;
        $this->week  = $week;
        $this->boss  = $boss;
        $this->maxHp = $maxHp;
    }

    public function getId(): string                     { return $this->id; }
    public function getWeek(): string                   { return $this->week; }
    public function getBoss(): Boss                     { return $this->boss; }
    public function getMaxHp(): int                     { return (int) $this->maxHp; }
    public function getDamage(): int                    { return (int) $this->damage; }
    public function getDefeatedAt(): ?\DateTimeImmutable { return $this->defeatedAt; }

    public function hp(): int
    {
        return max(0, $this->getMaxHp() - $this->getDamage());
    }

    /** Suma daño y dice si con él cae (sólo la primera vez). */
    public function hit(int $damage, \DateTimeImmutable $now): bool
    {
        if ($this->defeatedAt !== null) {
            return false;
        }
        $this->damage = $this->getDamage() + max(0, $damage);
        if ($this->hp() === 0) {
            $this->defeatedAt = $now;
            return true;
        }

        return false;
    }

    /** La semana ISO de una fecha, `2026-W44`. */
    public static function weekOf(\DateTimeImmutable $at): string
    {
        return $at->format('o-\WW');
    }

    /** El jefe de esa semana: van rotando los siete. */
    public static function bossOf(string $week): Boss
    {
        $cases = Boss::cases();

        return $cases[(int) substr($week, -2) % count($cases)];
    }
}
