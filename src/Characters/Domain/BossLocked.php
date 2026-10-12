<?php

declare(strict_types=1);

namespace App\Characters\Domain;

use App\Characters\Domain\Battle\Boss;
use App\Shared\Domain\DomainError;

final class BossLocked extends \DomainException implements DomainError
{
    public function __construct(Boss $boss, Boss $required)
    {
        parent::__construct(sprintf('Para retar a %s hay que vencer antes a %s.', $boss->name(), $required->name()));
    }

    public function code(): string
    {
        return 'boss_locked';
    }
}
