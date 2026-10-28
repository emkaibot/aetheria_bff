<?php

declare(strict_types=1);

namespace App\Characters\Application;

use App\Characters\Domain\Battle\BattleResult;
use App\Characters\Domain\Battle\BattleSimulator;
use App\Characters\Domain\Battle\Boss;
use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterRepository;
use App\Characters\Domain\Guilds\Guild;
use App\Characters\Domain\Guilds\GuildRepository;
use App\Characters\Domain\Guilds\Raid;
use App\Characters\Domain\Guilds\RaidAttack;
use App\Characters\Domain\InvalidGuild;
use App\Characters\Domain\Progression;
use App\Characters\Domain\Stats;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * La raid semanal del gremio. Cada ataque es un combate de pocas rondas
 * contra el jefe de la semana con una vida inagotable: lo que cuenta es el
 * daño, que se suma a la vida compartida del gremio y da sellos.
 *
 * El jefe pega como uno del nivel de quien ataca (el más fuerte que ya
 * debería poder vencer), para que todo el gremio aporte, empiece donde
 * empiece.
 */
final class Raids
{
    /** Vida del jefe en cada ataque: no se le tumba en uno. */
    private const ENDLESS_HP = 10_000_000;

    public function __construct(
        private readonly GuildRepository $guilds,
        private readonly CharacterRepository $characters,
        private readonly EntityManagerInterface $em,
    ) {}

    /** La raid de esta semana del gremio; la abre si aún no existe. */
    public function current(Guild $guild, ?\DateTimeImmutable $now = null): Raid
    {
        $week = Raid::weekOf($now ?? new \DateTimeImmutable());
        $raid = $this->guilds->raid($guild, $week);
        if ($raid === null) {
            $members = max(1, count($this->guilds->members($guild)));
            $raid    = new Raid(Uuid::v7()->toRfc4122(), $guild, $week, Raid::bossOf($week), Raid::HP_PER_MEMBER * $members);
            $this->em->persist($raid);
            $this->em->flush();
        }

        return $raid;
    }

    /** Ataques que le quedan hoy (UTC) a un personaje. */
    public function attacksLeft(Raid $raid, Character $c, ?\DateTimeImmutable $now = null): int
    {
        $today = ($now ?? new \DateTimeImmutable())->setTimezone(new \DateTimeZone('UTC'))->setTime(0, 0);

        return max(0, Raid::ATTACKS_PER_DAY - $this->guilds->attacksSince($raid, $c->getId(), $today));
    }

    /** @return array{result: BattleResult, raid: Raid, hp_before: int, damage: int, seals: int, killed: bool} */
    public function attack(Character $c): array
    {
        return $this->em->wrapInTransaction(function () use ($c): array {
            $this->em->lock($c, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($c);

            $member = $this->guilds->membershipOf($c->getId()) ?? throw new InvalidGuild('Únete a un gremio para atacar en la raid.');
            $now    = new \DateTimeImmutable();
            $raid   = $this->current($member->getGuild(), $now);
            // Varios del gremio pueden atacar a la vez: la vida compartida, de uno en uno.
            $this->em->lock($raid, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($raid);

            if ($raid->getDefeatedAt() !== null) {
                throw new InvalidGuild(sprintf('%s ya ha caído esta semana. La próxima, otro jefe.', $raid->getBoss()->name()));
            }
            if ($this->attacksLeft($raid, $c, $now) === 0) {
                throw new InvalidGuild(sprintf('Ya has atacado %d veces hoy. Vuelve mañana.', Raid::ATTACKS_PER_DAY));
            }

            $before = $raid->hp();
            $lineup = Lineup::of($c, $this->characters);
            $match  = self::matchingBoss(Progression::teamLevel($c->getEnergy(), $lineup->party));
            $s      = $match->stats();
            $result = (new BattleSimulator())->run(
                $lineup->combatants,
                $raid->getBoss(),
                $lineup->petLevels(),
                new Stats(self::ENDLESS_HP, $s->atk, $s->def, $s->spd),
                $match->partyScale(),
                Raid::ROUNDS,
            );

            $last   = $result->log[array_key_last($result->log)] ?? null;
            $damage = $result->bossMaxHp - (int) ($last['boss_hp'] ?? $result->bossMaxHp);
            $seals  = max(1, intdiv($damage, Raid::DAMAGE_PER_SEAL));
            $killed = $raid->hit($damage, $now);

            $c->earnSeals($seals + ($killed ? Raid::KILL_SEALS : 0));
            $this->em->persist(new RaidAttack(Uuid::v7()->toRfc4122(), $raid, $c, $damage, $seals, $now));
            $this->em->flush();

            if ($killed) {
                // Los sellos de la caída, para todos los que han pegado esta semana.
                $this->em->getConnection()->executeStatement(
                    'UPDATE characters SET seals = seals + ? WHERE id <> ? AND id IN (SELECT DISTINCT character_id FROM guild_raid_attacks WHERE raid_id = ?)',
                    [Raid::KILL_SEALS, $c->getId(), $raid->getId()],
                );
            }

            return ['result' => $result, 'raid' => $raid, 'hp_before' => $before, 'damage' => $damage, 'seals' => $seals, 'killed' => $killed];
        });
    }

    /** El jefe más fuerte que un equipo de ese nivel ya debería poder vencer. */
    private static function matchingBoss(int $teamLevel): Boss
    {
        $match = Boss::RatQueen;
        foreach (Boss::cases() as $b) {
            if ($b->recommendedLevel() <= $teamLevel) {
                $match = $b;
            }
        }

        return $match;
    }
}
