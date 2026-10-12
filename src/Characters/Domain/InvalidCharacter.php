<?php

declare(strict_types=1);

namespace App\Characters\Domain;

use App\Shared\Domain\DomainError;

/** Datos que no valen; el mensaje está pensado para enseñarse tal cual. */
final class InvalidCharacter extends \DomainException implements DomainError
{
    public function code(): string
    {
        return 'invalid';
    }
}
