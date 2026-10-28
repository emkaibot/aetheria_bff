<?php

declare(strict_types=1);

namespace App\Characters\Domain;

use App\Shared\Domain\DomainError;

final class HeroAlreadyOwned extends \DomainException implements DomainError
{
    public function __construct(Hero $hero)
    {
        parent::__construct(sprintf('%s ya está en tu compañía.', $hero->label()));
    }

    public function code(): string
    {
        return 'hero_owned';
    }
}
