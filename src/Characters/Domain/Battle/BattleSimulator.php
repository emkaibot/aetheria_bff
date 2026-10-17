<?php

declare(strict_types=1);

namespace App\Characters\Domain\Battle;

use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterClass;
use App\Characters\Domain\Perk;
use App\Characters\Domain\Progression;
use App\Characters\Domain\Stats;

/**
 * Resuelve un combate por turnos de principio a fin y devuelve el registro.
 *
 * Se resuelve **en el servidor**: si lo hiciera la app, cualquiera podría
 * mandar «he ganado» y quedarse con la recompensa. La app sólo reproduce el
 * registro con su animación.
 *
 * Cada ronda actúan los dos, primero el más rápido (en empate, el héroe). El
 * héroe usa su especial de clase cada tres acciones; el jefe, el suyo cada
 * cuatro. Si a las 50 rondas siguen los dos en pie, gana el jefe: así una
 * clériga que se cura no puede alargar una pelea perdida para siempre.
 */
final class BattleSimulator
{
    public const MAX_ROUNDS = 50;

    private const HERO_SPECIAL_EVERY = 3;
    private const BOSS_SPECIAL_EVERY = 4;
    private const BOSS_SPECIAL_MULT  = 1.7;

    /** @var array<int, array<string, mixed>> */
    private array $log = [];
    private int $round = 0;

    public function __construct(private readonly Rng $rng = new Rng()) {}

    /** @param \App\Characters\Domain\Item[] $items el equipo del héroe */
    public function fight(Character $character, Boss $boss, array $items = []): BattleResult
    {
        return $this->simulate($character->getName(), $character->getClass(), $character->getEnergy(), $boss, null, $items);
    }

    /**
     * Sin entidad: lo usa `make balance` para probar niveles sin crear
     * personajes. `$bossStats` sustituye las del jefe al calibrarlo.
     */
    public function simulate(string $name, CharacterClass $class, int $energy, Boss $boss, ?Stats $bossStats = null, array $items = []): BattleResult
    {
        $this->log   = [];
        $this->round = 0;

        $hero  = new Fighter($name, Progression::stats($class, $energy, $items), Perk::unlockedBy($energy));
        $enemy = new Fighter($boss->name(), $bossStats ?? $boss->stats());

        while ($hero->alive() && $enemy->alive() && $this->round < self::MAX_ROUNDS) {
            $this->round++;

            $heroFirst = $hero->stats->spd >= $enemy->stats->spd;
            $order     = $heroFirst ? ['hero', 'boss'] : ['boss', 'hero'];

            foreach ($order as $turn) {
                if (!$hero->alive() || !$enemy->alive()) {
                    break;
                }

                $turn === 'hero'
                    ? $this->heroTurn($hero, $enemy, $class)
                    : $this->bossTurn($enemy, $hero, $boss);
            }
        }

        if ($hero->alive() && $enemy->alive()) {
            $this->entry('boss', 'timeout', sprintf('%s resiste. El combate se alarga demasiado y %s se retira.', $boss->name(), $name), $hero, $enemy);
        }

        return new BattleResult(
            won: !$enemy->alive() && $hero->alive(),
            log: $this->log,
            heroMaxHp: $hero->stats->hp,
            bossMaxHp: $enemy->stats->hp,
            rounds: $this->round,
        );
    }

    private function heroTurn(Fighter $hero, Fighter $enemy, CharacterClass $class): void
    {
        $hero->actions++;
        $special = $hero->actions % self::HERO_SPECIAL_EVERY === 0;

        if (!$special) {
            $this->strike($hero, $enemy, 'hero', 'attack', 1.0, 'ataca');

            return;
        }

        match ($class) {
            CharacterClass::Warrior => $this->strike($hero, $enemy, 'hero', 'special', 2.0, 'usa ' . $class->specialName()),
            CharacterClass::Mage    => $this->strike($hero, $enemy, 'hero', 'special', 1.7, 'lanza ' . $class->specialName(), ignoreDefense: true),
            CharacterClass::Rogue   => $this->rogueSpecial($hero, $enemy, $class),
            CharacterClass::Cleric  => $this->clericSpecial($hero, $enemy, $class),
        };
    }

