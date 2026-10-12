<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Presenter;

use App\Characters\Domain\Battle\Boss;
use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterClass;
use App\Characters\Domain\EnergyMovement;
use App\Characters\Domain\Perk;
use App\Characters\Domain\Progression;

/**
 * Cómo salen las cosas por la API. Todo lo derivado —nivel, estadísticas,
 * ventajas— va ya calculado: la app no repite ninguna fórmula.
 */
final class CharacterPresenter
{
    /** @param array<string, array{victories: int, defeats: int}> $records bossId => récord */
    public static function character(Character $c, array $records = []): array
    {
        $energy = $c->getEnergy();
        $next   = Perk::nextFor($energy);

        $victories = array_sum(array_column($records, 'victories'));
        $defeats   = array_sum(array_column($records, 'defeats'));

        return [
            'id'          => $c->getId(),
            'name'        => $c->getName(),
            'class'       => self::classRef($c->getClass()),
            'energy'      => $energy,
            'progression' => Progression::describe($energy),
            'stats'       => Progression::stats($c->getClass(), $energy)->toArray(),
            'perks'       => array_map(fn (Perk $p) => self::perk($p, $energy), Perk::cases()),
            'next_perk'   => $next === null ? null : self::perk($next, $energy) + ['missing' => $next->threshold() - $energy],
            'record'      => [
                'victories'      => $victories,
                'defeats'        => $defeats,
                'bosses_beaten'  => count(array_filter($records, fn ($r) => $r['victories'] > 0)),
            ],
            'created_at'  => $c->getCreatedAt()->format(DATE_ATOM),
            'updated_at'  => $c->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    public static function movement(EnergyMovement $m): array
    {
        $boss = $m->getBossId() !== null ? Boss::tryFrom($m->getBossId()) : null;

        return [
            'id'            => $m->getId(),
            'amount'        => $m->getAmount(),
            'balance_after' => $m->getBalanceAfter(),
            'kind'          => $m->getKind()->value,
            'note'          => $m->getNote(),
            'boss'          => $boss === null ? null : ['id' => $boss->value, 'name' => $boss->name(), 'emoji' => $boss->emoji()],
            'created_at'    => $m->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    public static function perk(Perk $p, ?int $energy = null): array
    {
        $out = [
            'id'          => $p->value,
            'name'        => $p->label(),
            'emoji'       => $p->emoji(),
            'threshold'   => $p->threshold(),
            'description' => $p->description(),
        ];

        if ($energy !== null) {
            $out['unlocked'] = $energy >= $p->threshold();
        }

        return $out;
    }

    public static function classRef(CharacterClass $class): array
    {
        return ['id' => $class->value, 'name' => $class->label(), 'emoji' => $class->emoji()];
    }

    public static function classFull(CharacterClass $class): array
    {
        return self::classRef($class) + [
            'description' => $class->description(),
            'base'        => $class->base()->toArray(),
            'growth'      => $class->growth()->toArray(),
            'special'     => ['name' => $class->specialName(), 'description' => $class->specialDescription()],
        ];
    }

    /**
     * @param ?array{victories: int, defeats: int} $record
     * @param ?string $status `locked` | `available` | `beaten`, para la lista de un personaje
     */
    public static function boss(Boss $b, ?array $record = null, ?string $status = null): array
    {
        $out = [
            'id'                => $b->value,
            'name'              => $b->name(),
            'emoji'             => $b->emoji(),
            'lore'              => $b->lore(),
            'stats'             => $b->stats()->toArray(),
            'recommended_level' => $b->recommendedLevel(),
            'reward'            => $b->reward(),
            'penalty'           => $b->penalty(),
            'special'           => $b->specialName(),
            'requires'          => $b->previous()?->value,
        ];

        if ($status !== null) {
            $out['status']    = $status;
            $out['victories'] = $record['victories'] ?? 0;
            $out['defeats']   = $record['defeats'] ?? 0;
        }

        return $out;
    }
}
