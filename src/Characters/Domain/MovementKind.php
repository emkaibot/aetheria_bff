<?php

declare(strict_types=1);

namespace App\Characters\Domain;

enum MovementKind: string
{
    /** Añadido o quitado a mano desde la ficha. */
    case Manual  = 'manual';
    /**
     * Antiguos: hasta el 2026-10-17 los combates daban y quitaban éter. Ya no
     * (el éter sólo se da a mano), pero los movimientos de entonces siguen en
     * el histórico.
     */
    case Victory = 'victory';
    case Defeat  = 'defeat';
}
