<?php

declare(strict_types=1);

namespace App\Characters\Domain\Battle;

use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterClass;
use App\Characters\Domain\CharacterHero;
use App\Characters\Domain\Hero;
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
 * Pelean el líder y los héroes de su equipo contra el jefe. Cada ronda actúan
 * todos, del más rápido al más lento (en empate, el equipo antes que el jefe).
 * Cada uno usa el especial de su papel cada tres acciones; el jefe ataca a un
 * miembro al azar (o al guardián que lo provoca) y cada cuatro acciones
 * golpea a todo el equipo. Se gana al tumbar al jefe y se pierde cuando cae el
 * equipo entero, o si a las 50 rondas el jefe sigue en pie: así una clériga
 * que cura no puede alargar una pelea perdida para siempre.
 *
 * El jefe crece con el tamaño del equipo, para que llevar más héroes ayude sin
 * convertir cada combate en un paseo. Cuánto crece lo dice cada jefe
 * (`Boss::partyScale`), calibrado con `make balance`.
 */
final class BattleSimulator
{
    public const MAX_ROUNDS = 50;

    private const SPECIAL_EVERY      = 3;
    private const BOSS_SPECIAL_EVERY = 4;
    private const BOSS_SPECIAL_MULT  = 0.9;
    /** Vida y ataque extra del jefe por cada héroe que acompaña al líder. */
    private const BOSS_HP_PER_ALLY  = 0.9;
    private const BOSS_ATK_PER_ALLY = 0.12;

    /** @var array<int, array<string, mixed>> */
    private array $log = [];
    private int $round = 0;
    /** @var Fighter[] el líder primero y luego el equipo, en su orden */
    private array $team = [];
    private Fighter $boss;

    public function __construct(private readonly Rng $rng = new Rng()) {}

    /**
     * @param \App\Characters\Domain\Item[] $items el equipo del líder
     * @param CharacterHero[]               $party los héroes que lo acompañan
     */
    public function fight(Character $character, Boss $boss, array $items = [], array $party = []): BattleResult
    {
        return $this->simulate(
            $character->getName(),
            $character->getClass(),
            $character->getEnergy(),
            $boss,
            null,
            $items,
            array_map(fn (CharacterHero $h) => [$h->getHero(), $h->getExp()], $party),
        );
    }

    /**
     * Sin entidades: lo usa `make balance` para probar niveles sin crear
     * personajes. `$bossStats` y `$partyScale` sustituyen los del jefe al
     * calibrarlo.
     *
     * @param \App\Characters\Domain\Item[] $items
     * @param array<int, array{0: Hero, 1: int}> $party héroe y su EXP
     */
    public function simulate(
        string $name,
        CharacterClass $class,
        int $energy,
        Boss $boss,
        ?Stats $bossStats = null,
        array $items = [],
        array $party = [],
        ?float $partyScale = null,
    ): BattleResult {
        $this->log   = [];
        $this->round = 0;

        $this->team = [new Fighter('leader', $name, Progression::stats($class, $energy, $items), $class->role(), Perk::unlockedBy($energy), Progression::level($energy))];
        foreach ($party as [$hero, $exp]) {
            $this->team[] = new Fighter($hero->value, $hero->label(), Progression::heroStats($hero, $exp), $hero->role(), level: Progression::level($exp));
        }
        $this->boss = new Fighter('boss', $boss->name(), self::scaledBoss($bossStats ?? $boss->stats(), count($party) * ($partyScale ?? $boss->partyScale())));

        while ($this->teamAlive() && $this->boss->alive() && $this->round < self::MAX_ROUNDS) {
            $this->round++;
            foreach ($this->turnOrder() as $fighter) {
                if (!$this->teamAlive() || !$this->boss->alive()) {
                    break;
                }
                if (!$fighter->alive()) {
                    continue;
                }
                $fighter === $this->boss ? $this->bossTurn($boss) : $this->heroTurn($fighter);
            }
        }

        if ($this->teamAlive() && $this->boss->alive()) {
            $this->entry('boss', 'boss', null, 'timeout', sprintf('%s resiste. El combate se alarga demasiado y el equipo se retira.', $boss->name()));
        }

        return new BattleResult(
            won: !$this->boss->alive() && $this->teamAlive(),
            log: $this->log,
            heroMaxHp: $this->team[0]->stats->hp,
            bossMaxHp: $this->boss->stats->hp,
            rounds: $this->round,
            team: array_map(fn (Fighter $f) => [
                'key'    => $f->key,
                'name'   => $f->name,
                'hero'   => $f->key === 'leader' ? null : $f->key,
                'role'   => $f->role?->value,
                'level'  => $f->level,
                'max_hp' => $f->stats->hp,
            ], $this->team),
        );
    }

    /** `$allies` va ya multiplicado por la escala del jefe, así que puede no ser entero. */
    public static function scaledBoss(Stats $stats, float $allies): Stats
    {
        return new Stats(
            (int) round($stats->hp * (1 + self::BOSS_HP_PER_ALLY * $allies)),
            (int) round($stats->atk * (1 + self::BOSS_ATK_PER_ALLY * $allies)),
            $stats->def,
            $stats->spd,
        );
    }

