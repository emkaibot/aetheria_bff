<?php

declare(strict_types=1);

namespace App\Characters\Domain;

use App\Shared\Domain\DomainError;

final class InvalidGear extends \DomainException implements DomainError
{
    public function code(): string
    {
        return 'invalid_gear';
    }
}
