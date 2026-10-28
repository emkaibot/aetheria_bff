<?php

declare(strict_types=1);

namespace App\Characters\Application;

use App\Characters\Domain\CharacterHero;
use App\Characters\Domain\Pets\CharacterPet;

/** Reparte EXP al equipo y a las mascotas activas, y apunta quién sube o evoluciona. */
final class Experience
{
    /**
     * @param  CharacterHero[] $party
     * @param  CharacterPet[]  $pets
     * @return array{level_ups: array<string, int>, evolutions: array<string, int>}
     */
    public static function grant(array $party, array $pets, int $exp): array
    {
        $levelUps = [];
        foreach ($party as $h) {
            if (($up = $h->gainExp($exp)) > 0) {
                $levelUps[$h->getHero()->value] = $up;
            }
        }

        $evolutions = [];
        foreach ($pets as $p) {
            if (($up = $p->gainExp($exp)) > 0) {
                $evolutions[$p->getPet()->value] = $up;
            }
        }

        return ['level_ups' => $levelUps, 'evolutions' => $evolutions];
    }
}
