<?php

declare(strict_types=1);

namespace App\Characters\Domain;

use App\Shared\Domain\DomainError;

final class InsufficientEnergy extends \DomainException implements DomainError
{
    public function code(): string
    {
        return 'insufficient_energy';
    }

    public function __construct(public readonly int $balance, public readonly int $requested)
    {
        parent::__construct(sprintf('Sólo tiene %d de éter; no se pueden quitar %d.', $balance, $requested));
    }
}
