<?php

declare(strict_types=1);

namespace App\Characters\Domain;

enum MovementKind: string
{
    /** Añadido o quitado a mano desde la ficha. */
    case Manual  = 'manual';
    case Victory = 'victory';
    case Defeat  = 'defeat';
}
