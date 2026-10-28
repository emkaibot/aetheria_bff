<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Presenter;

use App\Characters\Domain\Pets\CharacterPet;
use App\Characters\Domain\Pets\Pet;
use App\Characters\Domain\Progression;

/** Las mascotas tal y como salen por la API. */
final class PetPresenter
{
    public static function pet(Pet $p): array
    {
        $boss = $p->unlockedBy();

        return [
            'id'      => $p->value,
            'title'   => $p->title(),
            'forms'   => array_map(fn (string $name, int $i) => ['name' => $name, 'stage' => $i + 1, 'level' => $i === 0 ? 1 : Pet::EVOLVES_AT[$i - 1]], $p->forms(), array_keys($p->forms())),
            'bonus'   => ['name' => $p->bonusLabel(), 'values' => $p->bonusValues(), 'unit' => $p->bonusAttribute() === 'crit' ? 'pp' : '%'],
            'special' => [
                'name'        => $p->specialName(),
                'description' => $p->specialDescription(),
                'values'      => $p->specialValues(),
                'every'       => $p->every(),
            ],
            'starter' => in_array($p, Pet::STARTERS, true),
            'boss'    => $boss === null ? null : ['id' => $boss->value, 'name' => $boss->name(), 'emoji' => $boss->emoji()],
            'price'   => $p->price(),
        ];
    }

    /** Una mascota vista desde un personaje: su forma, su nivel y si va activa. */
    public static function petFor(Pet $p, ?CharacterPet $owned): array
    {
        $exp   = $owned?->getExp() ?? 0;
        $level = Progression::level($exp);

        return self::pet($p) + [
            'owned'       => $owned !== null,
            'active'      => $owned?->isActive() ?? false,
            'name'        => $p->name($level),
            'stage'       => Pet::stage($level),
            'exp'         => $exp,
            'progression' => Progression::describe($exp),
        ];
    }

    /** @param CharacterPet[] $pets */
    public static function summary(array $pets): array
    {
        return array_map(fn (CharacterPet $p) => [
            'id'    => $p->getPet()->value,
            'name'  => $p->getPet()->name($p->level()),
            'stage' => Pet::stage($p->level()),
            'level' => $p->level(),
        ], array_values($pets));
    }
}