    private function rogueSpecial(Fighter $hero, Fighter $enemy, CharacterClass $class): void
    {
        $this->strike($hero, $enemy, 'hero', 'special', 1.0, 'usa ' . $class->specialName());
        if ($enemy->alive()) {
            $this->strike($hero, $enemy, 'hero', 'special', 1.0, 'remata');
        }
    }

    private function clericSpecial(Fighter $hero, Fighter $enemy, CharacterClass $class): void
    {
        $healed = $hero->heal((int) round($hero->stats->hp * 0.14));
        $this->entry('hero', 'heal', sprintf('%s invoca %s y recupera %d de vida.', $hero->name, $class->specialName(), $healed), $hero, $enemy, heal: $healed);
        $this->strike($hero, $enemy, 'hero', 'attack', 1.0, 'ataca envuelta en luz');
    }

    private function bossTurn(Fighter $boss, Fighter $hero, Boss $def): void
    {
        $boss->actions++;
        $special = $boss->actions % self::BOSS_SPECIAL_EVERY === 0;

        $special
            ? $this->strike($boss, $hero, 'boss', 'special', self::BOSS_SPECIAL_MULT, 'desata ' . $def->specialName())
            : $this->strike($boss, $hero, 'boss', 'attack', 1.0, 'ataca');
    }

    private function strike(
        Fighter $attacker,
        Fighter $target,
        string $actor,
        string $action,
        float $mult,
        string $verb,
        bool $ignoreDefense = false,
    ): void {
        if ($target->has(Perk::Reflexes) && $this->rng->chance(0.10)) {
            $this->entry($actor, 'dodge', sprintf('%s %s, pero %s lo esquiva.', $attacker->name, $verb, $target->name), ...$this->sides($actor, $attacker, $target));

            return;
        }

        $defense = $ignoreDefense ? 0 : $target->stats->def;
        $damage  = $attacker->stats->atk * 50 / (50 + $defense) * $mult * $this->rng->between(0.8, 1.2);

        $crit = $attacker->has(Perk::Critical) && $this->rng->chance(0.15);
        if ($crit) {
            $damage *= 2;
        }
        if ($attacker->has(Perk::Ascension) && !$attacker->hasHit) {
            $damage *= 3;
        }
        if ($target->has(Perk::StoneSkin)) {
            $damage *= 0.9;
        }

        $damage = max(1, (int) round($damage));
        $attacker->hasHit = true;
        $target->hp = max(0, $target->hp - $damage);

        $text = sprintf('%s %s: %d de daño%s.', $attacker->name, $verb, $damage, $crit ? ' ¡crítico!' : '');
        $this->entry($actor, $action, $text, ...$this->sides($actor, $attacker, $target), damage: $damage, crit: $crit);

        if ($attacker->has(Perk::Vampiric) && $attacker->alive()) {
            $healed = $attacker->heal((int) round($damage * 0.12));
            if ($healed > 0) {
                $this->entry($actor, 'heal', sprintf('%s absorbe %d de vida.', $attacker->name, $healed), ...$this->sides($actor, $attacker, $target), heal: $healed);
            }
        }

        if ($target->has(Perk::SecondWind) && !$target->usedSecondWind && $target->alive() && $target->hp < $target->stats->hp * 0.25) {
            $target->usedSecondWind = true;
            $healed = $target->heal((int) round($target->stats->hp * 0.35));
            $other  = $actor === 'hero' ? 'boss' : 'hero';
            $this->entry($other, 'heal', sprintf('¡Segundo aliento! %s recupera %d de vida.', $target->name, $healed), ...$this->sides($other, $target, $attacker), heal: $healed);
        }
    }

    /** @return array{0: Fighter, 1: Fighter} [héroe, jefe] sea quien sea el que actúa */
    private function sides(string $actor, Fighter $actorFighter, Fighter $other): array
    {
        return $actor === 'hero' ? [$actorFighter, $other] : [$other, $actorFighter];
    }

    private function entry(
        string $actor,
        string $action,
        string $text,
        Fighter $hero,
        Fighter $boss,
        int $damage = 0,
        int $heal = 0,
        bool $crit = false,
    ): void {
        $this->log[] = [
            'round'   => $this->round,
            'actor'   => $actor,
            'action'  => $action,
            'text'    => $text,
            'damage'  => $damage,
            'heal'    => $heal,
            'crit'    => $crit,
            'hero_hp' => $hero->hp,
            'boss_hp' => $boss->hp,
        ];
    }
}
