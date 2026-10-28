<?php

declare(strict_types=1);

namespace App\Characters\Domain\Battle;

use App\Characters\Domain\CharacterClass;
use App\Characters\Domain\Hero;
use App\Characters\Domain\Perk;
use App\Characters\Domain\Pets\Pet;
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
 * golpea a todo el equipo. Las mascotas no reciben golpes: dan su
 * bonificación a todos desde el principio y usan su habilidad al empezar las
 * rondas que les tocan. Se gana al tumbar al jefe y se pierde cuando cae el
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
    /** @var array<int, array{0: Pet, 1: int}> mascota y su nivel */
    private array $pets = [];
    /** La Mirada del oráculo: el siguiente golpe del equipo es crítico. */
    private bool $nextCrit = false;
    private bool $revived = false;

    public function __construct(private readonly Rng $rng = new Rng()) {}

    /**
     * @param Combatant[]                        $team el líder primero
     * @param array<int, array{0: Pet, 1: int}> $pets las mascotas activas y su nivel
     */
    public function run(array $team, Boss $boss, array $pets = [], ?Stats $bossStats = null, ?float $partyScale = null): BattleResult
    {
        $this->log      = [];
        $this->round    = 0;
        $this->pets     = $pets;
        $this->nextCrit = false;
        $this->revived  = false;

        [$statMult, $petBonus] = self::petPassives($pets);
        $this->team = array_map(fn (Combatant $c) => new Fighter(
            $c->key,
            $c->name,
            new Stats($c->stats->hp, $c->stats->atk, (int) round($c->stats->def * $statMult['def']), (int) round($c->stats->spd * $statMult['spd'])),
            $c->role,
            $c->perks,
            $c->level,
            $c->bonus->plus($petBonus),
        ), array_values($team));
        $allies     = count($team) - 1;
        $this->boss = new Fighter('boss', $boss->name(), self::scaledBoss($bossStats ?? $boss->stats(), $allies * ($partyScale ?? $boss->partyScale())));

        while ($this->teamAlive() && $this->boss->alive() && $this->round < self::MAX_ROUNDS) {
            $this->round++;
            $teamFirst = $this->petsAct();
            foreach ($this->turnOrder($teamFirst) as $fighter) {
                if (!$this->teamAlive() || !$this->boss->alive()) {
                    break;
                }
                if (!$fighter->alive()) {
                    continue;
                }
                $fighter === $this->boss ? $this->bossTurn($boss) : $this->heroTurn($fighter);
            }
            if ($this->boss->alive()) {
                $this->rebirth();
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
            pets: array_map(fn (array $p) => ['id' => $p[0]->value, 'name' => $p[0]->name($p[1]), 'stage' => Pet::stage($p[1]), 'level' => $p[1]], $pets),
        );
    }

    /**
     * Sin entidades ni equipamiento: lo usa `make balance` para probar niveles
     * sin crear personajes. `$bossStats` y `$partyScale` sustituyen los del
     * jefe al calibrarlo.
     *
     * @param \App\Characters\Domain\Item[]     $items
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
        $team = [Combatant::leader($name, $class, $energy, $items)];
        foreach ($party as [$hero, $exp]) {
            $team[] = Combatant::hero($hero, $exp);
        }

        return $this->run($team, $boss, [], $bossStats, $partyScale);
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

    /**
     * Lo que las mascotas dan a todo el equipo.
     *
     * @param  array<int, array{0: Pet, 1: int}> $pets
     * @return array{0: array{def: float, spd: float}, 1: Bonus}
     */
    public static function petPassives(array $pets): array
    {
        $mult  = ['def' => 1.0, 'spd' => 1.0];
        $extra = ['skill' => 0.0, 'healing' => 0.0, 'crit' => 0.0, 'lifesteal' => 0.0];
        foreach ($pets as [$pet, $level]) {
            $value = $pet->bonus($level) / 100;
            $attr  = $pet->bonusAttribute();
            isset($mult[$attr]) ? $mult[$attr] += $value : $extra[$attr] += $value;
        }

        return [$mult, new Bonus(crit: $extra['crit'], lifesteal: $extra['lifesteal'], healing: $extra['healing'], skill: $extra['skill'])];
    }

    /**
     * Las habilidades de las mascotas que tocan esta ronda. Devuelve si el
     * equipo actúa entero antes que el jefe (el Aullido plateado).
     */
    private function petsAct(): bool
    {
        $teamFirst = false;
        foreach ($this->pets as [$pet, $level]) {
            $every = $pet->every();
            if ($every === null || $this->round % $every !== 0 || !$this->boss->alive() || !$this->teamAlive()) {
                continue;
            }
            $name    = $pet->name($level);
            $special = $pet->specialName();
            $value   = $pet->specialValue($level) / 100;

            match ($pet) {
                Pet::Azurion, Pet::Umbra => $this->petStrike($pet, $name, $special, $value),
                Pet::Lunaris => (function () use (&$teamFirst, $pet, $name, $special): void {
                    $teamFirst = true;
                    $this->entry('pet', $pet->value, 'team', 'special', sprintf('%s lanza %s: el equipo actúa primero.', $name, $special));
                })(),
                Pet::Noctua => (function () use ($pet, $name, $special): void {
                    $this->nextCrit = true;
                    $this->entry('pet', $pet->value, 'team', 'special', sprintf('%s usa %s: el siguiente golpe será crítico.', $name, $special));
                })(),
                Pet::Bastion => (function () use ($pet, $name, $special, $value): void {
                    $shields = [];
                    foreach ($this->aliveTeam() as $f) {
                        $amount     = (int) round($f->stats->hp * $value);
                        $f->shield += $amount;
                        $shields[$f->key] = $amount;
                    }
                    $this->entry('pet', $pet->value, 'team', 'shield', sprintf('%s alza %s: el equipo gana %d de escudo.', $name, $special, array_sum($shields)), heals: $shields);
                })(),
                Pet::Solaria => null,
            };
        }

        return $teamFirst;
    }

    private function petStrike(Pet $pet, string $name, string $special, float $mult): void
    {
        $alive  = $this->aliveTeam();
        $avgAtk = array_sum(array_map(fn (Fighter $f) => $f->stats->atk, $alive)) / max(1, count($alive));
        $damage = max(1, (int) round($avgAtk * $mult * 50 / (50 + $this->boss->stats->def) * $this->rng->between(0.9, 1.1)));
        $this->boss->hp = max(0, $this->boss->hp - $damage);
        $this->entry('pet', $pet->value, 'boss', 'special', sprintf('%s usa %s: %d de daño.', $name, $special, $damage), hits: ['boss' => $damage]);
    }

    /** Renacer de Solaria: una vez por combate, al final de la ronda en que cae alguien. */
    private function rebirth(): void
    {
        if ($this->revived || !$this->teamAlive()) {
            return;
        }
        foreach ($this->pets as [$pet, $level]) {
            if ($pet !== Pet::Solaria) {
                continue;
            }
            foreach ($this->team as $f) {
                if (!$f->alive()) {
                    $this->revived = true;
                    $f->hp = max(1, (int) round($f->stats->hp * $pet->specialValue($level) / 100));
                    $this->entry('pet', $pet->value, $f->key, 'heal', sprintf('%s usa %s: %s vuelve al combate con %d de vida.', $pet->name($level), $pet->specialName(), $f->name, $f->hp), heals: [$f->key => $f->hp]);
                    return;
                }
            }
        }
    }

    /** @return Fighter[] */
    private function turnOrder(bool $teamFirst): array
    {
        // Ordenación estable: en empate el equipo (que va antes en la lista) gana.
        $team = $this->team;
        usort($team, fn (Fighter $a, Fighter $b) => $b->stats->spd <=> $a->stats->spd);
        if ($teamFirst) {
            return [...$team, $this->boss];
        }

        $all = [...$team, $this->boss];
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
        $healed = $ally->heal((int) round($ally->stats->hp * 0.18 * (1 + $hero->bonus->skill)));
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
            $healed = $ally->heal((int) round($ally->stats->hp * 0.07 * (1 + $hero->bonus->skill)));
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

        if ($target->dodgeChance() > 0 && $this->rng->chance($target->dodgeChance())) {
            $this->entry($actor, $attacker->key, $target->key, 'dodge', sprintf('%s %s, pero %s lo esquiva.', $attacker->name, $verb, $target->name));
            return 0;
        }

        if ($action === 'special') {
            $mult *= 1 + $attacker->bonus->skill;
        }
        if ($actor === 'hero' && $this->nextCrit) {
            $this->nextCrit = false;
            $forceCrit      = true;
        }

        [$damage, $crit] = $this->damage($attacker, $target, $mult, $ignoreDefense, $forceCrit);
        $suffix = $crit ? ' ¡crítico!' : '';
        $text   = $actor === 'boss' && count($this->team) > 1
            ? sprintf('%s %s a %s: %d de daño%s.', $attacker->name, $verb, $target->name, $damage, $suffix)
            : sprintf('%s %s: %d de daño%s.', $attacker->name, $verb, $damage, $suffix);
        $this->entry($actor, $attacker->key, $target->key, $action, $text, hits: [$target->key => $damage], crit: $crit);

        if ($attacker->lifesteal() > 0 && $attacker->alive()) {
            $healed = $attacker->heal((int) round($damage * $attacker->lifesteal()));
            if ($healed > 0) {
                $this->entry($actor, $attacker->key, $attacker->key, 'heal', sprintf('%s absorbe %d de vida.', $attacker->name, $healed), heals: [$attacker->key => $healed]);
            }
        }

        $this->secondWind($target);

        return $damage;
    }

    /**
     * Calcula y aplica el daño, sin escribir nada en el registro. El escudo
     * se gasta antes que la vida.
     *
     * @return array{0: int, 1: bool} daño y si fue crítico
     */
    private function damage(Fighter $attacker, Fighter $target, float $mult, bool $ignoreDefense, bool $forceCrit): array
    {
        $defense = $ignoreDefense ? 0 : $target->stats->def;
        $damage  = $attacker->stats->atk * 50 / (50 + $defense) * $mult * $this->rng->between(0.8, 1.2);

        $crit = $forceCrit || ($attacker->critChance() > 0 && $this->rng->chance($attacker->critChance()));
        if ($crit) {
            $damage *= 2 + $attacker->bonus->critDamage;
        }
        if ($attacker->has(Perk::Ascension) && !$attacker->hasHit) {
            $damage *= 3;
        }
        if ($target->has(Perk::StoneSkin)) {
            $damage *= 0.9;
        }

        $damage = max(1, (int) round($damage));
        $attacker->hasHit = true;

        $absorbed        = min($target->shield, $damage);
        $target->shield -= $absorbed;
        $target->hp      = max(0, $target->hp - ($damage - $absorbed));

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
     * @param string             $actor `hero`, `boss` o `pet`
     * @param array<string, int> $hits  daño por objetivo
     * @param array<string, int> $heals curación (o escudo, en `shield`) por objetivo
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