    /** @return Fighter[] */
    private function turnOrder(): array
    {
        // Ordenación estable: en empate el equipo (que va antes en la lista) gana.
        $all = [...$this->team, $this->boss];
        usort($all, fn (Fighter $a, Fighter $b) => $b->stats->spd <=> $a->stats->spd);

        return $all;
    }

    private function teamAlive(): bool
    {
        foreach ($this->team as $f) {
            if ($f->alive()) {
                return true;
            }
        }

        return false;
    }

    /** @return Fighter[] */
    private function aliveTeam(): array
    {
        return array_values(array_filter($this->team, fn (Fighter $f) => $f->alive()));
    }

    private function heroTurn(Fighter $hero): void
    {
        $hero->actions++;
        $role = $hero->role ?? Role::Warrior;

        if ($hero->actions % self::SPECIAL_EVERY !== 0) {
            $this->strike($hero, $this->boss, 'attack', 1.0, 'ataca');
            return;
        }

        $special = $role->specialName();
        match ($role) {
            Role::Warrior     => $this->strike($hero, $this->boss, 'special', 2.0, 'usa ' . $special),
            Role::Mage        => $this->strike($hero, $this->boss, 'special', 1.7, 'lanza ' . $special, ignoreDefense: true),
            Role::Rogue       => $this->multiStrike($hero, 2, 1.0, $special),
            Role::Martial     => $this->multiStrike($hero, 3, 0.6, $special),
            Role::Archer      => $this->strike($hero, $this->boss, 'special', 1.2, 'dispara ' . $special, forceCrit: true),
            Role::Berserker   => $this->strike($hero, $this->boss, 'special', 1.0 + 1.5 * (1 - $hero->hp / $hero->stats->hp), 'desata ' . $special),
            Role::Cleric      => $this->clericSpecial($hero, $special),
            Role::Druid       => $this->druidSpecial($hero, $special),
            Role::Guardian    => $this->guardianSpecial($hero, $special),
            Role::Necromancer => $this->necromancerSpecial($hero, $special),
        };
    }

    private function multiStrike(Fighter $hero, int $hits, float $mult, string $special): void
    {
        $this->strike($hero, $this->boss, 'special', $mult, 'usa ' . $special);
        for ($i = 1; $i < $hits && $this->boss->alive(); $i++) {
            $this->strike($hero, $this->boss, 'special', $mult, 'remata');
        }
    }

    private function clericSpecial(Fighter $hero, string $special): void
    {
        $ally   = $this->mostHurt();
        $healed = $ally->heal((int) round($ally->stats->hp * 0.18));
        $text   = $ally === $hero
            ? sprintf('%s invoca %s y recupera %d de vida.', $hero->name, $special, $healed)
            : sprintf('%s invoca %s: %s recupera %d de vida.', $hero->name, $special, $ally->name, $healed);
        $this->entry('hero', $hero->key, $ally->key, 'heal', $text, heals: [$ally->key => $healed]);
        $this->strike($hero, $this->boss, 'attack', 1.0, 'ataca envuelta en luz');
    }

    private function druidSpecial(Fighter $hero, string $special): void
    {
        $heals = [];
        foreach ($this->aliveTeam() as $ally) {
            $healed = $ally->heal((int) round($ally->stats->hp * 0.07));
            if ($healed > 0) {
                $heals[$ally->key] = $healed;
            }
        }
        $this->entry('hero', $hero->key, 'team', 'heal', sprintf('%s hace crecer %s: el equipo recupera %d de vida.', $hero->name, $special, array_sum($heals)), heals: $heals);
        $this->strike($hero, $this->boss, 'attack', 0.8, 'golpea');
    }

    private function guardianSpecial(Fighter $hero, string $special): void
    {
        $hero->taunt = 2;
        $this->entry('hero', $hero->key, $hero->key, 'special', sprintf('%s levanta %s y atrae al enemigo.', $hero->name, $special));
        $this->strike($hero, $this->boss, 'attack', 0.8, 'golpea con el escudo');
    }

    private function necromancerSpecial(Fighter $hero, string $special): void
    {
        $damage = $this->strike($hero, $this->boss, 'special', 1.3, 'usa ' . $special);
        $healed = $hero->heal(intdiv($damage, 2));
        if ($healed > 0) {
            $this->entry('hero', $hero->key, $hero->key, 'heal', sprintf('%s absorbe %d de vida.', $hero->name, $healed), heals: [$hero->key => $healed]);
        }
    }

    private function mostHurt(): Fighter
    {
        $alive = $this->aliveTeam();
        usort($alive, fn (Fighter $a, Fighter $b) => $a->hp / $a->stats->hp <=> $b->hp / $b->stats->hp);

        return $alive[0];
    }

    private function bossTurn(Boss $def): void
    {
        $this->boss->actions++;

        if ($this->boss->actions % self::BOSS_SPECIAL_EVERY === 0) {
            $hits = [];
            foreach ($this->aliveTeam() as $target) {
                $hits[$target->key] = $this->damage($this->boss, $target, self::BOSS_SPECIAL_MULT, false, false)[0];
            }
            $this->entry('boss', 'boss', 'team', 'special', sprintf('%s desata %s sobre todo el equipo: %d de daño.', $def->name(), $def->specialName(), array_sum($hits)), hits: $hits);
            foreach ($this->aliveTeam() as $target) {
                $this->secondWind($target);
            }
            return;
        }

        $this->strike($this->boss, $this->bossTarget(), 'attack', 1.0, 'ataca');
    }

    /** El guardián que provoca, si lo hay; si no, alguien del equipo al azar. */
    private function bossTarget(): Fighter
    {
        $alive = $this->aliveTeam();
        foreach ($alive as $f) {
            if ($f->taunt > 0) {
                $f->taunt--;
                return $f;
            }
        }

        return $alive[min(count($alive) - 1, (int) floor($this->rng->float() * count($alive)))];
    }

    /** Un golpe con su línea en el registro. Devuelve el daño hecho. */
    private function strike(
        Fighter $attacker,
        Fighter $target,
        string $action,
        float $mult,
        string $verb,
        bool $ignoreDefense = false,
        bool $forceCrit = false,
    ): int {
        $actor = $attacker === $this->boss ? 'boss' : 'hero';

        if ($target->has(Perk::Reflexes) && $this->rng->chance(0.10)) {
            $this->entry($actor, $attacker->key, $target->key, 'dodge', sprintf('%s %s, pero %s lo esquiva.', $attacker->name, $verb, $target->name));
            return 0;
        }

        [$damage, $crit] = $this->damage($attacker, $target, $mult, $ignoreDefense, $forceCrit);
        $suffix = $crit ? ' ¡crítico!' : '';
        $text   = $actor === 'boss' && count($this->team) > 1
            ? sprintf('%s %s a %s: %d de daño%s.', $attacker->name, $verb, $target->name, $damage, $suffix)
            : sprintf('%s %s: %d de daño%s.', $attacker->name, $verb, $damage, $suffix);
        $this->entry($actor, $attacker->key, $target->key, $action, $text, hits: [$target->key => $damage], crit: $crit);

        if ($attacker->has(Perk::Vampiric) && $attacker->alive()) {
            $healed = $attacker->heal((int) round($damage * 0.12));
            if ($healed > 0) {
                $this->entry($actor, $attacker->key, $attacker->key, 'heal', sprintf('%s absorbe %d de vida.', $attacker->name, $healed), heals: [$attacker->key => $healed]);
            }
        }

        $this->secondWind($target);

        return $damage;
    }

    /**
     * Calcula y aplica el daño, sin escribir nada en el registro.
     *
     * @return array{0: int, 1: bool} daño y si fue crítico
     */
    private function damage(Fighter $attacker, Fighter $target, float $mult, bool $ignoreDefense, bool $forceCrit): array
    {
        $defense = $ignoreDefense ? 0 : $target->stats->def;
        $damage  = $attacker->stats->atk * 50 / (50 + $defense) * $mult * $this->rng->between(0.8, 1.2);

        $crit = $forceCrit || ($attacker->has(Perk::Critical) && $this->rng->chance(0.15));
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

        return [$damage, $crit];
    }

    private function secondWind(Fighter $target): void
    {
        if (!$target->has(Perk::SecondWind) || $target->usedSecondWind || !$target->alive() || $target->hp >= $target->stats->hp * 0.25) {
            return;
        }

        $target->usedSecondWind = true;
        $healed = $target->heal((int) round($target->stats->hp * 0.35));
        $this->entry('hero', $target->key, $target->key, 'heal', sprintf('¡Segundo aliento! %s recupera %d de vida.', $target->name, $healed), heals: [$target->key => $healed]);
    }

    /**
     * Una línea del registro con la vida de todos tras ella. `hero_hp`,
     * `damage` y `heal` son los de antes del equipo (el líder y los totales),
     * para que una app sin actualizar siga pudiendo pintar el combate.
     *
     * @param array<string, int> $hits  daño por objetivo
     * @param array<string, int> $heals curación por objetivo
     */
    private function entry(
        string $actor,
        string $who,
        ?string $target,
        string $action,
        string $text,
        array $hits = [],
        array $heals = [],
        bool $crit = false,
    ): void {
        $teamHp = [];
        foreach ($this->team as $f) {
            $teamHp[$f->key] = $f->hp;
        }

        $this->log[] = [
            'round'   => $this->round,
            'actor'   => $actor,
            'who'     => $who,
            'target'  => $target,
            'action'  => $action,
            'text'    => $text,
            'damage'  => array_sum($hits),
            'heal'    => array_sum($heals),
            'hits'    => (object) $hits,
            'heals'   => (object) $heals,
            'crit'    => $crit,
            'hero_hp' => $this->team[0]->hp,
            'boss_hp' => $this->boss->hp,
            'team_hp' => $teamHp,
        ];
    }
}
